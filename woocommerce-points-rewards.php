<?php
/**
 * Plugin Name: WooCommerce Points & Rewards 會員系統
 * Plugin URI: https://github.com/millenniumlin/woocommerce-points-rewards
 * Description: 完整的 WooCommerce 累積消費點數獎勵系統，支援會員等級、點數回饋、折抵功能等。
 * Version: 1.7.0
 * Author: Github Copilot x millenniumlim
 * License: GPL v2 or later
 * Text Domain: wc-points-rewards
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8.2
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 10.0.4
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

// [修正 SEC-1] 檢查 WooCommerce 是否啟用（支援一般安裝及多站點網路啟用）
// 原先只用 apply_filters('active_plugins') 無法涵蓋 Multisite network-activated 的情況
function wc_points_rewards_is_woocommerce_active() {
    $active_plugins = (array) get_option('active_plugins', array());
    if (is_multisite()) {
        $active_plugins = array_merge(
            $active_plugins,
            array_keys((array) get_site_option('active_sitewide_plugins', array()))
        );
    }
    return in_array('woocommerce/woocommerce.php', $active_plugins, true) || class_exists('WooCommerce');
}

if (!wc_points_rewards_is_woocommerce_active()) {
    return;
}

// 宣告 WooCommerce 高效能訂單儲存（HPOS）相容性
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

// 定義常數 - 修正版本號一致性
define('WC_POINTS_REWARDS_VERSION', '1.7.0');
define('WC_POINTS_REWARDS_PLUGIN_FILE', __FILE__);
define('WC_POINTS_REWARDS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_POINTS_REWARDS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 主要外掛類別
 */
class WC_Points_Rewards {
    
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
        $this->includes();
        $this->init_classes();
    }
    
    /**
     * 初始化 WordPress hooks
     */
    private function init_hooks() {
        // 外掛啟用時創建資料庫表格
        register_activation_hook(__FILE__, array($this, 'activate'));
        // 外掛停用時的清理工作
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        // 外掛卸載時刪除資料
        register_uninstall_hook(__FILE__, array('WC_Points_Rewards', 'uninstall'));
        
        // 初始化
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * 包含必要檔案
     */
    private function includes() {
        // 首先載入輔助函數
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/functions.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/functions.php';
        }
        
        // 核心類別
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-database.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-database.php';
        }
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-points-calculator.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-points-calculator.php';
        }
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-member-tier.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-member-tier.php';
        }
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-notifications.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-notifications.php';
        }
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-security.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-security.php';
        }
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-ajax-handler.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        }
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-admin-points-manager.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/class-admin-points-manager.php';
        }
        
        // 管理介面
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/class-admin.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/class-admin.php';
        }
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/class-settings.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/class-settings.php';
        }
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/class-reports.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/class-reports.php';
        }
        
        // 前端功能
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/class-frontend.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/class-frontend.php';
        }
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/class-checkout.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/class-checkout.php';
        }
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/class-account.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/class-account.php';
        }
    }
    
    /**
     * 初始化類別實例
     */
    private function init_classes() {
        // 核心類別 - 檢查類別存在後再初始化
        if (class_exists('WC_Points_Rewards_Database')) {
            WC_Points_Rewards_Database::instance();
        }
        if (class_exists('WC_Points_Rewards_Points_Calculator')) {
            WC_Points_Rewards_Points_Calculator::instance();
        }
        if (class_exists('WC_Points_Rewards_Member_Tier')) {
            WC_Points_Rewards_Member_Tier::instance();
        }
        if (class_exists('WC_Points_Rewards_Notifications')) {
            WC_Points_Rewards_Notifications::instance();
        }
        if (class_exists('WC_Points_Rewards_Security')) {
            WC_Points_Rewards_Security::instance();
        }
        if (class_exists('WC_Points_Rewards_Ajax_Handler')) {
            WC_Points_Rewards_Ajax_Handler::instance();
        }
        
        // 管理介面
        if (is_admin()) {
            if (class_exists('WC_Points_Rewards_Admin')) {
                WC_Points_Rewards_Admin::instance();
            }
            if (class_exists('WC_Points_Rewards_Settings')) {
                WC_Points_Rewards_Settings::instance();
            }
            if (class_exists('WC_Points_Rewards_Reports')) {
                WC_Points_Rewards_Reports::instance();
            }
        }
        
        // 前端功能（包括 AJAX 請求）
        if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            if (class_exists('WC_Points_Rewards_Frontend')) {
                WC_Points_Rewards_Frontend::instance();
            }
            if (class_exists('WC_Points_Rewards_Checkout')) {
                WC_Points_Rewards_Checkout::instance();
            }
            if (class_exists('WC_Points_Rewards_Account')) {
                WC_Points_Rewards_Account::instance();
            }
        }
    }
    
    /**
     * 外掛啟用時執行
     */
    public function activate() {
        // 確保 WooCommerce 已啟用
        if (!wc_points_rewards_is_woocommerce_active()) {
            wp_die(
                esc_html__('WooCommerce Points & Rewards 需要先安裝並啟用 WooCommerce。', 'wc-points-rewards'),
                esc_html__('啟用失敗', 'wc-points-rewards'),
                array('back_link' => true)
            );
        }

        // 檢查類別存在後再執行
        if (class_exists('WC_Points_Rewards_Database')) {
            WC_Points_Rewards_Database::create_tables();
        }
        
        // 設定預設設定值
        $this->set_default_settings();
        
        // 建立預設會員等級
        $this->create_default_tiers();
        
        // 設定重新整理重寫規則的標記
        update_option('wc_points_rewards_flush_rewrite_rules', 'yes');
        delete_option('wc_points_rewards_endpoints_flushed');
        
        // 立即重新整理一次重寫規則
        flush_rewrite_rules(false);
        
        // 清除快取
        wp_cache_flush();
    }
    
    /**
     * 外掛停用時執行
     */
    public function deactivate() {
        // [修正 BUG-1] 清除所有排程任務（含 birthday_check 及 weekly_report）
        // 原先只清除 daily_cleanup 和 notification_check，birthday_check 不會被清除
        wp_clear_scheduled_hook('wc_points_rewards_daily_cleanup');
        wp_clear_scheduled_hook('wc_points_rewards_notification_check');
        wp_clear_scheduled_hook('wc_points_rewards_daily_birthday_check');
        wp_clear_scheduled_hook('wc_points_rewards_weekly_report');
        
        // 停用時也重新整理重寫規則，移除我們的端點
        flush_rewrite_rules(false);
        
        // 清除快取
        wp_cache_flush();
    }
    
    /**
     * 外掛卸載時執行
     */
    public static function uninstall() {
        // 檢查類別存在後再執行
        if (class_exists('WC_Points_Rewards_Database')) {
            WC_Points_Rewards_Database::drop_tables();
        }
        
        // 刪除選項設定
        delete_option('wc_points_rewards_settings');
        delete_option('wc_points_rewards_version');
        
        // 刪除重寫規則相關選項
        delete_option('wc_points_rewards_flush_rewrite_rules');
        delete_option('wc_points_rewards_endpoints_flushed');
        
        // 最後一次重新整理重寫規則
        flush_rewrite_rules(false);
        
        // 清除快取
        wp_cache_flush();
    }
    
    /**
     * 初始化
     */
    public function init() {
        // 檢查版本更新
        $this->check_version();
        $this->ensure_runtime_default_options();
        
        // 設定排程任務
        $this->schedule_events();
    }
    
    /**
     * 載入翻譯檔案
     */
    public function load_textdomain() {
        load_plugin_textdomain('wc-points-rewards', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * 設定預設設定值
     */
    private function set_default_settings() {
        // 檢查是否已經有設定
        if (!get_option('wc_points_rewards_settings')) {
            $default_settings = array(
                'enable_points_system' => 'yes',
                'show_in_menu' => 'no',
                'points_per_amount' => 1,
                'points_amount' => 1,
                'registration_points' => 100,
                'birthday_points' => 200,
                'points_expiry_months' => 12,
                'min_cart_total' => 0,
                'max_discount_percent' => 50,
                'enable_cart_redemption' => 'yes',
                'notification_days' => 30,
                'enable_notifications' => 'yes',
                'enable_birthday_points' => 'yes',
                'enable_registration_points' => 'yes',
                'points_name' => '點',
                'points_value' => 1
            );
            
            add_option('wc_points_rewards_settings', $default_settings);
        }

        foreach ($this->get_runtime_default_options() as $option_name => $option_value) {
            add_option($option_name, $option_value);
        }
        add_option('wc_points_rewards_version', WC_POINTS_REWARDS_VERSION);
    }
    
    /**
     * 創建預設會員等級
     */
    private function create_default_tiers() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        // [修正 SEC-2] 使用 prepare 防止 SQL 注入（SHOW TABLES LIKE 需要 prepare）
        $table_exists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table_name)
        ) === $table_name;

        if (!$table_exists) {
            return;
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is already sanitized via prefix
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`");
        if ($existing_count > 0) {
            return;
        }
        
        $default_tiers = array(
            array('name' => '微光會員', 'min_amount' => 5000,  'bonus_percentage' => 10, 'tier_order' => 1),
            array('name' => '曙光會員', 'min_amount' => 10000, 'bonus_percentage' => 20, 'tier_order' => 2),
            array('name' => '熾光會員', 'min_amount' => 20000, 'bonus_percentage' => 30, 'tier_order' => 3),
        );
        
        foreach ($default_tiers as $tier) {
            $wpdb->insert($table_name, $tier);
        }
    }
    
    /**
     * 檢查版本更新
     */
    private function check_version() {
        $current_version = get_option('wc_points_rewards_version');
        if ($current_version !== WC_POINTS_REWARDS_VERSION) {
            $this->update_database();
            update_option('wc_points_rewards_flush_rewrite_rules', 'yes');
            update_option('wc_points_rewards_version', WC_POINTS_REWARDS_VERSION);
        }
    }
    
    /**
     * 更新資料庫
     */
    private function update_database() {
        if (class_exists('WC_Points_Rewards_Database')) {
            WC_Points_Rewards_Database::create_tables();
        }
        $this->ensure_runtime_default_options();
    }

    /**
     * 確保新增的個別 option 在舊站升級時也會建立。
     */
    private function ensure_runtime_default_options() {
        foreach ($this->get_runtime_default_options() as $option_name => $option_value) {
            add_option($option_name, $option_value);
        }
    }

    /**
     * 取得需確保存在的個別 option 預設值。
     *
     * @return array<string,mixed>
     */
    private function get_runtime_default_options() {
        return array(
            'wc_points_rewards_enable_manual_admin_points' => 'yes',
            'wc_points_rewards_manual_admin_points_per_grant_max' => 1000,
            'wc_points_rewards_manual_admin_points_per_admin_daily_max' => 1000,
            'wc_points_rewards_manual_admin_points_site_daily_max' => 3000,
        );
    }
    
    /**
     * 設定排程任務
     */
    private function schedule_events() {
        if (!wp_next_scheduled('wc_points_rewards_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wc_points_rewards_daily_cleanup');
        }
        
        if (!wp_next_scheduled('wc_points_rewards_notification_check')) {
            wp_schedule_event(time(), 'daily', 'wc_points_rewards_notification_check');
        }
        
        // 每日生日點數檢查
        if (!wp_next_scheduled('wc_points_rewards_daily_birthday_check')) {
            wp_schedule_event(time(), 'daily', 'wc_points_rewards_daily_birthday_check');
        }
    }
}

// 初始化外掛
add_action('plugins_loaded', function() {
    WC_Points_Rewards::instance();
});

/**
 * 獲取外掛主實例的便利函式
 */
function wc_points_rewards() {
    return WC_Points_Rewards::instance();
}
