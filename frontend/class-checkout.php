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
        // 購物車頁面顯示點數使用選項 - 插入於購物車商品清單與總計區塊之間
        add_action('woocommerce_before_cart_totals', array($this, 'display_cart_points_section'));
        
        // 結帳頁面顯示點數使用選項 - 使用WooCommerce標準hooks
        add_action('woocommerce_review_order_after_order_total', array($this, 'display_checkout_points_section'));
        
        // 處理點數折扣 - 與WooCommerce費用系統整合
        add_action('woocommerce_cart_calculate_fees', array($this, 'apply_points_discount'));
        
        // 訂單完成時記錄點數使用 - 確保交易完整性
        add_action('woocommerce_checkout_order_processed', array($this, 'record_points_usage'), 10, 2);
        
        // AJAX 處理點數使用 - 提供即時互動體驗
        add_action('wp_ajax_wc_points_rewards_apply_discount', array($this, 'ajax_apply_points_discount'));
        add_action('wp_ajax_wc_points_rewards_remove_discount', array($this, 'ajax_remove_points_discount'));
        
        // 購物車更新時檢查點數使用 - 維持資料一致性
        add_action('woocommerce_cart_updated', array($this, 'validate_points_usage'));
    }
    
    /**
     * 在購物車顯示點數使用區塊 - 位於購物車商品清單與總計區塊之間
     */
    public function display_cart_points_section() {
        if (!is_user_logged_in()) {
            return;
        }
        
        // 只在購物車頁面顯示
        if (!is_cart()) {
            return;
        }
        
        echo '<div class="wc-points-cart-section-wrapper">';
        echo '<div class="cart-collaterals">';
        echo '<table class="shop_table shop_table_responsive">';
        $this->render_points_section('cart');
        echo '</table>';
        echo '</div>';
        echo '</div>';
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
        
        // 從設定中獲取參數，使用新的預設值
        $max_discount_percent = floatval(get_option('wc_points_rewards_max_discount_percent', 20));
        $min_cart_total = floatval(get_option('wc_points_rewards_min_cart_total', 500));
        
        $available_points = $database->get_user_points($user_id);
        $cart_total = WC()->cart->get_subtotal();
        
        // 檢查是否啟用點數系統
        $enable_points_system = get_option('wc_points_rewards_enable_points_system', 'yes');
        if ($enable_points_system !== 'yes') {
            return;
        }
        
        // 檢查購物車金額是否達到最低要求
        if ($cart_total < $min_cart_total) {
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
        
        if ($available_points <= 0) {
            echo '<tr class="points-no-balance">';
            echo '<td colspan="2">';
            echo '<div class="wc-points-message wc-points-info">';
            echo __('您目前沒有可用的點數', 'wc-points-rewards');
            echo '</div>';
            echo '</td>';
            echo '</tr>';
            return;
        }
        
        // 計算最大可使用點數（基於購物車金額和設定的百分比限制）
        $max_discount_amount = ($cart_total * $max_discount_percent) / 100;
        $points_value = floatval(get_option('wc_points_rewards_points_value', 0.01));
        $max_points_by_percentage = $max_discount_amount / $points_value;
        $max_points = min($available_points, $max_points_by_percentage);
        
        $current_discount = WC()->session->get('wc_points_rewards_discount_amount', 0);
        
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
        
        // 檢查購物車條件和折扣限制
        $cart_total = WC()->cart->get_subtotal();
        $min_cart_total = floatval(get_option('wc_points_rewards_min_cart_total', 500));
        $max_discount_percent = floatval(get_option('wc_points_rewards_max_discount_percent', 20));
        
        // 檢查最低購物車金額
        if ($cart_total < $min_cart_total) {
            wp_send_json_error(sprintf(__('購物車金額須滿 %s 才能使用點數', 'wc-points-rewards'), wc_price($min_cart_total)));
        }
        
        // 檢查最大折扣百分比限制
        $max_discount_amount = ($cart_total * $max_discount_percent) / 100;
        $points_value = floatval(get_option('wc_points_rewards_points_value', 0.01));
        $max_points_allowed = $max_discount_amount / $points_value;
        
        if ($points_to_use > $max_points_allowed) {
            wp_send_json_error(sprintf(__('本次訂單最多只能使用 %s 點數（最多折抵 %s%%）', 'wc-points-rewards'), 
                wc_points_rewards_number_format($max_points_allowed), 
                $max_discount_percent
            ));
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
            $available_points = $database->get_user_points($user_id);
            $cart_total = WC()->cart->get_subtotal();
            
            // 獲取設定
            $min_cart_total = floatval(get_option('wc_points_rewards_min_cart_total', 500));
            $max_discount_percent = floatval(get_option('wc_points_rewards_max_discount_percent', 20));
            $points_value = floatval(get_option('wc_points_rewards_points_value', 0.01));
            
            // 計算最大允許使用的點數
            $max_discount_amount = ($cart_total * $max_discount_percent) / 100;
            $max_points_allowed = $max_discount_amount / $points_value;
            
            // 檢查各種限制條件
            $should_remove = false;
            
            if ($discount_amount > $available_points) {
                $should_remove = true;
            } elseif ($cart_total < $min_cart_total) {
                $should_remove = true;
            } elseif ($discount_amount > $max_points_allowed) {
                $should_remove = true;
            }
            
            if ($should_remove) {
                WC()->session->__unset('wc_points_rewards_discount_amount');
                wc_add_notice(__('您的點數使用已自動調整', 'wc-points-rewards'), 'notice');
            }
        }
    }
}