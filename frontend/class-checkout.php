<?php
/**
 * 結帳頁面類別
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 結帳頁面類別
 */
class WC_Points_Rewards_Checkout {
    
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
        // 購物車頁面顯示點數使用選項 - 移動到總計上方，運送方式下方
        add_action('woocommerce_cart_totals_before_order_total', array($this, 'display_cart_points_section'));
        
        // 結帳頁面顯示點數使用選項 - 移動到小計上方
        add_action('woocommerce_review_order_after_cart_contents', array($this, 'display_checkout_points_section'));
        
        // 處理點數折扣
        add_action('woocommerce_cart_calculate_fees', array($this, 'apply_points_discount'));
        
        // 訂單完成時記錄點數使用
        add_action('woocommerce_checkout_order_processed', array($this, 'record_points_usage'), 10, 2);
        
        // AJAX 處理點數使用
        add_action('wp_ajax_wc_points_rewards_apply_discount', array($this, 'ajax_apply_points_discount'));
        add_action('wp_ajax_wc_points_rewards_remove_discount', array($this, 'ajax_remove_points_discount'));
        
        // 購物車更新時檢查點數使用
        add_action('woocommerce_cart_updated', array($this, 'validate_points_usage'));
    }
    
    /**
     * 在購物車顯示點數使用區塊
     */
    public function display_cart_points_section() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $this->render_points_section('cart');
    }
    
    /**
     * 在結帳頁面顯示點數使用區塊
     */
    public function display_checkout_points_section() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $this->render_points_section('checkout');
    }
    
    /**
     * 渲染點數使用區塊
     */
    private function render_points_section($context = 'cart') {
        $user_id = get_current_user_id();
        $database = WC_Points_Rewards_Database::instance();
        $calculator = WC_Points_Rewards_Points_Calculator::instance();
        
        // 檢查是否啟用購物車點數折抵
        $enable_cart_redemption = get_option('wc_points_rewards_enable_cart_redemption', 'yes');
        if ($enable_cart_redemption !== 'yes') {
            return;
        }
        
        $current_discount = WC()->session->get('wc_points_rewards_discount_amount', 0);
        
        // 在結帳頁面，只有當有使用點數時才顯示
        if ($context === 'checkout' && $current_discount <= 0) {
            return;
        }
        
        $available_points = $database->get_user_points($user_id);
        $cart_total = WC()->cart->get_subtotal();
        $min_cart_total = floatval(get_option('wc_points_rewards_min_cart_total', 0));
        
        // 檢查購物車金額是否達到最低要求 (僅購物車頁面)
        if ($context === 'cart' && $cart_total < $min_cart_total) {
            if ($min_cart_total > 0) {
                echo '<tr class="points-requirements">';
                echo '<td colspan="2">';
                echo '<div class="wc-points-message wc-points-info">';
                echo sprintf(__('購物車滿 %s 即可使用點數折抵', 'wc-points-rewards'), wc_price($min_cart_total));
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
            return;
        }
        
        if ($context === 'cart' && $available_points <= 0) {
            echo '<tr class="points-no-balance">';
            echo '<td colspan="2">';
            echo '<div class="wc-points-message wc-points-info">';
            echo __('您目前沒有可用的點數', 'wc-points-rewards');
            echo '</div>';
            echo '</td>';
            echo '</tr>';
            return;
        }
        
        // 計算最大可使用點數
        $max_discount_percent = floatval(get_option('wc_points_rewards_max_discount_percent', 50));
        $max_discount_amount = ($cart_total * $max_discount_percent) / 100;
        
        // 計算最大可用點數（考慮點數價值）
        $point_value = floatval(get_option('wc_points_rewards_points_value', 1));
        $max_points_by_amount = $max_discount_amount / $point_value;
        $max_points = min($available_points, $max_points_by_amount);
        
        // 傳遞變數到模板
        $max_usable_points = $max_points;
        $context_type = $context; // 傳遞上下文到模板
        
        include WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/views/cart-points-section.php';
    }
    
    /**
     * 應用點數折扣
     */
    public function apply_points_discount() {
        if (!is_admin() && !defined('DOING_AJAX')) {
            $discount_amount = WC()->session->get('wc_points_rewards_discount_amount', 0);
            
            if ($discount_amount > 0) {
                $calculator = WC_Points_Rewards_Points_Calculator::instance();
                $discount_value = $calculator->calculate_discount_amount($discount_amount);
                
                WC()->cart->add_fee(
                    __('點數折抵', 'wc-points-rewards'),
                    -$discount_value,
                    false
                );
            }
        }
    }
    
    /**
     * 記錄點數使用
     */
    public function record_points_usage($order_id, $posted_data) {
        $discount_amount = WC()->session->get('wc_points_rewards_discount_amount', 0);
        
        if ($discount_amount > 0) {
            $order = wc_get_order($order_id);
            $user_id = $order->get_user_id();
            
            if ($user_id) {
                $database = WC_Points_Rewards_Database::instance();
                $calculator = WC_Points_Rewards_Points_Calculator::instance();
                
                // 記錄點數使用
                $description = sprintf(__('訂單 #%s 使用點數折抵', 'wc-points-rewards'), $order->get_order_number());
                $database->add_points(
                    $user_id,
                    -$discount_amount, // 負數表示扣除
                    'redeemed',
                    $description,
                    $order_id
                );
                
                // 記錄到訂單 meta
                $discount_value = $calculator->calculate_discount_amount($discount_amount);
                update_post_meta($order_id, '_points_discount_amount', $discount_value);
                update_post_meta($order_id, '_points_used', $discount_amount);
                
                // 清除 session
                WC()->session->__unset('wc_points_rewards_discount_amount');
            }
        }
    }
    
    /**
     * AJAX: 應用點數折扣
     */
    public function ajax_apply_points_discount() {
        check_ajax_referer('wc_points_rewards_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('請先登入', 'wc-points-rewards'));
        }
        
        $points_to_use = floatval($_POST['points'] ?? 0);
        
        if ($points_to_use <= 0) {
            wp_send_json_error(__('請輸入有效的點數', 'wc-points-rewards'));
        }
        
        $user_id = get_current_user_id();
        $database = WC_Points_Rewards_Database::instance();
        $calculator = WC_Points_Rewards_Points_Calculator::instance();
        
        // 檢查用戶點數餘額
        $available_points = $database->get_user_points($user_id);
        if ($points_to_use > $available_points) {
            wp_send_json_error(__('點數不足', 'wc-points-rewards'));
        }
        
        // 檢查購物車條件
        $cart_total = WC()->cart->get_subtotal();
        if (!$calculator->can_use_points($cart_total, $points_to_use)) {
            wp_send_json_error(__('不符合點數使用條件', 'wc-points-rewards'));
        }
        
        // 應用折扣
        WC()->session->set('wc_points_rewards_discount_amount', $points_to_use);
        
        $discount_value = $calculator->calculate_discount_amount($points_to_use);
        
        wp_send_json_success(array(
            'message' => sprintf(__('已使用 %s 點數，折抵 %s', 'wc-points-rewards'), 
                wc_points_rewards_number_format($points_to_use), 
                wc_price($discount_value)
            ),
            'discount_amount' => $discount_value,
            'points_used' => $points_to_use
        ));
    }
    
    /**
     * AJAX: 移除點數折扣
     */
    public function ajax_remove_points_discount() {
        check_ajax_referer('wc_points_rewards_nonce', 'nonce');
        
        WC()->session->__unset('wc_points_rewards_discount_amount');
        
        wp_send_json_success(array(
            'message' => __('已移除點數折抵', 'wc-points-rewards')
        ));
    }
    
    /**
     * 驗證點數使用
     */
    public function validate_points_usage() {
        $discount_amount = WC()->session->get('wc_points_rewards_discount_amount', 0);
        
        if ($discount_amount > 0) {
            $user_id = get_current_user_id();
            if (!$user_id) {
                WC()->session->__unset('wc_points_rewards_discount_amount');
                return;
            }
            
            $database = WC_Points_Rewards_Database::instance();
            $calculator = WC_Points_Rewards_Points_Calculator::instance();
            
            $available_points = $database->get_user_points($user_id);
            $cart_total = WC()->cart->get_subtotal();
            
            // 如果點數不足或不符合條件，移除折扣
            if ($discount_amount > $available_points || !$calculator->can_use_points($cart_total, $discount_amount)) {
                WC()->session->__unset('wc_points_rewards_discount_amount');
                wc_add_notice(__('您的點數使用已自動調整', 'wc-points-rewards'), 'notice');
            }
        }
    }
}