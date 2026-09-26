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
        add_action('wp_ajax_wc_points_rewards_apply_points',       array($this, 'ajax_apply_points'));
        add_action('wp_ajax_wc_points_rewards_remove_points',      array($this, 'ajax_remove_points'));
        add_action('wp_ajax_wc_points_rewards_admin_add_points',   array($this, 'ajax_admin_add_points'));
        add_action('wp_ajax_wc_points_rewards_admin_deduct_points', array($this, 'ajax_admin_deduct_points'));
    }

    /**
     * 驗證 nonce
     *
     * [修正 SEC-5] 原本 AJAX 端點呼叫此方法時，失敗會觸發 wp_die() 回傳 HTML 頁面，
     * 前端 AJAX 解析 JSON 時會失敗，且不提供正確的 JSON 錯誤訊息。
     * 修正：改為回傳 JSON 格式的錯誤並終止執行，適合 AJAX 端點使用。
     *
     * @param string $action   Nonce action 名稱
     * @param bool   $is_ajax  是否為 AJAX 請求（true 時回傳 JSON，false 時 wp_die）
     */
    public function verify_nonce($action = 'wc_points_rewards_nonce', $is_ajax = true) {
        $nonce = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';

        if (!wp_verify_nonce($nonce, $action)) {
            if ($is_ajax || (defined('DOING_AJAX') && DOING_AJAX)) {
                // [修正 SEC-5] AJAX 請求回傳 JSON 格式錯誤，不用 wp_die HTML 頁面
                wp_send_json_error(
                    array(
                        'code'    => 'invalid_nonce',
                        'message' => __('安全驗證失敗，請重新整理頁面再試。', 'wc-points-rewards'),
                    ),
                    403
                );
            } else {
                wp_die(
                    esc_html__('安全驗證失敗', 'wc-points-rewards'),
                    esc_html__('安全驗證失敗', 'wc-points-rewards'),
                    array('response' => 403)
                );
            }
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
                return max(0, min($value, 2147483647));

            case 'float':
                $value = floatval($data);
                return max(0, min($value, 999999999.99));

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
            'timestamp'  => current_time('mysql'),
            'event_type' => sanitize_text_field($event_type),
            'description' => sanitize_textarea_field($description),
            'user_id'    => intval($user_id),
            'ip_address' => $this->get_user_ip(),
            'user_agent' => sanitize_text_field(isset($_SERVER['HTTP_USER_AGENT']) ? wp_unslash($_SERVER['HTTP_USER_AGENT']) : 'Unknown'),
        );

        error_log('WC Points Rewards Security: ' . wp_json_encode($log_data));

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
            'REMOTE_ADDR',
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '127.0.0.1';
    }

    /**
     * 檢查是否為可疑活動
     */
    public function is_suspicious_activity($user_id, $action_type) {
        $key   = "wc_points_rewards_activity_{$user_id}_{$action_type}";
        $count = get_transient($key);

        if ($count && $count > 10) {
            $this->log_security_event(
                'suspicious_activity',
                sprintf('用戶 %d 在短時間內進行過多 %s 操作', intval($user_id), sanitize_text_field($action_type)),
                $user_id
            );
            return true;
        }

        set_transient($key, ($count ? $count + 1 : 1), 600);

        return false;
    }

    /**
     * AJAX: 申請使用點數
     *
     * [修正 SEC-5] verify_nonce 失敗現在回傳 JSON 而非 HTML
     */
    public function ajax_apply_points() {
        $this->verify_nonce('wc_points_rewards_nonce', true);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('請先登入', 'wc-points-rewards'), 401);
        }

        if ($this->is_suspicious_activity($user_id, 'apply_points')) {
            wp_send_json_error(__('操作過於頻繁，請稍後再試', 'wc-points-rewards'), 429);
        }

        $points_to_use = $this->sanitize_input(isset($_POST['points']) ? $_POST['points'] : 0, 'float');

        if ($points_to_use <= 0) {
            wp_send_json_error(__('點數必須大於0', 'wc-points-rewards'));
        }

        $database         = WC_Points_Rewards_Database::instance();
        $available_points = $database->get_user_points($user_id);

        if ($points_to_use > $available_points) {
            wp_send_json_error(__('點數不足', 'wc-points-rewards'));
        }

        $calculator = WC_Points_Rewards_Points_Calculator::instance();
        $cart_total = WC()->cart->get_subtotal();

        if (!$calculator->can_use_points($cart_total, $points_to_use)) {
            wp_send_json_error(__('不符合點數使用條件', 'wc-points-rewards'));
        }

        WC()->session->set('wc_points_rewards_discount_amount', $points_to_use);

        $this->log_security_event('points_applied', sprintf('用戶申請使用 %s 點數', $points_to_use), $user_id);

        wp_send_json_success(array(
            'message' => sprintf(__('已使用 %s 點數', 'wc-points-rewards'), wc_points_rewards_number_format($points_to_use)),
        ));
    }

    /**
     * AJAX: 移除點數使用
     */
    public function ajax_remove_points() {
        $this->verify_nonce('wc_points_rewards_nonce', true);

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('請先登入', 'wc-points-rewards'), 401);
        }

        WC()->session->__unset('wc_points_rewards_discount_amount');

        $this->log_security_event('points_removed', '用戶移除點數使用', $user_id);

        wp_send_json_success(array(
            'message' => __('已移除點數使用', 'wc-points-rewards'),
        ));
    }

    /**
     * AJAX: 管理員手動添加點數
     */
    public function ajax_admin_add_points() {
        if (!wc_points_rewards_is_site_administrator()) {
            wp_send_json_error(array(
                'code'    => 'forbidden',
                'message' => __('權限不足', 'wc-points-rewards'),
            ), 403);
        }

        $this->verify_nonce('wc_points_rewards_admin_nonce', true);

        $user_id = $this->sanitize_input(isset($_POST['user_id']) ? $_POST['user_id'] : 0, 'int');
        $points  = $this->sanitize_input(isset($_POST['points']) ? $_POST['points'] : 0, 'float');
        $reason  = $this->sanitize_input(isset($_POST['reason']) ? $_POST['reason'] : '', 'textarea');

        $manager = WC_Points_Rewards_Admin_Points_Manager::instance();
        $result  = $manager->create_manual_grant($user_id, $points, $reason, get_current_user_id());

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'code'    => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ));
        }

        wp_send_json_success(array(
            'message' => sprintf(
                __('成功為 %1$s 補發 %2$s，目前餘額為 %3$s。', 'wc-points-rewards'),
                $result['target_user']->display_name,
                wc_points_rewards_number_format($result['points']),
                wc_points_rewards_number_format($result['balance'])
            ),
            'balance' => $result['balance'],
            'usage'   => $result['usage'],
        ));
    }

    /**
     * AJAX: 管理員手動扣除點數
     */
    public function ajax_admin_deduct_points() {
        if (!wc_points_rewards_is_site_administrator()) {
            wp_send_json_error(array(
                'code'    => 'forbidden',
                'message' => __('權限不足', 'wc-points-rewards'),
            ), 403);
        }

        $this->verify_nonce('wc_points_rewards_admin_nonce', true);

        $user_id = $this->sanitize_input(isset($_POST['user_id']) ? $_POST['user_id'] : 0, 'int');
        $points  = $this->sanitize_input(isset($_POST['points']) ? $_POST['points'] : 0, 'float');
        $reason  = $this->sanitize_input(isset($_POST['reason']) ? $_POST['reason'] : '', 'textarea');

        $manager = WC_Points_Rewards_Admin_Points_Manager::instance();
        $result  = $manager->deduct_points($user_id, $points, $reason, get_current_user_id());

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'code'    => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ));
        }

        wp_send_json_success(array(
            'message' => sprintf(
                __('成功扣除 %1$s 的 %2$s，目前餘額為 %3$s。', 'wc-points-rewards'),
                $result['target_user']->display_name,
                wc_points_rewards_number_format($result['points']),
                wc_points_rewards_number_format($result['balance'])
            ),
            'balance' => $result['balance'],
        ));
    }
}
