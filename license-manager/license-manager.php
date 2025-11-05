<?php
/**
 * Plugin Name: ML License Manager
 * Plugin URI: https://github.com/millenniumlin/woocommerce-points-rewards
 * Description: 授權碼管理系統，用於管理和驗證產品授權
 * Version: 1.0.0
 * Author: millenniumlim
 * License: GPL v2 or later
 * Text Domain: ml-license-manager
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8.2
 * Requires PHP: 8.0
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

// 定義常數
define('ML_LICENSE_MANAGER_VERSION', '1.0.0');
define('ML_LICENSE_MANAGER_PLUGIN_FILE', __FILE__);
define('ML_LICENSE_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ML_LICENSE_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 主要外掛類別
 */
class ML_License_Manager {
    
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
    }
    
    /**
     * 包含必要檔案
     */
    private function includes() {
        // 安裝類別
        if (file_exists(ML_LICENSE_MANAGER_PLUGIN_DIR . 'includes/class-ml-license-manager-install.php')) {
            require_once ML_LICENSE_MANAGER_PLUGIN_DIR . 'includes/class-ml-license-manager-install.php';
        }
        
        // 授權碼類別
        if (file_exists(ML_LICENSE_MANAGER_PLUGIN_DIR . 'includes/class-ml-license-key.php')) {
            require_once ML_LICENSE_MANAGER_PLUGIN_DIR . 'includes/class-ml-license-key.php';
        }
        
        // API類別
        if (file_exists(ML_LICENSE_MANAGER_PLUGIN_DIR . 'includes/class-ml-license-manager-api.php')) {
            require_once ML_LICENSE_MANAGER_PLUGIN_DIR . 'includes/class-ml-license-manager-api.php';
        }
        
        // 管理介面
        if (is_admin()) {
            if (file_exists(ML_LICENSE_MANAGER_PLUGIN_DIR . 'includes/admin/class-ml-license-manager-admin-menus.php')) {
                require_once ML_LICENSE_MANAGER_PLUGIN_DIR . 'includes/admin/class-ml-license-manager-admin-menus.php';
            }
        }
    }
    
    /**
     * 初始化類別實例
     */
    private function init_classes() {
        // 核心類別
        if (class_exists('ML_License_Key')) {
            ML_License_Key::instance();
        }
        
        if (class_exists('ML_License_Manager_API')) {
            ML_License_Manager_API::instance();
        }
        
        // 管理介面
        if (is_admin()) {
            if (class_exists('ML_License_Manager_Admin_Menus')) {
                ML_License_Manager_Admin_Menus::instance();
            }
        }
    }
    
    /**
     * 外掛啟用時執行
     */
    public function activate() {
        // 檢查類別存在後再執行
        if (class_exists('ML_License_Manager_Install')) {
            ML_License_Manager_Install::install();
        }
        
        // 設定版本號
        update_option('ml_license_manager_version', ML_LICENSE_MANAGER_VERSION);
        
        // 清除快取
        wp_cache_flush();
    }
    
    /**
     * 外掛停用時執行
     */
    public function deactivate() {
        // 清除排程任務
        wp_clear_scheduled_hook('ml_license_manager_daily_cleanup');
        
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
        load_plugin_textdomain('ml-license-manager', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * 檢查版本更新
     */
    private function check_version() {
        $current_version = get_option('ml_license_manager_version');
        if ($current_version !== ML_LICENSE_MANAGER_VERSION) {
            // 執行更新程序
            if (class_exists('ML_License_Manager_Install')) {
                ML_License_Manager_Install::update_database();
            }
            
            update_option('ml_license_manager_version', ML_LICENSE_MANAGER_VERSION);
        }
    }
}

// 初始化外掛
add_action('plugins_loaded', function() {
    ML_License_Manager::instance();
});

/**
 * 獲取外掛主實例的便利函式
 */
function ml_license_manager() {
    return ML_License_Manager::instance();
}
