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
        // 購物車頁面顯示點數使用選項 - 移動到 order total 之前
        add_action('woocommerce_cart_totals_before_order_total', array($this, 'display_cart_points_section'));
        
        // 結帳頁面顯示點數使用選項 - 移動到 order total 之前
        add_action('woocommerce_review_order_before_order_total', array($this, 'display_checkout_points_section'));
        
        // 處理點數折扣
        add_action('woocommerce_cart_calculate_fees', array($this, 'apply_points_discount'));
        
        // 訂單完成時記錄點數使用
        add_action('woocommerce_checkout_order_processed', array($this, 'record_points_usage'), 10, 2);
        
        // AJAX 處理點數使用
        add_action('wp_ajax_wc_points_rewards_apply_discount', array($this, 'ajax_apply_points_discount'));
        add_action('wp_ajax_wc_points_rewards_remove_discount', array($this, 'ajax_remove_points_discount'));
        
        // 為非登入用戶提供AJAX端點（雖然會返回錯誤，但避免404）
        add_action('wp_ajax_nopriv_wc_points_rewards_apply_discount', array($this, 'ajax_apply_points_discount'));
        add_action('wp_ajax_nopriv_wc_points_rewards_remove_discount', array($this, 'ajax_remove_points_discount'));
        
        // 添加測試端點用於調試
        add_action('wp_ajax_wc_points_rewards_test_system', array($this, 'ajax_test_system'));
        add_action('wp_ajax_nopriv_wc_points_rewards_test_system', array($this, 'ajax_test_system'));
        
        // 購物車更新時檢查點數使用
        add_action('woocommerce_cart_updated', array($this, 'validate_points_usage'));
        
        // 確保在購物車計算時應用點數折扣
        add_action('woocommerce_after_calculate_totals', array($this, 'ensure_discount_applied'));
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
        
        // 獲取所有設定一次，避免重複查詢
        $settings = get_option('wc_points_rewards_settings', array());
        
        // 檢查是否啟用點數系統
        if (!wc_points_rewards_is_enabled()) {
            return;
        }
        
        // 檢查是否啟用購物車點數折抵
        $settings = get_option('wc_points_rewards_settings', array());
        $enable_cart_redemption = isset($settings['enable_cart_redemption']) ? $settings['enable_cart_redemption'] : 'yes';
        if ($enable_cart_redemption !== 'yes') {
            return;
        }
        
        // 結帳頁面只顯示已使用的點數，不提供編輯功能
        if ($context === 'checkout') {
            include WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/views/checkout-points-section.php';
            return;
        }
        
        // 以下是購物車頁面的邏輯
        $available_points = $database->get_user_points($user_id);
        $cart_total = WC()->cart->get_subtotal();
        $min_cart_total = isset($settings['min_cart_total']) ? floatval($settings['min_cart_total']) : 0;
        
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
        
        // 計算最大可使用點數
        $max_discount_percent = isset($settings['max_discount_percent']) ? floatval($settings['max_discount_percent']) : 100;
        $max_discount_amount = ($cart_total * $max_discount_percent) / 100;
        
        // 計算最大可用點數（考慮點數價值）
        $point_value = wc_points_rewards_get_points_value();
        $max_points_by_amount = $max_discount_amount / $point_value;
        $max_points = min($available_points, $max_points_by_amount);
        
        $current_discount = WC()->session->get('wc_points_rewards_discount_amount', 0);
        
        // 傳遞變數到模板
        $max_usable_points = $max_points;
        
        include WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/views/cart-points-section.php';
    }
    
    /**
     * 應用點數折扣
     */
    public function apply_points_discount() {
        // 修正：移除 DOING_AJAX 限制，確保在 AJAX 請求中也能應用折扣
        if (!is_admin()) {
            $discount_amount = WC()->session->get('wc_points_rewards_discount_amount', 0);
            
            if ($discount_amount > 0) {
                $calculator = WC_Points_Rewards_Points_Calculator::instance();
                $discount_value = $calculator->calculate_discount_amount($discount_amount);
                
                // 檢查是否已經添加了此折扣，避免重複添加
                $fees = WC()->cart->get_fees();
                $discount_already_applied = false;
                
                foreach ($fees as $fee) {
                    if ($fee->name === __('點數折抵', 'wc-points-rewards')) {
                        $discount_already_applied = true;
                        break;
                    }
                }
                
                // 只有在未添加折扣時才添加
                if (!$discount_already_applied) {
                    WC()->cart->add_fee(
                        __('點數折抵', 'wc-points-rewards'),
                        -$discount_value,
                        false
                    );
                }
            }
        }
    }
    
    /**
     * 記錄點數使用
     */
    public function record_points_usage($order_id, $posted_data) {
        error_log('WC Points Rewards: record_points_usage called for order ' . $order_id);
        
        $discount_amount = WC()->session->get('wc_points_rewards_discount_amount', 0);
        error_log('WC Points Rewards: Session discount amount - ' . $discount_amount);
        
        if ($discount_amount > 0) {
            $order = wc_get_order($order_id);
            $user_id = $order->get_user_id();
            error_log('WC Points Rewards: Order user ID - ' . $user_id);
            
            if ($user_id) {
                $database = WC_Points_Rewards_Database::instance();
                $calculator = WC_Points_Rewards_Points_Calculator::instance();
                
                // 記錄點數使用
                $description = sprintf(__('訂單 #%s 使用點數折抵', 'wc-points-rewards'), $order->get_order_number());
                
                $points_added = $database->add_points(
                    $user_id,
                    -$discount_amount, // 負數表示扣除
                    'redeemed',
                    $description,
                    $order_id
                );
                
                if ($points_added) {
                    error_log(sprintf('WC Points Rewards: 成功記錄點數扣除 - 用戶ID: %d, 點數: %s, 訂單: %s', 
                        $user_id, $discount_amount, $order_id));
                } else {
                    error_log(sprintf('WC Points Rewards: 點數扣除記錄失敗 - 用戶ID: %d, 點數: %s, 訂單: %s', 
                        $user_id, $discount_amount, $order_id));
                }
                
                // 記錄到訂單 meta
                $discount_value = $calculator->calculate_discount_amount($discount_amount);
                update_post_meta($order_id, '_points_discount_amount', $discount_value);
                update_post_meta($order_id, '_points_used', $discount_amount);
                
                error_log(sprintf('WC Points Rewards: 訂單 meta 已更新 - 折抵金額: %s, 使用點數: %s', 
                    $discount_value, $discount_amount));
                
                // 清除 session
                WC()->session->__unset('wc_points_rewards_discount_amount');
                error_log('WC Points Rewards: Session 已清除');
            } else {
                error_log('WC Points Rewards: 訂單沒有關聯的用戶ID');
            }
        } else {
            error_log('WC Points Rewards: 沒有點數折扣需要記錄');
        }
    }
    
    /**
     * AJAX: 應用點數折扣
     */
    public function ajax_apply_points_discount() {
        // 增強的錯誤日誌記錄
        error_log('WC Points Rewards: AJAX apply discount started');
        error_log('WC Points Rewards: POST data - ' . print_r($_POST, true));
        
        // 確保 WooCommerce 已載入
        if (!function_exists('WC') || !WC()) {
            error_log('WC Points Rewards: WooCommerce not loaded during AJAX');
            wp_send_json_error(__('購物車系統尚未載入，請稍後再試', 'wc-points-rewards'));
        }
        
        // 初始化 WooCommerce 會話（如果需要）
        if (WC()->session && !WC()->session->get_session_cookie()) {
            WC()->session->set_customer_session_cookie(true);
        }
        
        try {
            check_ajax_referer('wc_points_rewards_nonce', 'nonce');
        } catch (Exception $e) {
            error_log('WC Points Rewards: Nonce驗證失敗 - ' . $e->getMessage());
            wp_send_json_error(__('安全驗證失敗，請重新整理頁面再試', 'wc-points-rewards'));
        }
        
        if (!is_user_logged_in()) {
            error_log('WC Points Rewards: 用戶未登入');
            wp_send_json_error(__('請先登入', 'wc-points-rewards'));
        }
        
        $points_to_use = floatval($_POST['points'] ?? 0);
        error_log('WC Points Rewards: Points to use - ' . $points_to_use);
        
        if ($points_to_use <= 0) {
            error_log('WC Points Rewards: 無效的點數值 - ' . $points_to_use);
            wp_send_json_error(__('請輸入有效的點數', 'wc-points-rewards'));
        }
        
        try {
            $user_id = get_current_user_id();
            error_log('WC Points Rewards: User ID - ' . $user_id);
            
            // 檢查必要的類別是否存在
            if (!class_exists('WC_Points_Rewards_Database')) {
                error_log('WC Points Rewards: Database class missing');
                wp_send_json_error(__('點數系統暫時無法使用', 'wc-points-rewards'));
            }
            
            if (!class_exists('WC_Points_Rewards_Points_Calculator')) {
                error_log('WC Points Rewards: Calculator class missing');
                wp_send_json_error(__('點數系統暫時無法使用', 'wc-points-rewards'));
            }
            
            $database = WC_Points_Rewards_Database::instance();
            $calculator = WC_Points_Rewards_Points_Calculator::instance();
            
            // 檢查 WooCommerce 購物車是否可用
            if (!WC()->cart) {
                error_log('WC Points Rewards: WooCommerce cart not available');
                wp_send_json_error(__('購物車暫時無法使用', 'wc-points-rewards'));
            }
            
            // 檢查用戶點數餘額
            $available_points = $database->get_user_points($user_id);
            error_log('WC Points Rewards: Available points - ' . $available_points);
            
            if ($points_to_use > $available_points) {
                error_log('WC Points Rewards: 點數不足 - 需要:' . $points_to_use . ', 可用:' . $available_points);
                wp_send_json_error(__('點數不足', 'wc-points-rewards'));
            }
            
            // 檢查購物車條件（管理員可以跳過限制）
            $cart_total = WC()->cart->get_subtotal();
            error_log('WC Points Rewards: Cart total - ' . $cart_total);
            
            $can_use_points = $calculator->can_use_points($cart_total, $points_to_use);
            error_log('WC Points Rewards: Can use points - ' . ($can_use_points ? 'yes' : 'no'));
            
            // 如果是管理員，允許跳過某些限制
            if (!$can_use_points && current_user_can('manage_woocommerce')) {
                $settings = get_option('wc_points_rewards_settings', array());
                $allow_admin_override = isset($settings['allow_admin_override']) && $settings['allow_admin_override'] === 'yes';
                
                if ($allow_admin_override) {
                    $can_use_points = true;
                    error_log(sprintf('WC Points Rewards: 管理員覆蓋點數限制 - 用戶ID: %d, 點數: %s', $user_id, $points_to_use));
                }
            }
            
            if (!$can_use_points) {
                // 提供更詳細的錯誤信息
                $settings = get_option('wc_points_rewards_settings', array());
                $min_cart_total = isset($settings['min_cart_total']) ? floatval($settings['min_cart_total']) : 0;
                $max_discount_percent = isset($settings['max_discount_percent']) ? floatval($settings['max_discount_percent']) : 100;
                $discount_amount = $calculator->calculate_discount_amount($points_to_use);
                $max_discount_amount = ($cart_total * $max_discount_percent) / 100;
                
                $error_message = __('不符合點數使用條件', 'wc-points-rewards');
                
                if ($cart_total < $min_cart_total) {
                    $error_message = sprintf(__('購物車金額須達 %s 才能使用點數', 'wc-points-rewards'), wc_price($min_cart_total));
                } elseif ($discount_amount > $max_discount_amount) {
                    $error_message = sprintf(__('最多只能折抵 %s%% 的金額（%s）', 'wc-points-rewards'), $max_discount_percent, wc_price($max_discount_amount));
                }
                
                // 記錄錯誤日誌供調試
                error_log(sprintf('WC Points Rewards: 點數使用被拒絕 - 用戶ID: %d, 點數: %s, 購物車總額: %s, 最低要求: %s, 最大折抵: %s%%, 嘗試折抵: %s', 
                    $user_id, $points_to_use, $cart_total, $min_cart_total, $max_discount_percent, $discount_amount));
                
                wp_send_json_error($error_message);
            }
            
            // 應用折扣
            WC()->session->set('wc_points_rewards_discount_amount', $points_to_use);
            error_log('WC Points Rewards: Session discount amount set - ' . $points_to_use);
            
            // 立即嘗試應用折扣到購物車
            $discount_value = $calculator->calculate_discount_amount($points_to_use);
            error_log('WC Points Rewards: Calculated discount value - ' . $discount_value);
            
            // 檢查是否已經有相同的費用，避免重複添加
            $fees = WC()->cart->get_fees();
            $discount_found = false;
            
            foreach ($fees as $fee) {
                if ($fee->name === __('點數折抵', 'wc-points-rewards')) {
                    $discount_found = true;
                    error_log('WC Points Rewards: Existing discount found');
                    break;
                }
            }
            
            // 如果還沒有折扣費用，立即添加
            if (!$discount_found) {
                WC()->cart->add_fee(
                    __('點數折抵', 'wc-points-rewards'),
                    -$discount_value,
                    false
                );
                error_log('WC Points Rewards: Fee added to cart - ' . (-$discount_value));
            }
            
            // 強制重新計算購物車總計以確保折扣立即生效
            if (WC()->cart) {
                WC()->cart->calculate_totals();
                error_log('WC Points Rewards: Cart totals recalculated');
            }
            
            // 記錄成功應用點數的日誌
            error_log(sprintf('WC Points Rewards: 成功應用點數 - 用戶ID: %d, 點數: %s, 折抵金額: %s', 
                $user_id, $points_to_use, $discount_value));
            
            wp_send_json_success(array(
                'message' => sprintf(__('已使用 %s 點數，折抵 %s', 'wc-points-rewards'), 
                    wc_points_rewards_number_format($points_to_use), 
                    wc_price($discount_value)
                ),
                'discount_amount' => $discount_value,
                'points_used' => $points_to_use,
                'reload_cart' => true // 指示前端需要重新載入購物車
            ));
            
        } catch (Exception $e) {
            error_log('WC Points Rewards: AJAX處理錯誤 - ' . $e->getMessage());
            error_log('WC Points Rewards: 錯誤堆疊 - ' . $e->getTraceAsString());
            wp_send_json_error(__('系統發生錯誤，請稍後再試或聯繫管理員', 'wc-points-rewards'));
        }
    }
    
    /**
     * AJAX: 移除點數折扣
     */
    public function ajax_remove_points_discount() {
        check_ajax_referer('wc_points_rewards_nonce', 'nonce');
        
        WC()->session->__unset('wc_points_rewards_discount_amount');
        
        // 強制重新計算購物車總計以確保折扣立即移除
        if (WC()->cart) {
            WC()->cart->calculate_totals();
        }
        
        wp_send_json_success(array(
            'message' => __('已移除點數折抵', 'wc-points-rewards'),
            'reload_cart' => true // 指示前端需要重新載入購物車
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
    
    /**
     * 確保在購物車計算後點數折扣有被正確應用
     */
    public function ensure_discount_applied() {
        // 這個方法在購物車計算完成後確保點數折扣被正確顯示
        $discount_amount = WC()->session->get('wc_points_rewards_discount_amount', 0);
        
        if ($discount_amount > 0 && WC()->cart) {
            $calculator = WC_Points_Rewards_Points_Calculator::instance();
            $discount_value = $calculator->calculate_discount_amount($discount_amount);
            
            // 檢查折扣是否已經在費用中
            $fees = WC()->cart->get_fees();
            $discount_found = false;
            
            foreach ($fees as $fee) {
                if ($fee->name === __('點數折抵', 'wc-points-rewards')) {
                    $discount_found = true;
                    // 檢查金額是否正確
                    if (abs($fee->amount + $discount_value) > 0.01) {
                        // 金額不正確，移除舊的費用並重新添加
                        WC()->cart->fees = array_filter(WC()->cart->fees, function($existing_fee) {
                            return $existing_fee->name !== __('點數折抵', 'wc-points-rewards');
                        });
                        WC()->cart->add_fee(__('點數折抵', 'wc-points-rewards'), -$discount_value, false);
                    }
                    break;
                }
            }
            
            // 如果折扣不存在，添加它
            if (!$discount_found) {
                WC()->cart->add_fee(__('點數折抵', 'wc-points-rewards'), -$discount_value, false);
            }
        }
    }
    
    /**
     * AJAX: 測試系統狀態（調試用）
     */
    public function ajax_test_system() {
        $tests = array();
        
        // 測試 WordPress 函數
        $tests['wordpress'] = array(
            'wp_create_nonce' => function_exists('wp_create_nonce'),
            'check_ajax_referer' => function_exists('check_ajax_referer'),
            'wp_send_json_error' => function_exists('wp_send_json_error'),
            'is_user_logged_in' => function_exists('is_user_logged_in'),
            'get_current_user_id' => function_exists('get_current_user_id'),
        );
        
        // 測試 WooCommerce 函數
        $tests['woocommerce'] = array(
            'WC_function' => function_exists('WC'),
            'WC_instance' => (function_exists('WC') && WC()),
            'WC_cart' => (function_exists('WC') && WC() && WC()->cart),
            'WC_session' => (function_exists('WC') && WC() && WC()->session),
            'wc_price' => function_exists('wc_price'),
        );
        
        // 測試外掛類別
        $tests['plugin_classes'] = array(
            'WC_Points_Rewards_Database' => class_exists('WC_Points_Rewards_Database'),
            'WC_Points_Rewards_Points_Calculator' => class_exists('WC_Points_Rewards_Points_Calculator'),
            'WC_Points_Rewards_Checkout' => class_exists('WC_Points_Rewards_Checkout'),
        );
        
        // 測試設定
        $settings = get_option('wc_points_rewards_settings', array());
        $tests['settings'] = array(
            'settings_exist' => !empty($settings),
            'enable_points_system' => isset($settings['enable_points_system']) ? $settings['enable_points_system'] : 'not_set',
            'enable_cart_redemption' => isset($settings['enable_cart_redemption']) ? $settings['enable_cart_redemption'] : 'not_set',
            'points_value' => isset($settings['points_value']) ? $settings['points_value'] : 'not_set',
        );
        
        // 測試用戶狀態
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $tests['user'] = array(
                'logged_in' => true,
                'user_id' => $user_id,
                'can_manage_woocommerce' => current_user_can('manage_woocommerce'),
            );
            
            // 測試點數資料庫
            if (class_exists('WC_Points_Rewards_Database')) {
                $database = WC_Points_Rewards_Database::instance();
                $tests['user']['points_balance'] = $database->get_user_points($user_id);
            }
        } else {
            $tests['user'] = array(
                'logged_in' => false,
            );
        }
        
        // 測試 AJAX 動作註冊
        global $wp_filter;
        $tests['ajax_hooks'] = array(
            'apply_discount' => isset($wp_filter['wp_ajax_wc_points_rewards_apply_discount']),
            'remove_discount' => isset($wp_filter['wp_ajax_wc_points_rewards_remove_discount']),
        );
        
        wp_send_json_success($tests);
    }
}