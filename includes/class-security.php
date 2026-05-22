<?php
/**
 * 安全性管理類別
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 安全性管理類別
 */
class WC_Points_Rewards_Security {
    
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
    }
    
    /**
     * 初始化 hooks
     */
    private function init_hooks() {
        // AJAX 安全檢查
        add_action('wp_ajax_wc_points_rewards_apply_points', array($this, 'ajax_apply_points'));
        add_action('wp_ajax_wc_points_rewards_remove_points', array($this, 'ajax_remove_points'));
        
        // 管理員 AJAX 動作
        add_action('wp_ajax_wc_points_rewards_admin_add_points', array($this, 'ajax_admin_add_points'));
        add_action('wp_ajax_wc_points_rewards_admin_deduct_points', array($this, 'ajax_admin_deduct_points'));
    }
    
    /**
     * 驗證 nonce
     */
    public function verify_nonce($action = 'wc_points_rewards_nonce') {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', $action)) {
            wp_die(__('安全驗證失敗', 'wc-points-rewards'));
        }
    }
    
    /**
     * 檢查用戶權限
     */
    public function check_user_permission($user_id = null, $required_capability = 'read') {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        // 檢查是否為當前用戶或管理員
        if ($user_id !== get_current_user_id() && !current_user_can('manage_woocommerce')) {
            return false;
        }
        
        return user_can($user_id, $required_capability);
    }
    
    /**
     * 清理和驗證輸入數據
     */
    public function sanitize_input($data, $type = 'text') {
        switch ($type) {
            case 'text':
                return sanitize_text_field($data);
            
            case 'textarea':
                return sanitize_textarea_field($data);
            
            case 'email':
                return sanitize_email($data);
            
            case 'url':
                return esc_url_raw($data);
            
            case 'int':
                $value = intval($data);
                // 添加合理的範圍限制
                return max(0, min($value, 2147483647)); // 32位整數最大值
            
            case 'float':
                $value = floatval($data);
                // 添加合理的範圍限制，防止溢出
                return max(0, min($value, 999999999.99)); // 合理的點數上限
            
            case 'array':
                if (!is_array($data)) {
                    return array();
                }
                return array_map('sanitize_text_field', $data);
            
            default:
                return sanitize_text_field($data);
        }
    }
    
    /**
     * 防止 SQL 注入的安全查詢
     */
    public function safe_query($query, $params = array()) {
        global $wpdb;
        
        if (!empty($params)) {
            return $wpdb->prepare($query, $params);
        }
        
        return $query;
    }
    
    /**
     * 記錄安全事件
     */
    public function log_security_event($event_type, $description, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'event_type' => sanitize_text_field($event_type),
            'description' => sanitize_textarea_field($description),
            'user_id' => intval($user_id),
            'ip_address' => $this->get_user_ip(),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown')
        );
        
        // 可以保存到自定義日誌表或使用 WordPress 日誌
        error_log('WC Points Rewards Security: ' . json_encode($log_data));
        
        // 觸發安全事件動作
        do_action('wc_points_rewards_security_event', $log_data);
    }
    
    /**
     * 獲取用戶 IP 地址 - 安全版本
     */
    private function get_user_ip() {
        $ip_headers = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field($_SERVER[$header]);
                // 處理多個 IP 地址的情況（取第一個）
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // 驗證 IP 地址格式
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // 後備方案：返回本地 IP 或空字符串
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * 檢查是否為可疑活動
     */
    public function is_suspicious_activity($user_id, $action_type) {
        // 檢查頻率限制
        $key = "wc_points_rewards_activity_{$user_id}_{$action_type}";
        $count = get_transient($key);
        
        if ($count && $count > 10) { // 10分鐘內超過10次操作
            $this->log_security_event('suspicious_activity', "用戶 {$user_id} 在短時間內進行過多 {$action_type} 操作", $user_id);
            return true;
        }
        
        // 更新計數器
        set_transient($key, ($count ? $count + 1 : 1), 600); // 10分鐘
        
        return false;
    }
    
    /**
     * AJAX: 申請使用點數
     */
    public function ajax_apply_points() {
        $this->verify_nonce();
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('請先登入', 'wc-points-rewards'));
        }
        
        if ($this->is_suspicious_activity($user_id, 'apply_points')) {
            wp_send_json_error(__('操作過於頻繁，請稍後再試', 'wc-points-rewards'));
        }
        
        $points_to_use = $this->sanitize_input($_POST['points'], 'float');
        
        if ($points_to_use <= 0) {
            wp_send_json_error(__('點數必須大於0', 'wc-points-rewards'));
        }
        
        // 檢查用戶是否有足夠點數
        $database = WC_Points_Rewards_Database::instance();
        $available_points = $database->get_user_points($user_id);
        
        if ($points_to_use > $available_points) {
            wp_send_json_error(__('點數不足', 'wc-points-rewards'));
        }
        
        // 檢查購物車條件
        $calculator = WC_Points_Rewards_Points_Calculator::instance();
        $cart_total = WC()->cart->get_subtotal();
        
        if (!$calculator->can_use_points($cart_total, $points_to_use)) {
            wp_send_json_error(__('不符合點數使用條件', 'wc-points-rewards'));
        }
        
        // 應用點數折扣
        WC()->session->set('wc_points_rewards_discount_amount', $points_to_use);
        
        $this->log_security_event('points_applied', "用戶申請使用 {$points_to_use} 點數", $user_id);
        
        wp_send_json_success(array(
            'message' => sprintf(__('已使用 %s 點數', 'wc-points-rewards'), wc_points_rewards_number_format($points_to_use))
        ));
    }
    
    /**
     * AJAX: 移除點數使用
     */
    public function ajax_remove_points() {
        $this->verify_nonce();
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('請先登入', 'wc-points-rewards'));
        }
        
        // 移除點數折扣
        WC()->session->__unset('wc_points_rewards_discount_amount');
        
        $this->log_security_event('points_removed', "用戶移除點數使用", $user_id);
        
        wp_send_json_success(array(
            'message' => __('已移除點數使用', 'wc-points-rewards')
        ));
    }
    
    /**
     * AJAX: 管理員手動添加點數
     */
    public function ajax_admin_add_points() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('權限不足', 'wc-points-rewards'));
        }
        
        $this->verify_nonce('wc_points_rewards_admin_nonce');
        
        $user_id = $this->sanitize_input($_POST['user_id'], 'int');
        $points = $this->sanitize_input($_POST['points'], 'float');
        $reason = $this->sanitize_input($_POST['reason'], 'textarea');
        
        if (!$user_id || $points <= 0) {
            wp_send_json_error(__('參數錯誤', 'wc-points-rewards'));
        }
        
        $database = WC_Points_Rewards_Database::instance();
        
        // 計算過期時間
        $expiry_months = intval(get_option('wc_points_rewards_points_expiry_months', '12'));
        $expiry_date = date('Y-m-d H:i:s', strtotime("+{$expiry_months} months"));
        
        $result = $database->add_points(
            $user_id,
            $points,
            'admin',
            $reason ?: __('管理員手動添加', 'wc-points-rewards'),
            null,
            $expiry_date
        );
        
        if ($result) {
            $admin_user = wp_get_current_user();
            $this->log_security_event('admin_points_added', "管理員 {$admin_user->user_login} 為用戶 {$user_id} 添加 {$points} 點數", get_current_user_id());
            
            wp_send_json_success(array(
                'message' => sprintf(__('成功為用戶添加 %s 點數', 'wc-points-rewards'), wc_points_rewards_number_format($points))
            ));
        } else {
            wp_send_json_error(__('添加點數失敗', 'wc-points-rewards'));
        }
    }
    
    /**
     * AJAX: 管理員手動扣除點數
     */
    public function ajax_admin_deduct_points() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('權限不足', 'wc-points-rewards'));
        }
        
        $this->verify_nonce('wc_points_rewards_admin_nonce');
        
        $user_id = $this->sanitize_input($_POST['user_id'], 'int');
        $points = $this->sanitize_input($_POST['points'], 'float');
        $reason = $this->sanitize_input($_POST['reason'], 'textarea');
        
        if (!$user_id || $points <= 0) {
            wp_send_json_error(__('參數錯誤', 'wc-points-rewards'));
        }
        
        $database = WC_Points_Rewards_Database::instance();
        
        // 檢查用戶是否有足夠點數
        $available_points = $database->get_user_points($user_id);
        if ($points > $available_points) {
            wp_send_json_error(__('用戶點數不足', 'wc-points-rewards'));
        }
        
        $result = $database->add_points(
            $user_id,
            -$points, // 負數表示扣除
            'admin',
            $reason ?: __('管理員手動扣除', 'wc-points-rewards')
        );
        
        if ($result) {
            $admin_user = wp_get_current_user();
            $this->log_security_event('admin_points_deducted', "管理員 {$admin_user->user_login} 為用戶 {$user_id} 扣除 {$points} 點數", get_current_user_id());
            
            wp_send_json_success(array(
                'message' => sprintf(__('成功扣除用戶 %s 點數', 'wc-points-rewards'), wc_points_rewards_number_format($points))
            ));
        } else {
            wp_send_json_error(__('扣除點數失敗', 'wc-points-rewards'));
        }
    }
}