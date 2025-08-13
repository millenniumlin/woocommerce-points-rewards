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
     * 初始化 hooks - 移除購物車點數相關功能
     */
    private function init_hooks() {
        // 🚀 購物車頁面點數使用功能已移除
        // add_action('woocommerce_cart_totals_after_order_total', array($this, 'display_cart_points_section'));
        
        // 🚀 結帳頁面點數使用功能已移除  
        // add_action('woocommerce_review_order_after_order_total', array($this, 'display_checkout_points_section'));
        
        // 🚀 點數折扣處理功能已移除
        // add_action('woocommerce_cart_calculate_fees', array($this, 'apply_points_discount'));
        
        // 訂單完成時記錄點數使用（保留，因為可能有其他地方需要）
        add_action('woocommerce_checkout_order_processed', array($this, 'record_points_usage'), 10, 2);
        
        // 🚀 AJAX 處理點數使用功能已移除
        // add_action('wp_ajax_wc_points_rewards_apply_discount', array($this, 'ajax_apply_points_discount'));
        // add_action('wp_ajax_wc_points_rewards_remove_discount', array($this, 'ajax_remove_points_discount'));
        
        // 🚀 購物車更新時檢查點數使用功能已移除
        // add_action('woocommerce_cart_updated', array($this, 'validate_points_usage'));
    }
    
    /**
     * 🚀 已移除：在購物車顯示點數使用區塊
     */
    public function display_cart_points_section() {
        // 功能已移除 - 不再在購物車顯示點數使用區塊
        return;
    }
    
    /**
     * 🚀 已移除：在結帳頁面顯示點數使用區塊
     */
    public function display_checkout_points_section() {
        // 功能已移除 - 不再在結帳頁面顯示點數使用區塊
        return;
    }
    
    /**
     * 🚀 已移除：渲染點數使用區塊
     */
    private function render_points_section($context = 'cart') {
        // 功能已移除 - 不再渲染點數使用區塊
        return;
    }
    
    /**
     * 🚀 已移除：應用點數折扣
     */
    public function apply_points_discount() {
        // 功能已移除 - 不再處理點數折扣
        return;
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
     * 🚀 已移除：AJAX 應用點數折扣
     */
    public function ajax_apply_points_discount() {
        // 功能已移除 - 不再處理 AJAX 點數折扣申請
        wp_send_json_error(__('點數折抵功能已停用', 'wc-points-rewards'));
    }
    
    /**
     * 🚀 已移除：AJAX 移除點數折扣
     */
    public function ajax_remove_points_discount() {
        // 功能已移除 - 不再處理 AJAX 點數折扣移除
        wp_send_json_error(__('點數折抵功能已停用', 'wc-points-rewards'));
    }
    
    /**
     * 🚀 已移除：驗證點數使用
     */
    public function validate_points_usage() {
        // 功能已移除 - 不再驗證點數使用
        return;
    }
}