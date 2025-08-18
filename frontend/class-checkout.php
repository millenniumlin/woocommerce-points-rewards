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
     * WooCommerce 相容性實例
     */
    private $wc_compatibility;
    
    /**
     * 建構函式
     */
    public function __construct() {
        $this->wc_compatibility = WC_Points_Rewards_WooCommerce_Compatibility::instance();
        $this->init_hooks();
    }
    
    /**
     * 初始化 hooks
     */
    private function init_hooks() {
        // 使用相容性類別取得適當的 hooks
        $cart_hooks = $this->wc_compatibility->get_cart_hooks();
        $checkout_hooks = $this->wc_compatibility->get_checkout_hooks();
        
        // 購物車頁面顯示點數使用選項 - 支援多個 hooks
        foreach ($cart_hooks as $hook_name => $hook) {
            add_action($hook, array($this, 'display_cart_points_section'), 20);
        }
        
        // 結帳頁面顯示點數使用選項 - 支援多個 hooks
        foreach ($checkout_hooks as $hook_name => $hook) {
            add_action($hook, array($this, 'display_checkout_points_section'), 20);
        }
        
        // 處理點數折扣 - 使用相容性檢查
        add_action('woocommerce_cart_calculate_fees', array($this, 'apply_points_discount'));
        
        // 訂單完成時記錄點數使用
        add_action('woocommerce_checkout_order_processed', array($this, 'record_points_usage'), 10, 2);
        
        // AJAX 處理點數使用 - 確保相容性
        $this->wc_compatibility->handle_ajax_compatibility();
        add_action('wp_ajax_wc_points_rewards_apply_discount', array($this, 'ajax_apply_points_discount'));
        add_action('wp_ajax_nopriv_wc_points_rewards_apply_discount', array($this, 'ajax_apply_points_discount'));
        add_action('wp_ajax_wc_points_rewards_remove_discount', array($this, 'ajax_remove_points_discount'));
        add_action('wp_ajax_nopriv_wc_points_rewards_remove_discount', array($this, 'ajax_remove_points_discount'));
        
        // 購物車更新時檢查點數使用
        add_action('woocommerce_cart_updated', array($this, 'validate_points_usage'));
        
        // 載入前端資源
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }
    
    /**
     * 載入前端資源
     */
    public function enqueue_frontend_assets() {
        // 只在需要的頁面載入
        if (!$this->wc_compatibility->is_cart() && !$this->wc_compatibility->is_checkout()) {
            return;
        }
        
        // 載入 CSS
        wp_enqueue_style(
            'wc-points-rewards-frontend',
            WC_POINTS_REWARDS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            WC_POINTS_REWARDS_VERSION
        );
        
        // 載入 JavaScript
        wp_enqueue_script(
            'wc-points-rewards-frontend',
            WC_POINTS_REWARDS_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            WC_POINTS_REWARDS_VERSION,
            true
        );
        
        // 本地化腳本
        wp_localize_script('wc-points-rewards-frontend', 'wcPointsRewards', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_points_rewards_nonce'),
            'wcVersion' => $this->wc_compatibility->get_version(),
            'isCart' => $this->wc_compatibility->is_cart(),
            'isCheckout' => $this->wc_compatibility->is_checkout(),
            'messages' => array(
                'loading' => __('載入中...', 'wc-points-rewards'),
                'error' => __('發生錯誤，請稍後再試', 'wc-points-rewards'),
                'success' => __('操作成功', 'wc-points-rewards'),
                'insufficient_points' => __('點數不足', 'wc-points-rewards'),
                'invalid_amount' => __('請輸入有效的點數', 'wc-points-rewards'),
                'apply_points' => __('使用點數', 'wc-points-rewards'),
                'remove_points' => __('取消使用', 'wc-points-rewards'),
                'processing' => __('處理中...', 'wc-points-rewards')
            )
        ));
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
        
        $available_points = $database->get_user_points($user_id);
        $cart_total = $this->wc_compatibility->get_cart_subtotal();
        $min_cart_total = floatval(get_option('wc_points_rewards_min_cart_total', 0));
        
        // 檢查購物車金額是否達到最低要求
        if ($cart_total < $min_cart_total) {
            if ($min_cart_total > 0) {
                echo '<tr class="points-requirements">';
                echo '<td colspan="2">';
                echo '<div class="wc-points-message wc-points-info">';
                echo sprintf(__('購物車滿 %s 即可使用點數折抵', 'wc-points-rewards'), 
                    $this->wc_compatibility->format_price($min_cart_total));
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
        
        // 計算最大可使用點數
        $max_discount_percent = floatval(get_option('wc_points_rewards_max_discount_percent', 50));
        $max_discount_amount = ($cart_total * $max_discount_percent) / 100;
        
        // 計算最大可用點數（考慮點數價值）
        $point_value = floatval(get_option('wc_points_rewards_points_value', 1));
        $max_points_by_amount = $max_discount_amount / $point_value;
        $max_points = min($available_points, $max_points_by_amount);
        
        $current_discount = WC()->session->get('wc_points_rewards_discount_amount', 0);
        
        // 傳遞變數到模板
        $max_usable_points = $max_points;
        
        // 使用增強版模板
        include WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/views/cart-points-section-enhanced.php';
    }
    
    /**
     * 應用點數折扣
     */
    public function apply_points_discount() {
        if (!is_admin() && !defined('DOING_AJAX')) {
            $session = $this->wc_compatibility->get_session();
            if (!$session) {
                return;
            }
            
            $discount_amount = $session->get('wc_points_rewards_discount_amount', 0);
            
            if ($discount_amount > 0) {
                $calculator = WC_Points_Rewards_Points_Calculator::instance();
                $discount_value = $calculator->calculate_discount_amount($discount_amount);
                
                // 使用相容性方法添加費用
                $this->wc_compatibility->add_cart_fee(
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
        
        // 使用相容性方法檢查購物車條件
        $cart_total = $this->wc_compatibility->get_cart_subtotal();
        if (!$calculator->can_use_points($cart_total, $points_to_use)) {
            wp_send_json_error(__('不符合點數使用條件', 'wc-points-rewards'));
        }
        
        // 使用相容性方法應用折扣
        $session = $this->wc_compatibility->get_session();
        if (!$session) {
            wp_send_json_error(__('無法建立工作階段', 'wc-points-rewards'));
        }
        
        $session->set('wc_points_rewards_discount_amount', $points_to_use);
        
        $discount_value = $calculator->calculate_discount_amount($points_to_use);
        
        wp_send_json_success(array(
            'message' => sprintf(__('已使用 %s 點數，折抵 %s', 'wc-points-rewards'), 
                wc_points_rewards_number_format($points_to_use), 
                $this->wc_compatibility->format_price($discount_value)
            ),
            'discount_amount' => $discount_value,
            'points_used' => $points_to_use,
            'wc_version' => $this->wc_compatibility->get_version()
        ));
    }
    
    /**
     * AJAX: 移除點數折扣
     */
    public function ajax_remove_points_discount() {
        check_ajax_referer('wc_points_rewards_nonce', 'nonce');
        
        $session = $this->wc_compatibility->get_session();
        if ($session) {
            $session->__unset('wc_points_rewards_discount_amount');
        }
        
        wp_send_json_success(array(
            'message' => __('已移除點數折抵', 'wc-points-rewards'),
            'wc_version' => $this->wc_compatibility->get_version()
        ));
    }
    
    /**
     * 驗證點數使用
     */
    public function validate_points_usage() {
        $session = $this->wc_compatibility->get_session();
        if (!$session) {
            return;
        }
        
        $discount_amount = $session->get('wc_points_rewards_discount_amount', 0);
        
        if ($discount_amount > 0) {
            $user_id = get_current_user_id();
            if (!$user_id) {
                $session->__unset('wc_points_rewards_discount_amount');
                return;
            }
            
            $database = WC_Points_Rewards_Database::instance();
            $calculator = WC_Points_Rewards_Points_Calculator::instance();
            
            $available_points = $database->get_user_points($user_id);
            $cart_total = $this->wc_compatibility->get_cart_subtotal();
            
            // 如果點數不足或不符合條件，移除折扣
            if ($discount_amount > $available_points || !$calculator->can_use_points($cart_total, $discount_amount)) {
                $session->__unset('wc_points_rewards_discount_amount');
                
                // 顯示通知（使用相容的方法）
                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('您的點數使用已自動調整', 'wc-points-rewards'), 'notice');
                }
            }
        }
    }
}