<?php
/**
 * License Manager Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class Millennium_License_Manager_Core {
    
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
        // 初始化 hooks
    }
    
    /**
     * 創建授權碼
     */
    public function create_license($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'product_id' => null,
            'order_id' => null,
            'user_id' => null,
            'status' => 'active',
            'max_activations' => 1,
            'expires_at' => null,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // 生成授權碼
        $settings = get_option('millennium_license_settings', array());
        $format = isset($settings['license_key_format']) ? $settings['license_key_format'] : 'XXXX-XXXX-XXXX-XXXX';
        $license_key = Millennium_License_Key::generate($format);
        
        // 計算過期時間
        if (!$args['expires_at']) {
            $expiry_days = isset($settings['default_expiry_days']) ? intval($settings['default_expiry_days']) : 365;
            $args['expires_at'] = date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));
        }
        
        // 插入資料庫
        $table = $wpdb->prefix . 'millennium_licenses';
        $result = $wpdb->insert(
            $table,
            array(
                'license_key' => $license_key,
                'product_id' => $args['product_id'],
                'order_id' => $args['order_id'],
                'user_id' => $args['user_id'],
                'status' => $args['status'],
                'max_activations' => $args['max_activations'],
                'expires_at' => $args['expires_at'],
            )
        );
        
        if ($result) {
            $license_id = $wpdb->insert_id;
            
            // 記錄日誌
            Millennium_License_Key::log_action($license_id, 'create');
            
            return $this->get_license($license_id);
        }
        
        return false;
    }
    
    /**
     * 獲取授權碼
     */
    public function get_license($license_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'millennium_licenses';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $license_id
        ));
    }
    
    /**
     * 根據授權碼字串獲取授權碼
     */
    public function get_license_by_key($license_key) {
        global $wpdb;
        $table = $wpdb->prefix . 'millennium_licenses';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE license_key = %s",
            $license_key
        ));
    }
    
    /**
     * 更新授權碼
     */
    public function update_license($license_id, $args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'millennium_licenses';
        
        $allowed_fields = array(
            'status',
            'max_activations',
            'expires_at',
            'user_id',
        );
        
        $update_data = array();
        foreach ($args as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $update_data[$key] = $value;
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $license_id)
        );
        
        if ($result !== false) {
            // 記錄日誌
            Millennium_License_Key::log_action($license_id, 'update', $update_data);
            return true;
        }
        
        return false;
    }
    
    /**
     * 刪除授權碼
     */
    public function delete_license($license_id) {
        global $wpdb;
        
        // 刪除啟用記錄
        $wpdb->delete(
            $wpdb->prefix . 'millennium_license_activations',
            array('license_id' => $license_id)
        );
        
        // 刪除日誌
        $wpdb->delete(
            $wpdb->prefix . 'millennium_license_logs',
            array('license_id' => $license_id)
        );
        
        // 刪除授權碼
        $result = $wpdb->delete(
            $wpdb->prefix . 'millennium_licenses',
            array('id' => $license_id)
        );
        
        return $result !== false;
    }
    
    /**
     * 獲取授權碼列表
     */
    public function get_licenses($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'millennium_licenses';
        
        $defaults = array(
            'status' => null,
            'user_id' => null,
            'product_id' => null,
            'order_id' => null,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        
        if ($args['status']) {
            $where[] = $wpdb->prepare('status = %s', $args['status']);
        }
        
        if ($args['user_id']) {
            $where[] = $wpdb->prepare('user_id = %d', $args['user_id']);
        }
        
        if ($args['product_id']) {
            $where[] = $wpdb->prepare('product_id = %d', $args['product_id']);
        }
        
        if ($args['order_id']) {
            $where[] = $wpdb->prepare('order_id = %d', $args['order_id']);
        }
        
        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($query, $args['limit'], $args['offset']));
    }
    
    /**
     * 計算授權碼數量
     */
    public function count_licenses($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'millennium_licenses';
        
        $where = array('1=1');
        
        if (isset($args['status']) && $args['status']) {
            $where[] = $wpdb->prepare('status = %s', $args['status']);
        }
        
        if (isset($args['user_id']) && $args['user_id']) {
            $where[] = $wpdb->prepare('user_id = %d', $args['user_id']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        return $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where_clause");
    }
    
    /**
     * 獲取授權碼啟用記錄
     */
    public function get_activations($license_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'millennium_license_activations';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE license_id = %d ORDER BY activated_at DESC",
            $license_id
        ));
    }
    
    /**
     * 獲取授權碼日誌
     */
    public function get_logs($license_id, $limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'millennium_license_logs';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE license_id = %d ORDER BY created_at DESC LIMIT %d",
            $license_id,
            $limit
        ));
    }
}
