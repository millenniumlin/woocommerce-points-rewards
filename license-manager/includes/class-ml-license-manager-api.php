<?php
/**
 * REST API 管理類別
 * 
 * @package ML_License_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API 管理類別
 */
class ML_License_Manager_API {
    
    /**
     * 單例實例
     */
    private static $instance = null;
    
    /**
     * API 命名空間
     */
    private $namespace = 'ml-license/v1';
    
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
        register_rest_route($this->namespace, '/validate', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_license'),
            'permission_callback' => array($this, 'validate_api_permission'),
            'args' => array(
                'license_key' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => '授權碼',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // 啟用授權碼
        register_rest_route($this->namespace, '/activate', array(
            'methods' => 'POST',
            'callback' => array($this, 'activate_license'),
            'permission_callback' => array($this, 'validate_api_permission'),
            'args' => array(
                'license_key' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => '授權碼',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'instance_name' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => '實例名稱',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'instance_id' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => '實例識別碼',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // 停用授權碼
        register_rest_route($this->namespace, '/deactivate', array(
            'methods' => 'POST',
            'callback' => array($this, 'deactivate_license'),
            'permission_callback' => array($this, 'validate_api_permission'),
            'args' => array(
                'activation_token' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => '啟用令牌',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // 檢查授權碼狀態
        register_rest_route($this->namespace, '/check', array(
            'methods' => 'POST',
            'callback' => array($this, 'check_license'),
            'permission_callback' => array($this, 'validate_api_permission'),
            'args' => array(
                'activation_token' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => '啟用令牌',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }
    
    /**
     * 驗證 API 權限
     * 
     * @param WP_REST_Request $request 請求
     * @return bool 是否有權限
     */
    public function validate_api_permission($request) {
        $settings = get_option('ml_license_manager_settings', array());
        
        // 檢查是否啟用 API
        if (isset($settings['enable_api']) && $settings['enable_api'] !== 'yes') {
            return false;
        }
        
        // 檢查是否需要驗證
        if (isset($settings['require_api_authentication']) && $settings['require_api_authentication'] === 'yes') {
            // 這裡可以實作 API 金鑰驗證
            // 目前先返回 true，之後可以添加更嚴格的驗證
            return true;
        }
        
        return true;
    }
    
    /**
     * 驗證授權碼
     * 
     * @param WP_REST_Request $request 請求
     * @return WP_REST_Response|WP_Error 回應
     */
    public function validate_license($request) {
        $license_key = $request->get_param('license_key');
        
        $license_manager = ML_License_Key::instance();
        $result = $license_manager->validate_license($license_key);
        
        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }
        
        $license = $license_manager->get_license($license_key);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('授權碼有效', 'ml-license-manager'),
            'data' => array(
                'license_key' => $license->license_key,
                'status' => $license->status,
                'expires_at' => $license->expires_at,
                'activation_count' => $license->activation_count,
                'activation_limit' => $license->activation_limit,
            ),
        ), 200);
    }
    
    /**
     * 啟用授權碼
     * 
     * @param WP_REST_Request $request 請求
     * @return WP_REST_Response|WP_Error 回應
     */
    public function activate_license($request) {
        $license_key = $request->get_param('license_key');
        $instance_name = $request->get_param('instance_name');
        $instance_id = $request->get_param('instance_id');
        
        $license_manager = ML_License_Key::instance();
        
        // 先驗證授權碼
        $validation = $license_manager->validate_license($license_key);
        if (is_wp_error($validation)) {
            return new WP_Error(
                $validation->get_error_code(),
                $validation->get_error_message(),
                array('status' => 400)
            );
        }
        
        $license = $license_manager->get_license($license_key);
        
        // 生成啟用令牌
        $activation_token = $this->generate_activation_token();
        
        // 記錄啟用
        global $wpdb;
        $activations_table = $wpdb->prefix . 'ml_license_activations';
        
        $result = $wpdb->insert(
            $activations_table,
            array(
                'license_key_id' => $license->id,
                'activation_token' => $activation_token,
                'instance_name' => $instance_name,
                'instance_id' => $instance_id,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'status' => 'active',
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if (!$result) {
            return new WP_Error(
                'activation_failed',
                __('授權碼啟用失敗', 'ml-license-manager'),
                array('status' => 500)
            );
        }
        
        // 更新啟用次數
        $wpdb->update(
            $wpdb->prefix . 'ml_license_keys',
            array('activation_count' => $license->activation_count + 1),
            array('id' => $license->id),
            array('%d'),
            array('%d')
        );
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('授權碼啟用成功', 'ml-license-manager'),
            'data' => array(
                'activation_token' => $activation_token,
                'license_key' => $license->license_key,
                'expires_at' => $license->expires_at,
            ),
        ), 200);
    }
    
    /**
     * 停用授權碼
     * 
     * @param WP_REST_Request $request 請求
     * @return WP_REST_Response|WP_Error 回應
     */
    public function deactivate_license($request) {
        $activation_token = $request->get_param('activation_token');
        
        global $wpdb;
        $activations_table = $wpdb->prefix . 'ml_license_activations';
        
        // 獲取啟用記錄
        $activation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$activations_table} WHERE activation_token = %s AND status = 'active'",
                $activation_token
            )
        );
        
        if (!$activation) {
            return new WP_Error(
                'not_found',
                __('啟用令牌不存在或已停用', 'ml-license-manager'),
                array('status' => 404)
            );
        }
        
        // 更新啟用狀態
        $result = $wpdb->update(
            $activations_table,
            array(
                'status' => 'deactivated',
                'deactivated_at' => current_time('mysql'),
            ),
            array('id' => $activation->id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error(
                'deactivation_failed',
                __('授權碼停用失敗', 'ml-license-manager'),
                array('status' => 500)
            );
        }
        
        // 減少啟用次數
        $license_table = $wpdb->prefix . 'ml_license_keys';
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$license_table} SET activation_count = activation_count - 1 WHERE id = %d AND activation_count > 0",
                $activation->license_key_id
            )
        );
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('授權碼停用成功', 'ml-license-manager'),
        ), 200);
    }
    
    /**
     * 檢查授權碼狀態
     * 
     * @param WP_REST_Request $request 請求
     * @return WP_REST_Response|WP_Error 回應
     */
    public function check_license($request) {
        $activation_token = $request->get_param('activation_token');
        
        global $wpdb;
        $activations_table = $wpdb->prefix . 'ml_license_activations';
        $licenses_table = $wpdb->prefix . 'ml_license_keys';
        
        // 獲取啟用記錄和授權碼資訊
        $query = $wpdb->prepare(
            "SELECT a.*, l.status as license_status, l.expires_at, l.license_key
            FROM {$activations_table} a
            INNER JOIN {$licenses_table} l ON a.license_key_id = l.id
            WHERE a.activation_token = %s",
            $activation_token
        );
        
        $activation = $wpdb->get_row($query);
        
        if (!$activation) {
            return new WP_Error(
                'not_found',
                __('啟用令牌不存在', 'ml-license-manager'),
                array('status' => 404)
            );
        }
        
        // 更新最後檢查時間
        $wpdb->update(
            $activations_table,
            array('last_checked_at' => current_time('mysql')),
            array('id' => $activation->id),
            array('%s'),
            array('%d')
        );
        
        // 檢查授權碼狀態
        $is_valid = ($activation->status === 'active' && 
                    $activation->license_status === 'active' &&
                    (!$activation->expires_at || strtotime($activation->expires_at) > time()));
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'is_valid' => $is_valid,
                'license_key' => $activation->license_key,
                'status' => $activation->status,
                'license_status' => $activation->license_status,
                'expires_at' => $activation->expires_at,
                'last_checked_at' => current_time('mysql'),
            ),
        ), 200);
    }
    
    /**
     * 生成啟用令牌
     * 
     * @return string 啟用令牌
     */
    private function generate_activation_token() {
        return wp_generate_password(64, false);
    }
}
