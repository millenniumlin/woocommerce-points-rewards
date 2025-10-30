<?php
/**
 * Plugin Name: WooCommerce Points & Rewards 會員系統
 * Plugin URI: https://github.com/millenniumlin/woocommerce-points-rewards
 * Description: 完整的 WooCommerce 累積消費點數獎勵系統，支援會員等級、點數回饋、折抵功能等。
 * Version: 1.4.7
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

// 檢查 WooCommerce 是否啟用
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
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
define('WC_POINTS_REWARDS_VERSION', '1.4.7');  // 修正：與標題版本一致
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
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/class-data-manager.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/class-data-manager.php';
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
            if (class_exists('WC_Points_Rewards_Data_Manager')) {
                WC_Points_Rewards_Data_Manager::instance();
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
        // 檢查類別存在後再執行
        if (class_exists('WC_Points_Rewards_Database')) {
            // 創建資料庫表格
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
        // 清除排程任務
        wp_clear_scheduled_hook('wc_points_rewards_daily_cleanup');
        wp_clear_scheduled_hook('wc_points_rewards_notification_check');
        wp_clear_scheduled_hook('wc_points_rewards_daily_birthday_check');
        
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
            // 刪除資料庫表格
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
        if (get_option('wc_points_rewards_settings')) {
            return; // 已經有設定，不要覆蓋
        }
        
        $default_settings = array(
            // 點數系統啟用設定
            'enable_points_system' => 'yes',
            
            // 前台顯示控制設定
            'show_in_menu' => 'no',  // 預設不在選單顯示
            
            // 原有設定
            'points_per_amount' => 1, // 每1元回饋1點
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
            
            // 點數名稱設定
            'points_name' => '點',
            'points_value' => 1  // 1點 = 1元
        );
        
        add_option('wc_points_rewards_settings', $default_settings);
        add_option('wc_points_rewards_version', WC_POINTS_REWARDS_VERSION);
    }
    
    /**
     * 創建預設會員等級
     */
    private function create_default_tiers() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        // 檢查表格是否存在
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            return; // 表格不存在，跳過
        }
        
        // 檢查是否已經有資料，避免重複創建
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($existing_count > 0) {
            return; // 已經有資料，不需要重複創建
        }
        
        $default_tiers = array(
            array(
                'name' => '微光會員',
                'min_amount' => 5000,
                'bonus_percentage' => 10,
                'tier_order' => 1
            ),
            array(
                'name' => '曙光會員',
                'min_amount' => 10000,
                'bonus_percentage' => 20,
                'tier_order' => 2
            ),
            array(
                'name' => '熾光會員',
                'min_amount' => 20000,
                'bonus_percentage' => 30,
                'tier_order' => 3
            )
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
            // 執行更新程序
            $this->update_database();
            
            // 版本更新時也重新整理重寫規則
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
        
        // 新增：每日生日點數檢查
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
