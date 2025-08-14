<?php
/**
 * 外掛安裝腳本
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 外掛安裝類別
 */
class WC_Points_Rewards_Install {
    
    /**
     * 執行安裝
     */
    public static function install() {
        // 檢查系統需求
        self::check_requirements();
        
        // 創建資料庫表格
        self::create_tables();
        
        // 設定預設選項
        self::set_default_options();
        
        // 創建預設會員等級
        self::create_default_tiers();
        
        // 設定排程任務
        self::schedule_events();
        
        // 設定重寫規則
        self::setup_rewrite_rules();
        
        // 記錄安裝時間
        update_option('wc_points_rewards_installed_time', current_time('mysql'));
        update_option('wc_points_rewards_version', WC_POINTS_REWARDS_VERSION);
        
        // 清除快取
        wp_cache_flush();
    }
    
    /**
     * 檢查系統需求
     */
    private static function check_requirements() {
        // 檢查 PHP 版本
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            wp_die(__('WooCommerce Points & Rewards 需要 PHP 8.0 或更高版本。', 'wc-points-rewards'));
        }
        
        // 檢查 WordPress 版本
        if (version_compare(get_bloginfo('version'), '6.0', '<')) {
            wp_die(__('WooCommerce Points & Rewards 需要 WordPress 6.0 或更高版本。', 'wc-points-rewards'));
        }
        
        // 檢查 WooCommerce
        if (!class_exists('WooCommerce')) {
            wp_die(__('WooCommerce Points & Rewards 需要先安裝 WooCommerce 外掛。', 'wc-points-rewards'));
        }
        
        // 檢查資料庫權限
        global $wpdb;
        $result = $wpdb->query("SHOW GRANTS");
        if (!$result) {
            wp_die(__('資料庫權限不足，無法創建表格。', 'wc-points-rewards'));
        }
    }
    
    /**
     * 創建資料庫表格
     */
    private static function create_tables() {
        require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-database.php';
        WC_Points_Rewards_Database::create_tables();
    }
    
    /**
     * 設定預設選項
     */
    private static function set_default_options() {
        $default_settings = array(
            // 基本設定
            'enable_points_system' => 'yes',
            'points_name' => '點',
            'decimal_places' => 0,
            
            // 點數獲得設定
            'points_per_amount' => 100,
            'points_amount' => 1,
            'enable_registration_points' => 'yes',
            'registration_points' => 100,
            'enable_birthday_points' => 'yes',
            'birthday_points' => 200,
            'points_expiry_months' => 12,
            
            // 點數使用設定
            'enable_cart_redemption' => 'yes',
            'points_value' => 1,
            'min_cart_total' => 0,
            'max_discount_percent' => 100,
            'min_points_redemption' => 1,
            
            // 通知設定
            'enable_notifications' => 'yes',
            'notification_days' => 30,
            'email_points_earned' => 'yes',
            'email_tier_upgrade' => 'yes',
            'email_points_expiry' => 'yes',
            
            // 進階設定
            'enable_debug_mode' => 'no',
            'auto_cleanup_days' => 365,
            'cache_duration' => 3600,
            'show_points_in_menu' => 'yes'
        );
        
        add_option('wc_points_rewards_settings', $default_settings);
    }
    
    /**
     * 創建預設會員等級
     */
    private static function create_default_tiers() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        // 檢查是否已有等級
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($existing_count > 0) {
            return;
        }
        
        $default_tiers = array(
            array(
                'name' => '一般會員',
                'min_amount' => 0,
                'bonus_percentage' => 0,
                'tier_order' => 1
            ),
            array(
                'name' => '銅牌會員',
                'min_amount' => 5000,
                'bonus_percentage' => 10,
                'tier_order' => 2
            ),
            array(
                'name' => '銀牌會員',
                'min_amount' => 10000,
                'bonus_percentage' => 20,
                'tier_order' => 3
            ),
            array(
                'name' => '金牌會員',
                'min_amount' => 20000,
                'bonus_percentage' => 30,
                'tier_order' => 4
            ),
            array(
                'name' => '鑽石會員',
                'min_amount' => 50000,
                'bonus_percentage' => 50,
                'tier_order' => 5
            )
        );
        
        foreach ($default_tiers as $tier) {
            $wpdb->insert($table_name, $tier);
        }
    }
    
    /**
     * 設定排程任務
     */
    private static function schedule_events() {
        // 每日清理任務
        if (!wp_next_scheduled('wc_points_rewards_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wc_points_rewards_daily_cleanup');
        }
        
        // 每日通知檢查
        if (!wp_next_scheduled('wc_points_rewards_notification_check')) {
            wp_schedule_event(time(), 'daily', 'wc_points_rewards_notification_check');
        }
        
        // 每週報表生成
        if (!wp_next_scheduled('wc_points_rewards_weekly_report')) {
            wp_schedule_event(time(), 'weekly', 'wc_points_rewards_weekly_report');
        }
    }
    
    /**
     * 🚀 修正：設定重寫規則（兼容所有永久連結結構）
     */
    private static function setup_rewrite_rules() {
        // 添加我的帳戶端點 - 使用標準 WordPress 方式
        add_rewrite_endpoint('points-rewards', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('points-history', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('member-tier', EP_ROOT | EP_PAGES);
        
        // 確保 WooCommerce 查詢變數包含我們的端點
        if (class_exists('WC_Query')) {
            $wc_query = new WC_Query();
            if (isset($wc_query->query_vars)) {
                $wc_query->query_vars['points-rewards'] = 'points-rewards';
                $wc_query->query_vars['points-history'] = 'points-history';
                $wc_query->query_vars['member-tier'] = 'member-tier';
            }
        }
        
        // 重新整理重寫規則
        flush_rewrite_rules(false);
    }
    
    /**
     * 資料庫升級
     */
    public static function upgrade($old_version, $new_version) {
        global $wpdb;
        
        // 升級邏輯
        if (version_compare($old_version, '1.0.0', '<')) {
            // 從舊版本升級的邏輯
            self::upgrade_to_100();
        }
        
        // 更新版本號
        update_option('wc_points_rewards_version', $new_version);
    }
    
    /**
     * 升級到 1.0.0
     */
    private static function upgrade_to_100() {
        // 這裡可以放置從舊版本升級的邏輯
        // 例如：資料庫結構更改、數據遷移等
        
        // 重新創建表格（會檢查並更新結構）
        self::create_tables();
        
        // 更新設定結構
        $old_settings = get_option('wc_points_rewards_settings', array());
        $new_settings = wp_parse_args($old_settings, self::get_default_settings());
        update_option('wc_points_rewards_settings', $new_settings);
    }
    
    /**
     * 獲取預設設定
     */
    private static function get_default_settings() {
        return array(
            'enable_points_system' => 'yes',
            'points_name' => '點',
            'decimal_places' => 0,
            'points_per_amount' => 100,
            'points_amount' => 1,
            'enable_registration_points' => 'yes',
            'registration_points' => 100,
            'enable_birthday_points' => 'yes',
            'birthday_points' => 200,
            'points_expiry_months' => 12,
            'enable_cart_redemption' => 'yes',
            'points_value' => 1,
            'min_cart_total' => 0,
            'max_discount_percent' => 100,
            'min_points_redemption' => 1,
            'enable_notifications' => 'yes',
            'notification_days' => 30,
            'email_points_earned' => 'yes',
            'email_tier_upgrade' => 'yes',
            'email_points_expiry' => 'yes',
            'enable_debug_mode' => 'no',
            'auto_cleanup_days' => 365,
            'cache_duration' => 3600,
            'show_points_in_menu' => 'yes'
        );
    }
    
    /**
     * 創建範例數據（僅供測試環境使用）
     */
    public static function create_sample_data() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        global $wpdb;
        
        // 創建測試用戶
        $test_users = array(
            array(
                'user_login' => 'test_user_1',
                'user_email' => 'test1@example.com',
                'display_name' => '測試用戶一',
                'user_pass' => 'password123'
            ),
            array(
                'user_login' => 'test_user_2',
                'user_email' => 'test2@example.com',
                'display_name' => '測試用戶二',
                'user_pass' => 'password123'
            )
        );
        
        foreach ($test_users as $user_data) {
            $user_id = wp_insert_user($user_data);
            
            if (!is_wp_error($user_id)) {
                // 為測試用戶添加點數記錄
                $database = WC_Points_Rewards_Database::instance();
                
                $database->add_points(
                    $user_id,
                    500,
                    'earned',
                    '測試數據 - 註冊贈送',
                    null,
                    date('Y-m-d H:i:s', strtotime('+1 year'))
                );
                
                $database->add_points(
                    $user_id,
                    300,
                    'earned',
                    '測試數據 - 購物獲得',
                    null,
                    date('Y-m-d H:i:s', strtotime('+1 year'))
                );
                
                $database->add_points(
                    $user_id,
                    -100,
                    'redeemed',
                    '測試數據 - 購物使用'
                );
                
                // 設定年度消費統計
                $database->update_user_yearly_stats($user_id, 8000);
            }
        }
    }
}