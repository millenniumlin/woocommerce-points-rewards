<?php
/**
 * License Key Generation and Validation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Millennium_License_Key {
    
    /**
     * 生成授權碼
     */
    public static function generate($format = 'XXXX-XXXX-XXXX-XXXX') {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $license_key = '';
        
        // 將格式分解為段落
        $segments = explode('-', $format);
        $generated_segments = array();
        
        foreach ($segments as $segment) {
            $length = strlen($segment);
            $generated_segment = '';
            
            for ($i = 0; $i < $length; $i++) {
                $generated_segment .= $characters[random_int(0, strlen($characters) - 1)];
            }
            
            $generated_segments[] = $generated_segment;
        }
        
        $license_key = implode('-', $generated_segments);
        
        // 確保授權碼唯一
        if (self::exists($license_key)) {
            return self::generate($format);
        }
        
        return $license_key;
    }
    
    /**
     * 檢查授權碼是否存在
     */
    public static function exists($license_key) {
        global $wpdb;
        $table = $wpdb->prefix . 'millennium_licenses';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE license_key = %s",
            $license_key
        ));
        
        return $count > 0;
    }
    
    /**
     * 驗證授權碼格式
     */
    public static function validate_format($license_key, $format = 'XXXX-XXXX-XXXX-XXXX') {
        // 移除空白
        $license_key = trim($license_key);
        
        // 轉換格式為正則表達式
        $pattern = '/^' . str_replace('X', '[A-Z0-9]', $format) . '$/';
        
        return preg_match($pattern, $license_key);
    }
    
    /**
     * 驗證授權碼
     */
    public static function validate($license_key, $site_url = null, $instance_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'millennium_licenses';
        
        // 獲取授權碼資訊
        $license = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE license_key = %s",
            $license_key
        ));
        
        if (!$license) {
            return array(
                'valid' => false,
                'error' => 'license_not_found',
                'message' => __('授權碼不存在', 'millennium-license')
            );
        }
        
        // 檢查授權狀態
        if ($license->status !== 'active') {
            return array(
                'valid' => false,
                'error' => 'license_inactive',
                'message' => __('授權碼已停用', 'millennium-license')
            );
        }
        
        // 檢查過期時間
        if ($license->expires_at && strtotime($license->expires_at) < time()) {
            return array(
                'valid' => false,
                'error' => 'license_expired',
                'message' => __('授權碼已過期', 'millennium-license')
            );
        }
        
        // 檢查啟用次數
        if ($license->activation_count >= $license->max_activations) {
            // 如果提供了站點資訊，檢查是否已在此站點啟用
            if ($site_url && $instance_id) {
                $activation_table = $wpdb->prefix . 'millennium_license_activations';
                $activation = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $activation_table WHERE license_id = %d AND site_url = %s AND instance_id = %s AND status = 'active'",
                    $license->id,
                    $site_url,
                    $instance_id
                ));
                
                if (!$activation) {
                    return array(
                        'valid' => false,
                        'error' => 'max_activations_reached',
                        'message' => __('已達最大啟用次數', 'millennium-license')
                    );
                }
            } else {
                return array(
                    'valid' => false,
                    'error' => 'max_activations_reached',
                    'message' => __('已達最大啟用次數', 'millennium-license')
                );
            }
        }
        
        return array(
            'valid' => true,
            'license' => $license
        );
    }
    
    /**
     * 啟用授權碼
     */
    public static function activate($license_key, $site_url, $instance_id, $metadata = array()) {
        global $wpdb;
        
        // 驗證授權碼
        $validation = self::validate($license_key, $site_url, $instance_id);
        
        if (!$validation['valid']) {
            return $validation;
        }
        
        $license = $validation['license'];
        
        // 檢查是否已在此站點啟用
        $activation_table = $wpdb->prefix . 'millennium_license_activations';
        $existing_activation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $activation_table WHERE license_id = %d AND site_url = %s AND instance_id = %s",
            $license->id,
            $site_url,
            $instance_id
        ));
        
        if ($existing_activation) {
            // 更新現有啟用記錄
            $wpdb->update(
                $activation_table,
                array(
                    'status' => 'active',
                    'last_checked' => current_time('mysql'),
                    'metadata' => maybe_serialize($metadata)
                ),
                array('id' => $existing_activation->id)
            );
            
            $activation_token = $existing_activation->activation_token;
        } else {
            // 創建新啟用記錄
            $activation_token = wp_generate_password(32, false);
            
            $wpdb->insert(
                $activation_table,
                array(
                    'license_id' => $license->id,
                    'activation_token' => $activation_token,
                    'site_url' => $site_url,
                    'instance_id' => $instance_id,
                    'status' => 'active',
                    'metadata' => maybe_serialize($metadata)
                )
            );
            
            // 更新啟用次數
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}millennium_licenses SET activation_count = activation_count + 1 WHERE id = %d",
                $license->id
            ));
        }
        
        // 記錄日誌
        self::log_action($license->id, 'activate', $metadata);
        
        return array(
            'valid' => true,
            'activated' => true,
            'activation_token' => $activation_token,
            'license' => $license
        );
    }
    
    /**
     * 停用授權碼
     */
    public static function deactivate($license_key, $site_url, $instance_id) {
        global $wpdb;
        
        $license_table = $wpdb->prefix . 'millennium_licenses';
        $activation_table = $wpdb->prefix . 'millennium_license_activations';
        
        // 獲取授權碼
        $license = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $license_table WHERE license_key = %s",
            $license_key
        ));
        
        if (!$license) {
            return array(
                'success' => false,
                'error' => 'license_not_found'
            );
        }
        
        // 停用啟用記錄
        $result = $wpdb->update(
            $activation_table,
            array('status' => 'deactivated'),
            array(
                'license_id' => $license->id,
                'site_url' => $site_url,
                'instance_id' => $instance_id
            )
        );
        
        if ($result) {
            // 更新啟用次數
            $wpdb->query($wpdb->prepare(
                "UPDATE $license_table SET activation_count = activation_count - 1 WHERE id = %d AND activation_count > 0",
                $license->id
            ));
            
            // 記錄日誌
            self::log_action($license->id, 'deactivate');
        }
        
        return array(
            'success' => true,
            'deactivated' => true
        );
    }
    
    /**
     * 記錄動作日誌
     */
    public static function log_action($license_id, $action, $metadata = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'millennium_license_logs';
        
        $wpdb->insert(
            $table,
            array(
                'license_id' => $license_id,
                'action' => $action,
                'ip_address' => self::get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                'metadata' => maybe_serialize($metadata)
            )
        );
    }
    
    /**
     * 獲取客戶端 IP
     */
    private static function get_client_ip() {
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
