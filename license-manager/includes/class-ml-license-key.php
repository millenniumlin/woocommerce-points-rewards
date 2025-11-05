<?php
/**
 * 授權碼管理類別
 * 
 * @package ML_License_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 授權碼管理類別
 */
class ML_License_Key {
    
    /**
     * 單例實例
     */
    private static $instance = null;
    
    /**
     * 資料庫表格名稱
     */
    private $table_name;
    private $activations_table;
    private $logs_table;
    private $meta_table;
    
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
        global $wpdb;
        
        $this->table_name = $wpdb->prefix . 'ml_license_keys';
        $this->activations_table = $wpdb->prefix . 'ml_license_activations';
        $this->logs_table = $wpdb->prefix . 'ml_license_logs';
        $this->meta_table = $wpdb->prefix . 'ml_license_meta';
    }
    
    /**
     * 生成授權碼
     * 
     * @param array $args 授權碼參數
     * @return string|WP_Error 授權碼或錯誤
     */
    public function generate_license_key($args = array()) {
        $defaults = array(
            'product_id' => null,
            'order_id' => null,
            'user_id' => null,
            'activation_limit' => 1,
            'expires_at' => null,
            'status' => 'active',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // 生成唯一授權碼
        $license_key = $this->generate_unique_key();
        
        if (!$license_key) {
            return new WP_Error('generation_failed', __('授權碼生成失敗', 'ml-license-manager'));
        }
        
        // 插入資料庫
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'license_key' => $license_key,
                'product_id' => $args['product_id'],
                'order_id' => $args['order_id'],
                'user_id' => $args['user_id'],
                'status' => $args['status'],
                'activation_limit' => $args['activation_limit'],
                'activation_count' => 0,
                'expires_at' => $args['expires_at'],
            ),
            array('%s', '%d', '%d', '%d', '%s', '%d', '%d', '%s')
        );
        
        if (!$result) {
            return new WP_Error('insert_failed', __('授權碼儲存失敗', 'ml-license-manager'));
        }
        
        // 記錄日誌
        $this->log_action($wpdb->insert_id, 'created', '授權碼已創建');
        
        return $license_key;
    }
    
    /**
     * 生成唯一授權碼
     * 
     * @return string|false 授權碼或失敗
     */
    private function generate_unique_key() {
        $settings = get_option('ml_license_manager_settings', array());
        $length = isset($settings['license_key_length']) ? $settings['license_key_length'] : 32;
        $format = isset($settings['license_key_format']) ? $settings['license_key_format'] : 'alphanumeric';
        $prefix = isset($settings['license_key_prefix']) ? $settings['license_key_prefix'] : '';
        $suffix = isset($settings['license_key_suffix']) ? $settings['license_key_suffix'] : '';
        
        $max_attempts = 10;
        $attempts = 0;
        
        while ($attempts < $max_attempts) {
            // 生成隨機字符串
            switch ($format) {
                case 'hex':
                    $key = bin2hex(random_bytes($length / 2));
                    break;
                case 'alphanumeric':
                default:
                    $key = $this->generate_random_string($length);
                    break;
            }
            
            // 添加前綴和後綴
            $license_key = $prefix . $key . $suffix;
            
            // 檢查唯一性
            if (!$this->license_key_exists($license_key)) {
                return strtoupper($license_key);
            }
            
            $attempts++;
        }
        
        return false;
    }
    
    /**
     * 生成隨機字符串
     * 
     * @param int $length 長度
     * @return string 隨機字符串
     */
    private function generate_random_string($length) {
        $characters = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // 移除易混淆的字符
        $string = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[random_int(0, $max)];
        }
        
        return $string;
    }
    
    /**
     * 檢查授權碼是否存在
     * 
     * @param string $license_key 授權碼
     * @return bool 是否存在
     */
    public function license_key_exists($license_key) {
        global $wpdb;
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE license_key = %s",
                $license_key
            )
        );
        
        return $count > 0;
    }
    
    /**
     * 獲取授權碼資訊
     * 
     * @param string $license_key 授權碼
     * @return object|null 授權碼資訊
     */
    public function get_license($license_key) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE license_key = %s",
                $license_key
            )
        );
    }
    
    /**
     * 驗證授權碼
     * 
     * @param string $license_key 授權碼
     * @return bool|WP_Error 驗證結果
     */
    public function validate_license($license_key) {
        $license = $this->get_license($license_key);
        
        if (!$license) {
            return new WP_Error('not_found', __('授權碼不存在', 'ml-license-manager'));
        }
        
        // 檢查狀態
        if ($license->status !== 'active') {
            return new WP_Error('inactive', __('授權碼未啟用或已被撤銷', 'ml-license-manager'));
        }
        
        // 檢查過期時間
        if ($license->expires_at && strtotime($license->expires_at) < time()) {
            // 更新狀態為過期
            $this->update_license_status($license->id, 'expired');
            return new WP_Error('expired', __('授權碼已過期', 'ml-license-manager'));
        }
        
        // 檢查啟用次數
        if ($license->activation_count >= $license->activation_limit) {
            return new WP_Error('limit_reached', __('授權碼啟用次數已達上限', 'ml-license-manager'));
        }
        
        return true;
    }
    
    /**
     * 更新授權碼狀態
     * 
     * @param int $license_id 授權碼ID
     * @param string $status 新狀態
     * @return bool 是否成功
     */
    public function update_license_status($license_id, $status) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_name,
            array('status' => $status),
            array('id' => $license_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $this->log_action($license_id, $status, "授權碼狀態更新為 {$status}");
        }
        
        return $result !== false;
    }
    
    /**
     * 記錄活動日誌
     * 
     * @param int $license_id 授權碼ID
     * @param string $action 動作
     * @param string $description 描述
     */
    private function log_action($license_id, $action, $description = '') {
        global $wpdb;
        
        $wpdb->insert(
            $this->logs_table,
            array(
                'license_key_id' => $license_id,
                'action' => $action,
                'description' => $description,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * 獲取授權碼元數據
     * 
     * @param int $license_id 授權碼ID
     * @param string $meta_key 鍵
     * @return mixed 值
     */
    public function get_license_meta($license_id, $meta_key) {
        global $wpdb;
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM {$this->meta_table} WHERE license_key_id = %d AND meta_key = %s",
                $license_id,
                $meta_key
            )
        );
    }
    
    /**
     * 更新授權碼元數據
     * 
     * @param int $license_id 授權碼ID
     * @param string $meta_key 鍵
     * @param mixed $meta_value 值
     * @return bool 是否成功
     */
    public function update_license_meta($license_id, $meta_key, $meta_value) {
        global $wpdb;
        
        // 檢查是否已存在
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->meta_table} WHERE license_key_id = %d AND meta_key = %s",
                $license_id,
                $meta_key
            )
        );
        
        if ($existing) {
            // 更新
            return $wpdb->update(
                $this->meta_table,
                array('meta_value' => maybe_serialize($meta_value)),
                array(
                    'license_key_id' => $license_id,
                    'meta_key' => $meta_key
                ),
                array('%s'),
                array('%d', '%s')
            ) !== false;
        } else {
            // 插入
            return $wpdb->insert(
                $this->meta_table,
                array(
                    'license_key_id' => $license_id,
                    'meta_key' => $meta_key,
                    'meta_value' => maybe_serialize($meta_value)
                ),
                array('%d', '%s', '%s')
            ) !== false;
        }
    }
}
