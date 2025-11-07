<?php
/**
 * License Admin Interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class Millennium_License_Admin {
    
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'handle_actions'));
    }
    
    /**
     * 添加管理選單
     */
    public function add_admin_menu() {
        add_menu_page(
            __('授權管理', 'millennium-license'),
            __('授權管理', 'millennium-license'),
            'manage_options',
            'millennium-license',
            array($this, 'render_licenses_page'),
            'dashicons-admin-network',
            56
        );
        
        add_submenu_page(
            'millennium-license',
            __('所有授權碼', 'millennium-license'),
            __('所有授權碼', 'millennium-license'),
            'manage_options',
            'millennium-license',
            array($this, 'render_licenses_page')
        );
        
        add_submenu_page(
            'millennium-license',
            __('新增授權碼', 'millennium-license'),
            __('新增授權碼', 'millennium-license'),
            'manage_options',
            'millennium-license-new',
            array($this, 'render_new_license_page')
        );
        
        add_submenu_page(
            'millennium-license',
            __('設定', 'millennium-license'),
            __('設定', 'millennium-license'),
            'manage_options',
            'millennium-license-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * 載入腳本和樣式
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'millennium-license') === false) {
            return;
        }
        
        wp_enqueue_style(
            'millennium-license-admin',
            MILLENNIUM_LICENSE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MILLENNIUM_LICENSE_VERSION
        );
        
        wp_enqueue_script(
            'millennium-license-admin',
            MILLENNIUM_LICENSE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MILLENNIUM_LICENSE_VERSION,
            true
        );
        
        wp_localize_script('millennium-license-admin', 'millenniumLicense', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('millennium-license-admin'),
        ));
    }
    
    /**
     * 處理管理動作
     */
    public function handle_actions() {
        if (!isset($_GET['page']) || strpos($_GET['page'], 'millennium-license') === false) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // 處理刪除
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['license_id'])) {
            check_admin_referer('delete-license-' . $_GET['license_id']);
            
            $license_id = intval($_GET['license_id']);
            $manager = Millennium_License_Manager_Core::instance();
            
            if ($manager->delete_license($license_id)) {
                wp_safe_redirect(add_query_arg(array(
                    'page' => 'millennium-license',
                    'message' => 'deleted'
                ), admin_url('admin.php')));
                exit;
            }
        }
        
        // 處理狀態更新
        if (isset($_GET['action']) && in_array($_GET['action'], array('activate', 'deactivate')) && isset($_GET['license_id'])) {
            check_admin_referer('update-license-' . $_GET['license_id']);
            
            $license_id = intval($_GET['license_id']);
            $status = $_GET['action'] === 'activate' ? 'active' : 'inactive';
            $manager = Millennium_License_Manager_Core::instance();
            
            if ($manager->update_license($license_id, array('status' => $status))) {
                wp_safe_redirect(add_query_arg(array(
                    'page' => 'millennium-license',
                    'message' => 'updated'
                ), admin_url('admin.php')));
                exit;
            }
        }
        
        // 處理新增授權碼
        if (isset($_POST['create_license']) && check_admin_referer('create-license')) {
            $manager = Millennium_License_Manager_Core::instance();
            
            $args = array(
                'product_id' => isset($_POST['product_id']) ? intval($_POST['product_id']) : null,
                'user_id' => isset($_POST['user_id']) ? intval($_POST['user_id']) : null,
                'max_activations' => isset($_POST['max_activations']) ? intval($_POST['max_activations']) : 1,
                'expires_at' => isset($_POST['expires_at']) ? sanitize_text_field($_POST['expires_at']) : null,
            );
            
            if ($manager->create_license($args)) {
                wp_safe_redirect(add_query_arg(array(
                    'page' => 'millennium-license',
                    'message' => 'created'
                ), admin_url('admin.php')));
                exit;
            }
        }
        
        // 處理設定保存
        if (isset($_POST['save_settings']) && check_admin_referer('millennium-license-settings')) {
            $settings = array(
                'license_key_format' => sanitize_text_field($_POST['license_key_format']),
                'default_expiry_days' => intval($_POST['default_expiry_days']),
                'enable_api' => isset($_POST['enable_api']) ? 'yes' : 'no',
                'enable_email_notifications' => isset($_POST['enable_email_notifications']) ? 'yes' : 'no',
                'max_activations' => intval($_POST['max_activations']),
            );
            
            update_option('millennium_license_settings', $settings);
            
            wp_safe_redirect(add_query_arg(array(
                'page' => 'millennium-license-settings',
                'message' => 'saved'
            ), admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * 顯示授權碼列表頁面
     */
    public function render_licenses_page() {
        include MILLENNIUM_LICENSE_PLUGIN_DIR . 'templates/admin/licenses.php';
    }
    
    /**
     * 顯示新增授權碼頁面
     */
    public function render_new_license_page() {
        include MILLENNIUM_LICENSE_PLUGIN_DIR . 'templates/admin/new-license.php';
    }
    
    /**
     * 顯示設定頁面
     */
    public function render_settings_page() {
        include MILLENNIUM_LICENSE_PLUGIN_DIR . 'templates/admin/settings.php';
    }
}
