<?php
/**
 * 管理介面選單類別
 * 
 * @package ML_License_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 管理介面選單類別
 */
class ML_License_Manager_Admin_Menus {
    
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
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * 添加選單頁面
     */
    public function add_menu_pages() {
        // 主選單
        add_menu_page(
            __('授權管理', 'ml-license-manager'),
            __('授權管理', 'ml-license-manager'),
            'manage_options',
            'ml-license-manager',
            array($this, 'render_dashboard_page'),
            'dashicons-admin-network',
            56
        );
        
        // 儀表板子選單
        add_submenu_page(
            'ml-license-manager',
            __('儀表板', 'ml-license-manager'),
            __('儀表板', 'ml-license-manager'),
            'manage_options',
            'ml-license-manager',
            array($this, 'render_dashboard_page')
        );
        
        // 授權碼列表
        add_submenu_page(
            'ml-license-manager',
            __('授權碼', 'ml-license-manager'),
            __('授權碼', 'ml-license-manager'),
            'manage_options',
            'ml-license-keys',
            array($this, 'render_licenses_page')
        );
        
        // 新增授權碼
        add_submenu_page(
            'ml-license-manager',
            __('新增授權碼', 'ml-license-manager'),
            __('新增授權碼', 'ml-license-manager'),
            'manage_options',
            'ml-license-add',
            array($this, 'render_add_license_page')
        );
        
        // 啟用記錄
        add_submenu_page(
            'ml-license-manager',
            __('啟用記錄', 'ml-license-manager'),
            __('啟用記錄', 'ml-license-manager'),
            'manage_options',
            'ml-license-activations',
            array($this, 'render_activations_page')
        );
        
        // 設定
        add_submenu_page(
            'ml-license-manager',
            __('設定', 'ml-license-manager'),
            __('設定', 'ml-license-manager'),
            'manage_options',
            'ml-license-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * 載入管理介面資源
     */
    public function enqueue_admin_assets($hook) {
        // 只在授權管理頁面載入
        if (strpos($hook, 'ml-license') === false) {
            return;
        }
        
        // CSS
        if (file_exists(ML_LICENSE_MANAGER_PLUGIN_DIR . 'assets/css/admin.css')) {
            wp_enqueue_style(
                'ml-license-manager-admin',
                ML_LICENSE_MANAGER_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                ML_LICENSE_MANAGER_VERSION
            );
        }
        
        // JavaScript
        if (file_exists(ML_LICENSE_MANAGER_PLUGIN_DIR . 'assets/js/admin.js')) {
            wp_enqueue_script(
                'ml-license-manager-admin',
                ML_LICENSE_MANAGER_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                ML_LICENSE_MANAGER_VERSION,
                true
            );
            
            // 傳遞資料到 JavaScript
            wp_localize_script('ml-license-manager-admin', 'mlLicenseManager', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ml-license-manager-admin'),
                'i18n' => array(
                    'confirmDelete' => __('確定要刪除此授權碼嗎？', 'ml-license-manager'),
                    'confirmRevoke' => __('確定要撤銷此授權碼嗎？', 'ml-license-manager'),
                ),
            ));
        }
    }
    
    /**
     * 渲染儀表板頁面
     */
    public function render_dashboard_page() {
        global $wpdb;
        $licenses_table = $wpdb->prefix . 'ml_license_keys';
        $activations_table = $wpdb->prefix . 'ml_license_activations';
        
        // 獲取統計數據
        $total_licenses = $wpdb->get_var("SELECT COUNT(*) FROM {$licenses_table}");
        $active_licenses = $wpdb->get_var("SELECT COUNT(*) FROM {$licenses_table} WHERE status = 'active'");
        $expired_licenses = $wpdb->get_var("SELECT COUNT(*) FROM {$licenses_table} WHERE status = 'expired'");
        $total_activations = $wpdb->get_var("SELECT COUNT(*) FROM {$activations_table} WHERE status = 'active'");
        
        include ML_LICENSE_MANAGER_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
    
    /**
     * 渲染授權碼列表頁面
     */
    public function render_licenses_page() {
        global $wpdb;
        $licenses_table = $wpdb->prefix . 'ml_license_keys';
        
        // 處理刪除操作
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            check_admin_referer('delete-license-' . $_GET['id']);
            $wpdb->delete($licenses_table, array('id' => intval($_GET['id'])), array('%d'));
            wp_redirect(admin_url('admin.php?page=ml-license-keys&message=deleted'));
            exit;
        }
        
        // 獲取授權碼列表
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $licenses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$licenses_table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
        
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$licenses_table}");
        $total_pages = ceil($total_items / $per_page);
        
        include ML_LICENSE_MANAGER_PLUGIN_DIR . 'templates/admin/licenses-list.php';
    }
    
    /**
     * 渲染新增授權碼頁面
     */
    public function render_add_license_page() {
        // 處理表單提交
        if (isset($_POST['ml_add_license']) && check_admin_referer('ml_add_license')) {
            $license_manager = ML_License_Key::instance();
            
            $args = array(
                'product_id' => isset($_POST['product_id']) ? intval($_POST['product_id']) : null,
                'user_id' => isset($_POST['user_id']) ? intval($_POST['user_id']) : null,
                'activation_limit' => isset($_POST['activation_limit']) ? intval($_POST['activation_limit']) : 1,
                'expires_at' => isset($_POST['expires_at']) && !empty($_POST['expires_at']) ? 
                    sanitize_text_field($_POST['expires_at']) : null,
            );
            
            $result = $license_manager->generate_license_key($args);
            
            if (is_wp_error($result)) {
                $error = $result->get_error_message();
            } else {
                wp_redirect(admin_url('admin.php?page=ml-license-keys&message=created'));
                exit;
            }
        }
        
        include ML_LICENSE_MANAGER_PLUGIN_DIR . 'templates/admin/add-license.php';
    }
    
    /**
     * 渲染啟用記錄頁面
     */
    public function render_activations_page() {
        global $wpdb;
        $activations_table = $wpdb->prefix . 'ml_license_activations';
        $licenses_table = $wpdb->prefix . 'ml_license_keys';
        
        // 獲取啟用記錄
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $activations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, l.license_key 
                FROM {$activations_table} a 
                LEFT JOIN {$licenses_table} l ON a.license_key_id = l.id 
                ORDER BY a.activated_at DESC 
                LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
        
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$activations_table}");
        $total_pages = ceil($total_items / $per_page);
        
        include ML_LICENSE_MANAGER_PLUGIN_DIR . 'templates/admin/activations-list.php';
    }
    
    /**
     * 渲染設定頁面
     */
    public function render_settings_page() {
        // 處理表單提交
        if (isset($_POST['ml_save_settings']) && check_admin_referer('ml_save_settings')) {
            $settings = array(
                'enable_license_system' => isset($_POST['enable_license_system']) ? 'yes' : 'no',
                'default_activation_limit' => isset($_POST['default_activation_limit']) ? 
                    intval($_POST['default_activation_limit']) : 1,
                'default_expiry_days' => isset($_POST['default_expiry_days']) ? 
                    intval($_POST['default_expiry_days']) : 365,
                'license_key_length' => isset($_POST['license_key_length']) ? 
                    intval($_POST['license_key_length']) : 32,
                'license_key_format' => isset($_POST['license_key_format']) ? 
                    sanitize_text_field($_POST['license_key_format']) : 'alphanumeric',
                'enable_api' => isset($_POST['enable_api']) ? 'yes' : 'no',
                'require_api_authentication' => isset($_POST['require_api_authentication']) ? 'yes' : 'no',
            );
            
            update_option('ml_license_manager_settings', $settings);
            
            wp_redirect(admin_url('admin.php?page=ml-license-settings&message=saved'));
            exit;
        }
        
        $settings = get_option('ml_license_manager_settings', array());
        
        include ML_LICENSE_MANAGER_PLUGIN_DIR . 'templates/admin/settings.php';
    }
}
