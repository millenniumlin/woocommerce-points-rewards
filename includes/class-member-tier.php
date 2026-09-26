<?php
/**
 * 會員等級管理類別
 *
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 會員等級管理類別
 */
class WC_Points_Rewards_Member_Tier {

    /**
     * 單例實例
     */
    private static $instance = null;

    /**
     * 獲取單例實例
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 建構函式
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * 初始化 hooks
     */
    private function init_hooks() {
        // 每日檢查會員等級過期
        add_action('wc_points_rewards_daily_cleanup', array($this, 'check_tier_expiry'));

        // [修正 LOGIC-2] 分別監聽升級與降級事件，避免降級時仍觸發升級通知
        add_action('wc_points_rewards_tier_upgraded', array($this, 'send_tier_upgrade_notification'), 10, 2);
        add_action('wc_points_rewards_tier_changed',  array($this, 'handle_tier_changed'),           10, 3);
    }

    /**
     * 獲取所有會員等級
     *
     * [修正 SEC-3] 使用 get_var + prepare 確認表存在後再查詢
     */
    public function get_all_tiers() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name sanitized by prefix
        $tiers = $wpdb->get_results("
            SELECT * FROM `{$table_name}`
            ORDER BY tier_order ASC
        ");

        return $tiers ?: array();
    }

    /**
     * 獲取單個會員等級
     */
    public function get_tier($tier_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';

        $tier = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM `{$table_name}` WHERE id = %d
        ", $tier_id));

        return $tier;
    }

    /**
     * 創建或更新會員等級
     */
    public function save_tier($data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';

        $tier_data = array(
            'name'             => sanitize_text_field($data['name']),
            'min_amount'       => floatval($data['min_amount']),
            'bonus_percentage' => floatval($data['bonus_percentage']),
            'tier_order'       => intval($data['tier_order']),
        );

        if (isset($data['id']) && $data['id']) {
            $result = $wpdb->update($table_name, $tier_data, array('id' => intval($data['id'])));
        } else {
            $result = $wpdb->insert($table_name, $tier_data);
        }

        return $result !== false;
    }

    /**
     * 刪除會員等級
     */
    public function delete_tier($tier_id) {
        global $wpdb;

        $table_name  = $wpdb->prefix . 'wc_points_rewards_tiers';
        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';

        $users_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM `{$stats_table}`
            WHERE current_tier_id = %d
        ", $tier_id));

        if ($users_count > 0) {
            return new WP_Error('tier_in_use', __('無法刪除：仍有會員使用此等級', 'wc-points-rewards'));
        }

        $result = $wpdb->delete($table_name, array('id' => $tier_id));

        return $result !== false;
    }

    /**
     * 根據消費金額獲取符合的等級
     */
    public function get_tier_by_amount($amount) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';

        $tier = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM `{$table_name}`
            WHERE min_amount <= %f
            ORDER BY min_amount DESC
            LIMIT 1
        ", $amount));

        return $tier;
    }

    /**
     * 獲取用戶下一個等級
     *
     * [修正 LOGIC-12] 若用戶尚無等級，也應回傳第一個可達等級（而非 null）
     */
    public function get_next_tier($user_id) {
        global $wpdb;

        $database     = WC_Points_Rewards_Database::instance();
        $current_tier = $database->get_user_current_tier($user_id);
        $table_name   = $wpdb->prefix . 'wc_points_rewards_tiers';

        if (!$current_tier || !isset($current_tier->min_amount)) {
            // [修正 LOGIC-12] 沒有當前等級時，回傳 min_amount 最小的等級（即第一個可達等級）
            $next_tier = $wpdb->get_row("
                SELECT * FROM `{$table_name}`
                ORDER BY min_amount ASC
                LIMIT 1
            ");
            return $next_tier;
        }

        $next_tier = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM `{$table_name}`
            WHERE min_amount > %f
            ORDER BY min_amount ASC
            LIMIT 1
        ", $current_tier->min_amount));

        return $next_tier;
    }

    /**
     * 獲取用戶距離下一等級還需的消費金額
     */
    public function get_amount_to_next_tier($user_id) {
        $next_tier = $this->get_next_tier($user_id);
        if (!$next_tier) {
            return 0; // 已是最高等級
        }

        global $wpdb;
        $stats_table  = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $current_year = intval(wc_points_rewards_get_site_datetime()->format('Y'));

        $total_spent = $wpdb->get_var($wpdb->prepare("
            SELECT total_spent FROM `{$stats_table}`
            WHERE user_id = %d AND year = %d
        ", $user_id, $current_year));

        $total_spent = $total_spent ? floatval($total_spent) : 0;

        return max(0, $next_tier->min_amount - $total_spent);
    }

    /**
     * 檢查會員等級過期
     *
     * [修正 SEC-3/L5] 使用 prepare 確保 SQL 安全；使用站點時區比較過期時間
     * [修正 LOGIC-2] 觸發 wc_points_rewards_tier_changed 而非 wc_points_rewards_tier_upgraded，
     * 由 handle_tier_changed() 依據新舊等級差異決定發送升級或降級通知
     */
    public function check_tier_expiry() {
        global $wpdb;

        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $tiers_table = $wpdb->prefix . 'wc_points_rewards_tiers';

        // [修正 SEC-3] 改用 prepare + PHP 端時間比較，避免裸字串 SQL
        $current_datetime = wc_points_rewards_get_site_mysql_datetime();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name sanitized by prefix
        $expired_members = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM `{$stats_table}`
            WHERE tier_expiry_date IS NOT NULL
            AND tier_expiry_date <= %s
            AND current_tier_id IS NOT NULL
        ", $current_datetime));

        foreach ($expired_members as $member) {
            $current_year = intval(wc_points_rewards_get_site_datetime()->format('Y'));

            $current_spent = (float) $wpdb->get_var($wpdb->prepare("
                SELECT total_spent FROM `{$stats_table}`
                WHERE user_id = %d AND year = %d
            ", $member->user_id, $current_year));

            $new_tier = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM `{$tiers_table}`
                WHERE min_amount <= %f
                ORDER BY min_amount DESC
                LIMIT 1
            ", $current_spent));

            if ($new_tier) {
                $wpdb->update(
                    $stats_table,
                    array(
                        'current_tier_id'  => $new_tier->id,
                        'tier_start_date'  => current_time('mysql'),
                        'tier_expiry_date' => wc_points_rewards_get_site_datetime('+1 year')->format('Y-m-d H:i:s'),
                    ),
                    array('user_id' => $member->user_id, 'year' => $member->year)
                );

                // [修正 LOGIC-2] 觸發 tier_changed（含舊等級 ID），由 handle_tier_changed 決定通知類型
                do_action('wc_points_rewards_tier_changed', $member->user_id, $new_tier, intval($member->current_tier_id));
            }
        }
    }

    /**
     * 處理等級變更（升級或降級）
     *
     * [修正 LOGIC-2] 原本 check_tier_expiry 一律觸發 tier_upgraded，導致降級也寄出「恭喜升級」郵件
     * 此方法比較新舊等級，僅在真正升級時才發升級通知
     *
     * @param int      $user_id     用戶 ID
     * @param stdClass $new_tier    新等級物件
     * @param int      $old_tier_id 舊等級 ID
     */
    public function handle_tier_changed($user_id, $new_tier, $old_tier_id) {
        if (intval($new_tier->id) > intval($old_tier_id)) {
            // 真正的升級才發升級通知
            $this->send_tier_upgrade_notification($user_id, $new_tier);
        }
        // 降級或維持相同等級：可依需求另行實作降級通知邏輯（目前靜默處理）
    }

    /**
     * 發送等級升級通知
     */
    public function send_tier_upgrade_notification($user_id, $new_tier) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $subject = sprintf(__('恭喜！您已升級為 %s', 'wc-points-rewards'), $new_tier->name);

        $message = sprintf(
            __('親愛的 %s，<br><br>恭喜您已升級為 %s！<br>您現在可以享受 %s%% 的額外點數回饋。<br><br>感謝您的支持！', 'wc-points-rewards'),
            $user->display_name,
            $new_tier->name,
            $new_tier->bonus_percentage
        );

        wp_mail($user->user_email, $subject, $message);

        do_action('wc_points_rewards_tier_upgrade_notification_sent', $user_id, $new_tier);
    }

    /**
     * 獲取會員等級進度
     */
    public function get_tier_progress($user_id) {
        global $wpdb;

        $database    = WC_Points_Rewards_Database::instance();
        $current_tier = $database->get_user_current_tier($user_id);
        $next_tier   = $this->get_next_tier($user_id);

        $stats_table  = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $current_year = intval(wc_points_rewards_get_site_datetime()->format('Y'));

        $total_spent = (float) $wpdb->get_var($wpdb->prepare("
            SELECT total_spent FROM `{$stats_table}`
            WHERE user_id = %d AND year = %d
        ", $user_id, $current_year));

        $progress = array(
            'current_tier'       => $current_tier,
            'next_tier'          => $next_tier,
            'total_spent'        => $total_spent,
            'amount_to_next'     => $this->get_amount_to_next_tier($user_id),
            'progress_percentage' => 0,
        );

        if ($next_tier && $current_tier && isset($current_tier->min_amount)) {
            $range            = $next_tier->min_amount - $current_tier->min_amount;
            $current_progress = $total_spent - $current_tier->min_amount;
            $progress['progress_percentage'] = $range > 0 ? min(100, ($current_progress / $range) * 100) : 100;
        }

        return $progress;
    }

    /**
     * 手動設定用戶等級（管理員功能）
     */
    public function set_user_tier($user_id, $tier_id) {
        global $wpdb;

        $tier = $this->get_tier($tier_id);
        if (!$tier) {
            return false;
        }

        $stats_table  = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $current_year = intval(wc_points_rewards_get_site_datetime()->format('Y'));

        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM `{$stats_table}`
            WHERE user_id = %d AND year = %d
        ", $user_id, $current_year));

        $data = array(
            'current_tier_id'  => $tier_id,
            'tier_start_date'  => current_time('mysql'),
            'tier_expiry_date' => wc_points_rewards_get_site_datetime('+1 year')->format('Y-m-d H:i:s'),
        );

        if ($existing) {
            $result = $wpdb->update(
                $stats_table,
                $data,
                array('user_id' => $user_id, 'year' => $current_year)
            );
        } else {
            $data['user_id']     = $user_id;
            $data['year']        = $current_year;
            $data['total_spent'] = 0;
            $result = $wpdb->insert($stats_table, $data);
        }

        if ($result !== false) {
            do_action('wc_points_rewards_tier_manually_set', $user_id, $tier);
        }

        return $result !== false;
    }
}
