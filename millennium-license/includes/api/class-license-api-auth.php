<?php
/**
 * License API Authentication
 */

if (!defined('ABSPATH')) {
    exit;
}

class Millennium_License_API_Auth {
    
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
        // API 認證可以使用 WordPress 應用程式密碼或自訂 API 密鑰
    }
    
    /**
     * 驗證 API 請求
     */
    public function authenticate_request() {
        // 檢查是否啟用 API
        $settings = get_option('millennium_license_settings', array());
        if (!isset($settings['enable_api']) || $settings['enable_api'] !== 'yes') {
            return new WP_Error('api_disabled', __('API 已停用', 'millennium-license'), array('status' => 403));
        }
        
        // 檢查基本認證
        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            $user = wp_authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
            
            if (is_wp_error($user)) {
                return new WP_Error('invalid_credentials', __('無效的認證資訊', 'millennium-license'), array('status' => 401));
            }
            
            wp_set_current_user($user->ID);
            return true;
        }
        
        // 檢查 API 密鑰（可以在標頭或查詢參數中）
        $api_key = null;
        
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            $api_key = sanitize_text_field($_SERVER['HTTP_X_API_KEY']);
        } elseif (isset($_GET['api_key'])) {
            $api_key = sanitize_text_field($_GET['api_key']);
        }
        
        if ($api_key) {
            // 驗證 API 密鑰
            $stored_key = get_option('millennium_license_api_key');
            
            if ($api_key === $stored_key) {
                return true;
            }
            
            return new WP_Error('invalid_api_key', __('無效的 API 密鑰', 'millennium-license'), array('status' => 401));
        }
        
        // 檢查是否為公開端點
        if ($this->is_public_endpoint()) {
            return true;
        }
        
        return new WP_Error('authentication_required', __('需要認證', 'millennium-license'), array('status' => 401));
    }
    
    /**
     * 檢查是否為公開端點
     */
    private function is_public_endpoint() {
        $public_endpoints = array(
            '/millennium-license/v1/validate',
            '/millennium-license/v1/activate',
            '/millennium-license/v1/deactivate',
        );
        
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        foreach ($public_endpoints as $endpoint) {
            if (strpos($request_uri, $endpoint) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 生成 API 密鑰
     */
    public static function generate_api_key() {
        $key = wp_generate_password(32, false);
        update_option('millennium_license_api_key', $key);
        return $key;
    }
    
    /**
     * 獲取 API 密鑰
     */
    public static function get_api_key() {
        $key = get_option('millennium_license_api_key');
        
        if (!$key) {
            $key = self::generate_api_key();
        }
        
        return $key;
    }
}
