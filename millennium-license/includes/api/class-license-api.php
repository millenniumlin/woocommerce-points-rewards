<?php
/**
 * License REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

class Millennium_License_API {
    
    /**
     * API 命名空間
     */
    const NAMESPACE = 'millennium-license/v1';
    
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
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * 註冊 REST API 路由
     */
    public function register_routes() {
        // 驗證授權碼
        register_rest_route(self::NAMESPACE, '/validate', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_license'),
            'permission_callback' => '__return_true',
        ));
        
        // 啟用授權碼
        register_rest_route(self::NAMESPACE, '/activate', array(
            'methods' => 'POST',
            'callback' => array($this, 'activate_license'),
            'permission_callback' => '__return_true',
        ));
        
        // 停用授權碼
        register_rest_route(self::NAMESPACE, '/deactivate', array(
            'methods' => 'POST',
            'callback' => array($this, 'deactivate_license'),
            'permission_callback' => '__return_true',
        ));
        
        // 檢查授權狀態
        register_rest_route(self::NAMESPACE, '/check', array(
            'methods' => 'POST',
            'callback' => array($this, 'check_license'),
            'permission_callback' => '__return_true',
        ));
        
        // 獲取授權資訊
        register_rest_route(self::NAMESPACE, '/info', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_license_info'),
            'permission_callback' => array($this, 'check_permission'),
        ));
    }
    
    /**
     * 檢查權限
     */
    public function check_permission() {
        $auth = Millennium_License_API_Auth::instance();
        $result = $auth->authenticate_request();
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return true;
    }
    
    /**
     * 驗證授權碼
     */
    public function validate_license($request) {
        $license_key = sanitize_text_field($request->get_param('license_key'));
        $site_url = sanitize_text_field($request->get_param('site_url'));
        $instance_id = sanitize_text_field($request->get_param('instance_id'));
        
        if (!$license_key) {
            return new WP_Error('missing_parameter', __('缺少授權碼參數', 'millennium-license'), array('status' => 400));
        }
        
        $result = Millennium_License_Key::validate($license_key, $site_url, $instance_id);
        
        if (!$result['valid']) {
            return new WP_Error($result['error'], $result['message'], array('status' => 400));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'valid' => true,
            'message' => __('授權碼有效', 'millennium-license'),
            'data' => array(
                'license_key' => $result['license']->license_key,
                'status' => $result['license']->status,
                'expires_at' => $result['license']->expires_at,
                'max_activations' => $result['license']->max_activations,
                'activation_count' => $result['license']->activation_count,
            )
        ));
    }
    
    /**
     * 啟用授權碼
     */
    public function activate_license($request) {
        $license_key = sanitize_text_field($request->get_param('license_key'));
        $site_url = sanitize_text_field($request->get_param('site_url'));
        $instance_id = sanitize_text_field($request->get_param('instance_id'));
        
        if (!$license_key || !$site_url || !$instance_id) {
            return new WP_Error('missing_parameters', __('缺少必要參數', 'millennium-license'), array('status' => 400));
        }
        
        $metadata = array(
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'ip_address' => $this->get_client_ip(),
        );
        
        $result = Millennium_License_Key::activate($license_key, $site_url, $instance_id, $metadata);
        
        if (!$result['valid']) {
            return new WP_Error($result['error'], $result['message'], array('status' => 400));
        }
        
        // 發送啟用郵件通知
        $this->send_activation_email($result['license']);
        
        return rest_ensure_response(array(
            'success' => true,
            'activated' => true,
            'message' => __('授權碼已啟用', 'millennium-license'),
            'activation_token' => $result['activation_token'],
            'data' => array(
                'license_key' => $result['license']->license_key,
                'expires_at' => $result['license']->expires_at,
            )
        ));
    }
    
    /**
     * 停用授權碼
     */
    public function deactivate_license($request) {
        $license_key = sanitize_text_field($request->get_param('license_key'));
        $site_url = sanitize_text_field($request->get_param('site_url'));
        $instance_id = sanitize_text_field($request->get_param('instance_id'));
        
        if (!$license_key || !$site_url || !$instance_id) {
            return new WP_Error('missing_parameters', __('缺少必要參數', 'millennium-license'), array('status' => 400));
        }
        
        $result = Millennium_License_Key::deactivate($license_key, $site_url, $instance_id);
        
        if (!$result['success']) {
            return new WP_Error($result['error'], __('停用失敗', 'millennium-license'), array('status' => 400));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'deactivated' => true,
            'message' => __('授權碼已停用', 'millennium-license'),
        ));
    }
    
    /**
     * 檢查授權狀態
     */
    public function check_license($request) {
        $activation_token = sanitize_text_field($request->get_param('activation_token'));
        
        if (!$activation_token) {
            return new WP_Error('missing_parameter', __('缺少啟用令牌', 'millennium-license'), array('status' => 400));
        }
        
        global $wpdb;
        $activation_table = $wpdb->prefix . 'millennium_license_activations';
        $license_table = $wpdb->prefix . 'millennium_licenses';
        
        $activation = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, l.* FROM $activation_table a 
             INNER JOIN $license_table l ON a.license_id = l.id 
             WHERE a.activation_token = %s",
            $activation_token
        ));
        
        if (!$activation) {
            return new WP_Error('invalid_token', __('無效的啟用令牌', 'millennium-license'), array('status' => 404));
        }
        
        // 更新最後檢查時間
        $wpdb->update(
            $activation_table,
            array('last_checked' => current_time('mysql')),
            array('activation_token' => $activation_token)
        );
        
        return rest_ensure_response(array(
            'success' => true,
            'active' => $activation->status === 'active',
            'data' => array(
                'license_key' => $activation->license_key,
                'status' => $activation->status,
                'expires_at' => $activation->expires_at,
            )
        ));
    }
    
    /**
     * 獲取授權資訊
     */
    public function get_license_info($request) {
        $license_key = sanitize_text_field($request->get_param('license_key'));
        
        if (!$license_key) {
            return new WP_Error('missing_parameter', __('缺少授權碼參數', 'millennium-license'), array('status' => 400));
        }
        
        $manager = Millennium_License_Manager_Core::instance();
        $license = $manager->get_license_by_key($license_key);
        
        if (!$license) {
            return new WP_Error('not_found', __('找不到授權碼', 'millennium-license'), array('status' => 404));
        }
        
        $activations = $manager->get_activations($license->id);
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'license_key' => $license->license_key,
                'status' => $license->status,
                'max_activations' => $license->max_activations,
                'activation_count' => $license->activation_count,
                'expires_at' => $license->expires_at,
                'created_at' => $license->created_at,
                'activations' => $activations,
            )
        ));
    }
    
    /**
     * 發送授權碼郵件
     */
    private function send_activation_email($license) {
        $settings = get_option('millennium_license_settings', array());
        
        if (!isset($settings['enable_email_notifications']) || $settings['enable_email_notifications'] !== 'yes') {
            return;
        }
        
        if (!$license->user_id) {
            return;
        }
        
        $user = get_userdata($license->user_id);
        if (!$user) {
            return;
        }
        
        $subject = sprintf(__('授權碼已啟用 - %s', 'millennium-license'), get_bloginfo('name'));
        
        ob_start();
        include MILLENNIUM_LICENSE_PLUGIN_DIR . 'templates/emails/license-key-email.php';
        $message = ob_get_clean();
        
        $result = wp_mail($user->user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
        
        // 記錄郵件發送失敗
        if (!$result) {
            error_log('Millennium License: Failed to send activation email to ' . $user->user_email);
        }
    }
    
    /**
     * 獲取客戶端 IP
     */
    private function get_client_ip() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
}
