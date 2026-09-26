<?php
/**
 * 資料庫管理類別
 *
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 資料庫管理類別
 */
class WC_Points_Rewards_Database {

    /**
     * 單例實例
     */
    private static $instance = null;

    /**
     * 點數表是否已支援 admin_user_id 欄位。
     *
     * @var bool|null
     */
    private static $points_table_supports_admin_user_id = null;

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
        add_action('init', array($this, 'init'));
    }

    /**
     * 初始化
     */
    public function init() {
        // 初始化邏輯保留供擴充
    }

    /**
     * 創建所有必要的資料庫表格
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // 會員等級表
        $tiers_table = $wpdb->prefix . 'wc_points_rewards_tiers';
        $tiers_sql = "CREATE TABLE $tiers_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL COMMENT '等級名稱',
            min_amount decimal(10,2) NOT NULL DEFAULT 0 COMMENT '最低消費金額',
            bonus_percentage decimal(5,2) NOT NULL DEFAULT 0 COMMENT '額外回饋百分比',
            tier_order int(11) NOT NULL DEFAULT 0 COMMENT '等級順序',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_min_amount (min_amount),
            KEY idx_tier_order (tier_order)
        ) $charset_collate COMMENT='會員等級表';";

        // 點數記錄表
        $points_table = $wpdb->prefix . 'wc_points_rewards_points';
        $points_sql = "CREATE TABLE $points_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL COMMENT '用戶ID',
            order_id bigint(20) unsigned DEFAULT NULL COMMENT '訂單ID',
            admin_user_id bigint(20) unsigned DEFAULT NULL COMMENT '操作管理員ID（人工補發/扣除/匯入）',
            points decimal(10,2) NOT NULL COMMENT '點數變化量',
            type varchar(50) NOT NULL COMMENT '點數類型：earned, redeemed, expired, admin',
            description text COMMENT '描述',
            expiry_date datetime DEFAULT NULL COMMENT '過期時間',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_order_id (order_id),
            KEY idx_admin_user_id (admin_user_id),
            KEY idx_type (type),
            KEY idx_expiry_date (expiry_date),
            KEY idx_created_at (created_at)
        ) $charset_collate COMMENT='點數記錄表';";

        // 會員年度消費統計表
        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $stats_sql = "CREATE TABLE $stats_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL COMMENT '用戶ID',
            year int(4) NOT NULL COMMENT '年份',
            total_spent decimal(10,2) NOT NULL DEFAULT 0 COMMENT '年度總消費',
            current_tier_id bigint(20) unsigned DEFAULT NULL COMMENT '當前等級ID',
            tier_start_date datetime DEFAULT NULL COMMENT '等級開始時間',
            tier_expiry_date datetime DEFAULT NULL COMMENT '等級過期時間',
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_year (user_id, year),
            KEY idx_user_id (user_id),
            KEY idx_year (year),
            KEY idx_current_tier_id (current_tier_id),
            KEY idx_tier_expiry_date (tier_expiry_date)
        ) $charset_collate COMMENT='會員年度消費統計表';";

        // 外掛設定表
        $settings_table = $wpdb->prefix . 'wc_points_rewards_settings';
        $settings_sql = "CREATE TABLE $settings_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL COMMENT '設定鍵',
            setting_value longtext COMMENT '設定值',
            autoload varchar(20) NOT NULL DEFAULT 'yes',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_setting_key (setting_key),
            KEY idx_autoload (autoload)
        ) $charset_collate COMMENT='外掛設定表';";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($tiers_sql);
        dbDelta($points_sql);
        dbDelta($stats_sql);
        dbDelta($settings_sql);

        self::ensure_points_table_admin_user_id_schema($points_table);

        // 更新資料庫版本
        update_option('wc_points_rewards_db_version', '1.0.0');
    }

    /**
     * 刪除所有資料庫表格
     *
     * [修正 SEC-3] 使用反引號包裹表名並做正則清洗，防止表名注入
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'wc_points_rewards_tiers',
            $wpdb->prefix . 'wc_points_rewards_points',
            $wpdb->prefix . 'wc_points_rewards_user_stats',
            $wpdb->prefix . 'wc_points_rewards_settings',
        );

        foreach ($tables as $table) {
            $safe_table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
            if (!empty($safe_table)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- sanitized above
                $wpdb->query("DROP TABLE IF EXISTS `{$safe_table}`");
            }
        }

        delete_option('wc_points_rewards_db_version');
    }

    /**
     * 獲取用戶總點數 - 改進空值處理和資料型別安全
     */
    public function get_user_points($user_id) {
        global $wpdb;

        $user_id = intval($user_id);
        if ($user_id <= 0) {
            return 0.0;
        }

        $table_name = $wpdb->prefix . 'wc_points_rewards_points';

        $total_points = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points), 0)
            FROM `{$table_name}`
            WHERE user_id = %d
            AND type != %s
            AND (expiry_date IS NULL OR expiry_date > %s)
        ", $user_id, 'expired', wc_points_rewards_get_site_mysql_datetime()));

        $result = floatval($total_points);
        return max(0, $result);
    }

    /**
     * 添加點數記錄 - 使用事務確保一致性
     */
    public function add_points($user_id, $points, $type, $description = '', $order_id = null, $expiry_date = null, $admin_user_id = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc_points_rewards_points';

        $user_id     = intval($user_id);
        $points      = floatval($points);
        $type        = sanitize_text_field($type);
        $description = sanitize_textarea_field($description);

        if ($user_id <= 0) {
            return false;
        }

        if (abs($points) > 999999999.99) {
            return false;
        }

        $wpdb->query('START TRANSACTION');

        try {
            $data = array(
                'user_id'    => $user_id,
                'points'     => $points,
                'type'       => $type,
                'description' => $description,
                'created_at' => current_time('mysql'),
            );

            if ($order_id) {
                $data['order_id'] = intval($order_id);
            }

            if (!is_null($admin_user_id) && intval($admin_user_id) > 0 && $this->points_table_supports_admin_user_id()) {
                $data['admin_user_id'] = intval($admin_user_id);
            }

            if ($expiry_date) {
                $data['expiry_date'] = sanitize_text_field($expiry_date);
            }

            $result = $wpdb->insert($table_name, $data);

            if ($result === false) {
                $wpdb->query('ROLLBACK');
                return false;
            }

            $wpdb->query('COMMIT');

            do_action('wc_points_rewards_points_added', $user_id, $points, $type, $description, $order_id);
            return $wpdb->insert_id;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('WC Points Rewards: Error adding points - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 獲取用戶點數記錄
     * [修正 SEC-4] 強制 user_id WHERE 條件，確保用戶只能查自己的記錄
     */
    public function get_user_points_history($user_id, $limit = 50, $offset = 0) {
        global $wpdb;

        $user_id = intval($user_id);
        if ($user_id <= 0) {
            return array();
        }

        $table_name = $wpdb->prefix . 'wc_points_rewards_points';

        // 限制 limit 上限避免大量資料查詢
        $limit  = max(1, min(intval($limit), 500));
        $offset = max(0, intval($offset));

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM `{$table_name}`
            WHERE user_id = %d
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d
        ", $user_id, $limit, $offset));

        return $results ?: array();
    }

    /**
     * 更新用戶年度消費統計
     *
     * [修正 L3] 使用站點時區取得年份，避免 PHP 伺服器時區與站點時區不一致
     *
     * @param int        $user_id 用戶ID
     * @param float      $amount  消費金額
     * @param int|null   $year    統計年份（null時使用當前年份）
     */
    public function update_user_yearly_stats($user_id, $amount, $year = null) {
        global $wpdb;

        if (!$year) {
            // [修正 L3] 使用站點時區而非 PHP 伺服器時區
            $year = intval(wc_points_rewards_get_site_datetime()->format('Y'));
        }

        $table_name = $wpdb->prefix . 'wc_points_rewards_user_stats';

        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM `{$table_name}`
            WHERE user_id = %d AND year = %d
        ", $user_id, $year));

        if ($existing) {
            $wpdb->update(
                $table_name,
                array('total_spent' => $existing->total_spent + $amount),
                array('user_id' => $user_id, 'year' => $year)
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'user_id'     => $user_id,
                    'year'        => $year,
                    'total_spent' => $amount,
                )
            );
        }

        $this->check_tier_upgrade($user_id, $year);
    }

    /**
     * 檢查會員等級升級
     *
     * @param int $user_id 用戶ID
     * @param int $year    統計年份
     * @return bool 是否成功升級
     */
    private function check_tier_upgrade($user_id, $year) {
        global $wpdb;

        $user_id = intval($user_id);
        $year    = intval($year);

        if ($user_id <= 0 || $year < 2000 || $year > 2100) {
            return false;
        }

        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $tiers_table = $wpdb->prefix . 'wc_points_rewards_tiers';

        $lock_name    = "wc_points_tier_upgrade_{$user_id}_{$year}";
        $lock_acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock_name));

        if (!$lock_acquired) {
            return false;
        }

        try {
            $total_spent = (float) $wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(total_spent, 0) FROM `{$stats_table}`
                WHERE user_id = %d AND year = %d
            ", $user_id, $year));

            $new_tier = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM `{$tiers_table}`
                WHERE min_amount <= %f
                ORDER BY min_amount DESC
                LIMIT 1
            ", $total_spent));

            if ($new_tier) {
                // [修正 L4-related] 使用 current_time() 而非 MySQL NOW()，確保使用站點時區
                $wpdb->update(
                    $stats_table,
                    array(
                        'current_tier_id'  => intval($new_tier->id),
                        'tier_start_date'  => current_time('mysql'),
                        'tier_expiry_date' => wc_points_rewards_get_site_datetime('+1 year')->format('Y-m-d H:i:s'),
                    ),
                    array('user_id' => $user_id, 'year' => $year),
                    array('%d', '%s', '%s'),
                    array('%d', '%d')
                );

                do_action('wc_points_rewards_tier_upgraded', $user_id, $new_tier);
                return true;
            }
        } finally {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }

        return false;
    }

    /**
     * 獲取用戶當前等級
     *
     * [修正 L5] 使用站點時區字串取代 MySQL NOW()，確保時區一致
     */
    public function get_user_current_tier($user_id) {
        global $wpdb;

        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $tiers_table = $wpdb->prefix . 'wc_points_rewards_tiers';

        // [修正 L3/L5] 使用站點時區取得年份
        $current_year     = intval(wc_points_rewards_get_site_datetime()->format('Y'));
        $current_datetime = wc_points_rewards_get_site_mysql_datetime();

        $tier = $wpdb->get_row($wpdb->prepare("
            SELECT t.*
            FROM `{$tiers_table}` t
            INNER JOIN `{$stats_table}` s ON t.id = s.current_tier_id
            WHERE s.user_id = %d
            AND s.year = %d
            AND (s.tier_expiry_date IS NULL OR s.tier_expiry_date > %s)
        ", $user_id, $current_year, $current_datetime));

        if (!$tier) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is sanitized
            $tier = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM `{$tiers_table}` WHERE tier_order = %d LIMIT 1",
                1
            ));
        }

        return $tier;
    }

    /**
     * 清理過期點數
     */
    public function cleanup_expired_points() {
        global $wpdb;

        $table_name   = $wpdb->prefix . 'wc_points_rewards_points';
        $current_time = wc_points_rewards_get_site_mysql_datetime();

        $wpdb->query($wpdb->prepare("
            UPDATE `{$table_name}`
            SET type = %s
            WHERE expiry_date IS NOT NULL
            AND expiry_date <= %s
            AND (
                type = %s
                OR (type = %s AND points > 0)
            )
        ", 'expired', $current_time, 'earned', 'admin'));

        do_action('wc_points_rewards_points_expired');
    }

    /**
     * 使用每位會員獨立鎖來扣除點數，避免併發超扣。
     *
     * @param int      $user_id       用戶 ID。
     * @param float    $points        要扣除的點數（正數）。
     * @param string   $type          點數類型。
     * @param string   $description   描述。
     * @param int|null $order_id      訂單 ID。
     * @param int|null $admin_user_id 操作管理員 ID。
     * @return int|WP_Error
     */
    public function deduct_points_with_lock($user_id, $points, $type, $description = '', $order_id = null, $admin_user_id = null) {
        global $wpdb;

        $user_id = intval($user_id);
        $points  = floatval($points);

        if ($user_id <= 0 || $points <= 0) {
            return new WP_Error('invalid_points_debit', __('扣點參數不正確。', 'wc-points-rewards'));
        }

        $lock_name     = $this->get_user_points_lock_name($user_id);
        $lock_acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock_name));

        if ('1' !== (string) $lock_acquired) {
            return new WP_Error('points_lock_timeout', __('系統正忙碌中，暫時無法扣除點數，請稍後再試。', 'wc-points-rewards'));
        }

        try {
            $available_points = $this->get_user_points_total_at_time($user_id, wc_points_rewards_get_site_mysql_datetime());

            if ($points > $available_points) {
                return new WP_Error('insufficient_points', __('點數不足，無法完成此次扣點。', 'wc-points-rewards'));
            }

            $result = $this->add_points(
                $user_id,
                -$points,
                $type,
                $description,
                $order_id,
                null,
                $admin_user_id
            );

            if (!$result) {
                return new WP_Error('points_deduct_failed', __('扣除點數失敗。', 'wc-points-rewards'));
            }

            return $result;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }

    /**
     * 取得指定時間點仍可用的點數總額。
     */
    private function get_user_points_total_at_time($user_id, $current_time) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc_points_rewards_points';

        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0)
            FROM `{$table_name}`
            WHERE user_id = %d
            AND type != %s
            AND (expiry_date IS NULL OR expiry_date > %s)",
            $user_id,
            'expired',
            $current_time
        ));
    }

    /**
     * 取得會員點數鎖名稱。
     */
    private function get_user_points_lock_name($user_id) {
        return substr('wcpr_points_user_' . get_current_blog_id() . '_' . intval($user_id), 0, 64);
    }

    /**
     * 確保升級站點也補上 admin_user_id 欄位與索引。
     */
    private static function ensure_points_table_admin_user_id_schema($points_table) {
        global $wpdb;

        $safe_points_table = preg_replace('/[^A-Za-z0-9_]/', '', $points_table);

        if (empty($safe_points_table)) {
            return;
        }

        $column_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$safe_points_table}` LIKE %s", 'admin_user_id'));

        if ('admin_user_id' !== $column_exists) {
            $wpdb->query(
                "ALTER TABLE `{$safe_points_table}` ADD COLUMN admin_user_id bigint(20) unsigned DEFAULT NULL COMMENT '操作管理員ID（人工補發/扣除/匯入）' AFTER order_id"
            );
        }

        $index_exists = $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM `{$safe_points_table}` WHERE Key_name = %s", 'idx_admin_user_id'));

        if (!$index_exists) {
            $wpdb->query("ALTER TABLE `{$safe_points_table}` ADD INDEX idx_admin_user_id (admin_user_id)");
        }

        self::$points_table_supports_admin_user_id = true;
    }

    /**
     * 檢查目前點數表是否支援 admin_user_id 欄位。
     */
    private function points_table_supports_admin_user_id() {
        global $wpdb;

        if (null !== self::$points_table_supports_admin_user_id) {
            return self::$points_table_supports_admin_user_id;
        }

        $table_name = preg_replace('/[^A-Za-z0-9_]/', '', $wpdb->prefix . 'wc_points_rewards_points');

        if (empty($table_name)) {
            self::$points_table_supports_admin_user_id = false;
            return false;
        }

        $column_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table_name}` LIKE %s", 'admin_user_id'));

        self::$points_table_supports_admin_user_id = ('admin_user_id' === $column_exists);

        return self::$points_table_supports_admin_user_id;
    }
}
