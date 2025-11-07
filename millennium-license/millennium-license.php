<?php
/**
 * Plugin Name: Millennium License Manager
 * Plugin URI: https://github.com/millenniumlin/millennium-license
 * Description: 整合的 WordPress 授權系統外掛，包含 WooCommerce 整合功能，支援授權碼生成、管理、驗證和 API。
 * Version: 1.0.0
 * Author: millenniumlim
 * License: GPL v2 or later
 * Text Domain: millennium-license
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8.3
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 10.3.4
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

// 檢查 WooCommerce 是否啟用
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>' . esc_html__('Millennium License Manager 需要 WooCommerce 外掛才能運作。', 'millennium-license') . '</p></div>';
    });
    return;
}

// 定義常數
define('MILLENNIUM_LICENSE_VERSION', '1.0.0');
define('MILLENNIUM_LICENSE_PLUGIN_FILE', __FILE__);
define('MILLENNIUM_LICENSE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MILLENNIUM_LICENSE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 主要外掛類別
 */
class Millennium_License_Manager {
    
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
        
        // 初始化
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // 宣告 HPOS 相容性
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
    }
    
    /**
     * 宣告 HPOS 相容性
     */
    public function declare_hpos_compatibility() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
    
    /**
     * 包含必要檔案
     */
    private function includes() {
        // 核心類別
        require_once MILLENNIUM_LICENSE_PLUGIN_DIR . 'includes/class-license-install.php';
        require_once MILLENNIUM_LICENSE_PLUGIN_DIR . 'includes/class-license-key.php';
        require_once MILLENNIUM_LICENSE_PLUGIN_DIR . 'includes/class-license-manager.php';
        
        // 管理介面
        if (is_admin()) {
            require_once MILLENNIUM_LICENSE_PLUGIN_DIR . 'includes/admin/class-license-admin.php';
            require_once MILLENNIUM_LICENSE_PLUGIN_DIR . 'includes/admin/class-license-list-table.php';
        }
        
        // API
        require_once MILLENNIUM_LICENSE_PLUGIN_DIR . 'includes/api/class-license-api-auth.php';
        require_once MILLENNIUM_LICENSE_PLUGIN_DIR . 'includes/api/class-license-api.php';
        
        // WooCommerce 整合
        require_once MILLENNIUM_LICENSE_PLUGIN_DIR . 'includes/woocommerce/class-license-product.php';
        require_once MILLENNIUM_LICENSE_PLUGIN_DIR . 'includes/woocommerce/class-license-order.php';
    }
    
    /**
     * 初始化類別實例
     */
    private function init_classes() {
        // 核心類別
        Millennium_License_Manager_Core::instance();
        
        // 管理介面
        if (is_admin()) {
            Millennium_License_Admin::instance();
        }
        
        // API
        Millennium_License_API_Auth::instance();
        Millennium_License_API::instance();
        
        // WooCommerce 整合
        Millennium_License_Product::instance();
        Millennium_License_Order::instance();
    }
    
    /**
     * 外掛啟用時執行
     */
    public function activate() {
        // 創建資料庫表格
        Millennium_License_Install::create_tables();
        
        // 設定預設設定值
        $this->set_default_settings();
        
        // 清除快取
        wp_cache_flush();
    }
    
    /**
     * 外掛停用時執行
     */
    public function deactivate() {
        // 清除快取
        wp_cache_flush();
    }
    
    /**
     * 初始化
     */
    public function init() {
        // 檢查版本更新
        $this->check_version();
    }
    
    /**
     * 載入翻譯檔案
     */
    public function load_textdomain() {
        load_plugin_textdomain('millennium-license', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * 設定預設設定值
     */
    private function set_default_settings() {
        $default_settings = array(
            'license_key_format' => 'XXXX-XXXX-XXXX-XXXX',
            'default_expiry_days' => 365,
            'enable_api' => 'yes',
            'enable_email_notifications' => 'yes',
            'max_activations' => 1,
        );
        
        add_option('millennium_license_settings', $default_settings);
        add_option('millennium_license_version', MILLENNIUM_LICENSE_VERSION);
    }
    
    /**
     * 檢查版本更新
     */
    private function check_version() {
        $current_version = get_option('millennium_license_version');
        if ($current_version !== MILLENNIUM_LICENSE_VERSION) {
            // 執行更新程序
            Millennium_License_Install::create_tables();
            update_option('millennium_license_version', MILLENNIUM_LICENSE_VERSION);
        }
    }
}

// 初始化外掛
add_action('plugins_loaded', function() {
    Millennium_License_Manager::instance();
});

/**
 * 獲取外掛主實例的便利函式
 */
function millennium_license() {
    return Millennium_License_Manager::instance();
}
