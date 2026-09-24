<?php
/**
 * 管理員點數操作管理類別
 *
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 集中管理管理員手動補發/扣除邏輯
 */
class WC_Points_Rewards_Admin_Points_Manager {

    /**
     * 手動補發描述標記。
     */
    const MANUAL_GRANT_MARKER = '[manual_grant]';

    /**
     * 單例實例
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * 獲取單例實例
     *
     * @return self
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 取得手動補發當日使用情況
     *
     * @param int|null $operator_id 操作者 ID。
     * @return array<string,mixed>
     */
    public function get_manual_grant_usage($operator_id = null) {
        global $wpdb;

        $settings = wc_points_rewards_get_manual_grant_settings();
        $operator_id = $operator_id ? intval($operator_id) : get_current_user_id();
        $points_table = $wpdb->prefix . 'wc_points_rewards_points';
        $day_window = wc_points_rewards_get_site_day_window_mysql();

        $admin_used = 0.0;

        if ($operator_id > 0) {
            $legacy_operator_marker = '%admin_user_id=' . $operator_id . ';%';
            $admin_used = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(points), 0)
                FROM {$points_table}
                WHERE type = %s
                AND points > 0
                AND description LIKE %s
                AND (
                    admin_user_id = %d
                    OR (admin_user_id IS NULL AND description LIKE %s)
                )
                AND created_at BETWEEN %s AND %s",
                'admin',
                '%' . self::MANUAL_GRANT_MARKER . '%',
                $operator_id,
                $legacy_operator_marker,
                $day_window['start'],
                $day_window['end']
            ));
        }

        $site_used = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0)
            FROM {$points_table}
            WHERE type = %s
            AND points > 0
            AND description LIKE %s
            AND created_at BETWEEN %s AND %s",
            'admin',
            '%' . self::MANUAL_GRANT_MARKER . '%',
            $day_window['start'],
            $day_window['end']
        ));

        return array(
            'admin_used'      => $admin_used,
            'admin_remaining' => max(0, $settings['per_admin_daily_max'] - $admin_used),
            'site_used'       => $site_used,
            'site_remaining'  => max(0, $settings['site_daily_max'] - $site_used),
            'day_window'      => $day_window,
            'settings'        => $settings,
        );
    }

    /**
     * 建立手動補發點數紀錄。
     *
     * @param int         $target_user_id 目標會員 ID。
     * @param float       $points         點數。
     * @param string      $reason         原因。
     * @param int|null    $operator_id    操作者 ID。
     * @return array<string,mixed>|WP_Error
     */
    public function create_manual_grant($target_user_id, $points, $reason, $operator_id = null) {
        if (!wc_points_rewards_is_site_administrator($operator_id)) {
            return new WP_Error('forbidden', __('只有網站管理員可以手動補發點數。', 'wc-points-rewards'));
        }

        $target_user_id = intval($target_user_id);
        $points         = (float) $points;
        $reason         = sanitize_textarea_field($reason);
        $reason         = function_exists('mb_substr') ? mb_substr($reason, 0, 500) : substr($reason, 0, 500);
        $operator_id    = $operator_id ? intval($operator_id) : get_current_user_id();
        $settings       = wc_points_rewards_get_manual_grant_settings();
        $target_user    = get_user_by('id', $target_user_id);
        $operator_user  = get_user_by('id', $operator_id);

        if ('yes' !== $settings['enabled']) {
            return new WP_Error('manual_grants_disabled', __('管理員手動補發功能目前已停用。', 'wc-points-rewards'));
        }

        if (!$target_user) {
            return new WP_Error('invalid_user', __('找不到指定的會員。', 'wc-points-rewards'));
        }

        if (!$operator_user) {
            return new WP_Error('invalid_operator', __('找不到操作中的管理員帳號。', 'wc-points-rewards'));
        }

        if ($points <= 0) {
            return new WP_Error('invalid_points', __('補發點數必須大於 0。', 'wc-points-rewards'));
        }

        if ('' === trim($reason)) {
            return new WP_Error('reason_required', __('請填寫補發原因。', 'wc-points-rewards'));
        }

        if ($points > $settings['per_grant_max']) {
            return new WP_Error(
                'per_grant_limit',
                sprintf(
                    __('單筆手動補發不得超過 %s。', 'wc-points-rewards'),
                    wc_points_rewards_format_points_with_value($settings['per_grant_max'])
                )
            );
        }

        $locks = array(
            $this->get_lock_name('site'),
            $this->get_lock_name('operator_' . $operator_id),
        );

        if (!$this->acquire_locks($locks)) {
            return new WP_Error('grant_lock_timeout', __('系統正忙碌中，請稍後再試。', 'wc-points-rewards'));
        }

        try {
            $usage = $this->get_manual_grant_usage($operator_id);

            if (($usage['admin_used'] + $points) > $settings['per_admin_daily_max']) {
                return new WP_Error(
                    'per_admin_daily_limit',
                    sprintf(
                        __('您今日剩餘可補發額度不足，尚可補發 %s。', 'wc-points-rewards'),
                        wc_points_rewards_format_points_with_value($usage['admin_remaining'])
                    )
                );
            }

            if (($usage['site_used'] + $points) > $settings['site_daily_max']) {
                return new WP_Error(
                    'site_daily_limit',
                    sprintf(
                        __('本站今日剩餘可補發額度不足，尚可補發 %s。', 'wc-points-rewards'),
                        wc_points_rewards_format_points_with_value($usage['site_remaining'])
                    )
                );
            }

            // 除了新增 admin_user_id 欄位外，描述仍保留穩定格式，方便舊資料與人工查核。
            $description = $this->build_operator_description(
                self::MANUAL_GRANT_MARKER,
                __('管理員補發', 'wc-points-rewards'),
                $reason,
                $operator_user
            );

            $database    = WC_Points_Rewards_Database::instance();
            $expiry_date = wc_points_rewards_calculate_points_expiry_date();
            $result      = $database->add_points(
                $target_user_id,
                $points,
                'admin',
                $description,
                null,
                $expiry_date,
                $operator_id
            );

            if (!$result) {
                return new WP_Error('grant_failed', __('新增手動補發點數失敗。', 'wc-points-rewards'));
            }

            if (class_exists('WC_Points_Rewards_Security')) {
                WC_Points_Rewards_Security::instance()->log_security_event(
                    'manual_points_granted',
                    sprintf(
                        '管理員 %1$s（ID:%2$d）為用戶 %3$d 補發 %4$s 點，原因：%5$s',
                        $operator_user->user_login,
                        $operator_id,
                        $target_user_id,
                        wc_points_rewards_number_format($points),
                        $reason
                    ),
                    $operator_id
                );
            }

            return array(
                'point_entry_id' => $result,
                'target_user'    => $target_user,
                'points'         => $points,
                'description'    => $description,
                'expiry_date'    => $expiry_date,
                'balance'        => $database->get_user_points($target_user_id),
                'usage'          => $this->get_manual_grant_usage($operator_id),
            );
        } finally {
            $this->release_locks($locks);
        }
    }

    /**
     * 管理員手動扣點。
     *
     * @param int         $target_user_id 目標會員 ID。
     * @param float       $points         點數。
     * @param string      $reason         原因。
     * @param int|null    $operator_id    操作者 ID。
     * @return array<string,mixed>|WP_Error
     */
    public function deduct_points($target_user_id, $points, $reason = '', $operator_id = null) {
        if (!wc_points_rewards_is_site_administrator($operator_id)) {
            return new WP_Error('forbidden', __('只有網站管理員可以手動扣除點數。', 'wc-points-rewards'));
        }

        $target_user_id = intval($target_user_id);
        $points         = (float) $points;
        $reason         = sanitize_textarea_field($reason);
        $reason         = function_exists('mb_substr') ? mb_substr($reason, 0, 500) : substr($reason, 0, 500);
        $operator_id    = $operator_id ? intval($operator_id) : get_current_user_id();
        $target_user    = get_user_by('id', $target_user_id);
        $operator_user  = get_user_by('id', $operator_id);

        if (!$target_user) {
            return new WP_Error('invalid_user', __('找不到指定的會員。', 'wc-points-rewards'));
        }

        if (!$operator_user) {
            return new WP_Error('invalid_operator', __('找不到操作中的管理員帳號。', 'wc-points-rewards'));
        }

        if ($points <= 0) {
            return new WP_Error('invalid_points', __('扣除點數必須大於 0。', 'wc-points-rewards'));
        }

        $description = $this->build_operator_description(
            '[admin_deduction]',
            __('管理員扣除', 'wc-points-rewards'),
            $reason ? $reason : __('管理員手動扣除', 'wc-points-rewards'),
            $operator_user
        );

        $database = WC_Points_Rewards_Database::instance();
        $result   = $database->deduct_points_with_lock(
            $target_user_id,
            $points,
            'admin',
            $description,
            null,
            $operator_id
        );

        if (is_wp_error($result)) {
            return $result;
        }

        if (class_exists('WC_Points_Rewards_Security')) {
            WC_Points_Rewards_Security::instance()->log_security_event(
                'manual_points_deducted',
                sprintf(
                    '管理員 %1$s（ID:%2$d）為用戶 %3$d 扣除 %4$s 點，原因：%5$s',
                    $operator_user->user_login,
                    $operator_id,
                    $target_user_id,
                    wc_points_rewards_number_format($points),
                    $reason ? $reason : __('管理員手動扣除', 'wc-points-rewards')
                ),
                $operator_id
            );
        }

        return array(
            'point_entry_id' => $result,
            'target_user'    => $target_user,
            'points'         => $points,
            'balance'        => $database->get_user_points($target_user_id),
        );
    }

    /**
     * 建立稽核描述。
     *
     * @param string  $marker       穩定標記。
     * @param string  $action_label 動作標籤。
     * @param string  $reason       原因。
     * @param WP_User $operator     操作者。
     * @return string
     */
    private function build_operator_description($marker, $action_label, $reason, $operator) {
        return sprintf(
            '%1$s %2$s：%3$s [admin_user_id=%4$d;login=%5$s;name=%6$s]',
            $marker,
            $action_label,
            $reason,
            $operator->ID,
            sanitize_user($operator->user_login, true),
            sanitize_text_field($operator->display_name)
        );
    }

    /**
     * 嘗試依序取得鎖。
     *
     * @param string[] $locks 鎖名稱列表。
     * @return bool
     */
    private function acquire_locks($locks) {
        $acquired = array();

        foreach ($locks as $lock_name) {
            if (!$this->acquire_lock($lock_name)) {
                $this->release_locks($acquired);
                return false;
            }

            $acquired[] = $lock_name;
        }

        return true;
    }

    /**
     * 取得鎖。
     *
     * @param string $lock_name 鎖名稱。
     * @return bool
     */
    private function acquire_lock($lock_name) {
        global $wpdb;

        return '1' === (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock_name));
    }

    /**
     * 釋放鎖。
     *
     * @param string[] $locks 鎖名稱。
     * @return void
     */
    private function release_locks($locks) {
        global $wpdb;

        foreach (array_reverse($locks) as $lock_name) {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }

    /**
     * 產生手動補發鎖名稱。
     *
     * @param string $suffix 後綴。
     * @return string
     */
    private function get_lock_name($suffix) {
        return substr('wcpr_manual_grant_' . get_current_blog_id() . '_' . sanitize_key($suffix), 0, 64);
    }
}
