<?php
/**
 * WooCommerce Points & Rewards - Functions.php 點數折抵區塊代碼
 * 
 * 此代碼可以直接放置於佈景主題的 functions.php 檔案中
 * 提供購物車和結帳頁面的點數折抵功能
 * 
 * @package WC_Points_Rewards
 * @version 1.0.0
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 點數折抵區塊類別 - 用於 functions.php
 */
class WC_Points_Rewards_Functions_Block {
    
    /**
     * 初始化
     */
    public static function init() {
        // 確保 WooCommerce 和點數外掛已載入
        if (!class_exists('WooCommerce') || !class_exists('WC_Points_Rewards_Database')) {
            return;
        }
        
        // 註冊 hooks - 購物車頁面顯示在商品清單和總計之間
        add_action('woocommerce_cart_totals_before_order_total', array(__CLASS__, 'display_cart_points_section'));
        add_action('woocommerce_review_order_after_order_total', array(__CLASS__, 'display_checkout_points_section'));
        
        // 處理點數折扣
        add_action('woocommerce_cart_calculate_fees', array(__CLASS__, 'apply_points_discount'));
        
        // AJAX 處理
        add_action('wp_ajax_wc_points_functions_apply_discount', array(__CLASS__, 'ajax_apply_points_discount'));
        add_action('wp_ajax_wc_points_functions_remove_discount', array(__CLASS__, 'ajax_remove_points_discount'));
        
        // 訂單完成時記錄點數使用
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'record_points_usage'), 10, 2);
        
        // 載入樣式和腳本
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        
        // 購物車更新時檢查點數使用
        add_action('woocommerce_cart_updated', array(__CLASS__, 'validate_points_usage'));
    }
    
    /**
     * 載入樣式和腳本
     */
    public static function enqueue_scripts() {
        if (is_cart() || is_checkout()) {
            wp_enqueue_script('jquery');
            
            // 確保有 CSS 樣式可以附加到
            if (!wp_style_is('woocommerce-general', 'enqueued')) {
                wp_enqueue_style('woocommerce-general');
            }
            
            // 內聯 CSS
            wp_add_inline_style('woocommerce-general', self::get_inline_css());
            
            // 內聯 JavaScript
            wp_add_inline_script('jquery', self::get_inline_js());
            
            // 本地化腳本
            wp_localize_script('jquery', 'wcPointsFunctions', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_points_functions_nonce'),
                'messages' => array(
                    'processing' => __('處理中...', 'wc-points-rewards'),
                    'usePoints' => __('使用點數', 'wc-points-rewards'),
                    'removePoints' => __('取消使用', 'wc-points-rewards'),
                    'invalidPoints' => __('請輸入有效的點數', 'wc-points-rewards'),
                    'error' => __('發生錯誤，請稍後再試', 'wc-points-rewards')
                )
            ));
        }
    }
    
    /**
     * 取得內聯 CSS
     */
    private static function get_inline_css() {
        return '
        .wc-points-functions-wrapper {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .wc-points-functions-wrapper h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
        }
        .points-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        .points-info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .points-label {
            font-weight: 600;
            color: #555;
        }
        .points-value {
            font-weight: bold;
            color: #0073aa;
        }
        .points-value.discount-value {
            color: #d63638;
        }
        .points-input-section {
            margin: 15px 0;
        }
        .points-input-wrapper {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .points-input-wrapper input[type="number"] {
            width: 120px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .points-quick-options {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        .points-quick-use {
            background: none;
            border: 1px solid #0073aa;
            color: #0073aa;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
        }
        .points-quick-use:hover {
            background-color: #0073aa;
            color: white;
        }
        .points-applied-section {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 3px;
            padding: 10px;
            margin: 10px 0;
        }
        .points-applied-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .points-applied-text {
            color: #155724;
            font-weight: 600;
        }
        .wc-points-message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 3px;
        }
        .wc-points-message.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .wc-points-message.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .wc-points-message.info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        @media (max-width: 768px) {
            .points-info-grid {
                grid-template-columns: 1fr;
            }
            .points-input-wrapper {
                flex-direction: column;
                align-items: stretch;
            }
            .points-input-wrapper input[type="number"] {
                width: 100%;
            }
            .points-applied-summary {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
        }
        ';
    }
    
    /**
     * 取得內聯 JavaScript
     */
    private static function get_inline_js() {
        return '
        jQuery(document).ready(function($) {
            // 顯示訊息函數
            function showPointsMessage(message, type) {
                var messageClass = "wc-points-message " + type;
                var messageHtml = "<div class=\"" + messageClass + "\">" + message + "</div>";
                
                $(".points-messages").html(messageHtml).show();
                
                setTimeout(function() {
                    $(".points-messages").fadeOut();
                }, 5000);
            }
            
            // 點數使用快捷按鈕
            $(document).on("click", ".points-quick-use", function() {
                var points = $(this).data("points");
                $("#points-to-use").val(points);
            });
            
            // 應用點數折扣
            $(document).on("click", ".wc-points-functions-apply-discount", function() {
                var $button = $(this);
                var $input = $("#points-to-use");
                var points = parseFloat($input.val());
                
                if (!points || points <= 0) {
                    showPointsMessage(wcPointsFunctions.messages.invalidPoints, "error");
                    return;
                }
                
                $button.prop("disabled", true).text(wcPointsFunctions.messages.processing);
                
                $.ajax({
                    url: wcPointsFunctions.ajaxUrl,
                    type: "POST",
                    data: {
                        action: "wc_points_functions_apply_discount",
                        points: points,
                        nonce: wcPointsFunctions.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showPointsMessage(response.data.message, "success");
                            $("body").trigger("update_checkout");
                            location.reload();
                        } else {
                            showPointsMessage(response.data, "error");
                        }
                    },
                    error: function() {
                        showPointsMessage(wcPointsFunctions.messages.error, "error");
                    },
                    complete: function() {
                        $button.prop("disabled", false).text(wcPointsFunctions.messages.usePoints);
                    }
                });
            });
            
            // 移除點數折扣
            $(document).on("click", ".wc-points-functions-remove-discount", function() {
                var $button = $(this);
                
                $button.prop("disabled", true).text(wcPointsFunctions.messages.processing);
                
                $.ajax({
                    url: wcPointsFunctions.ajaxUrl,
                    type: "POST",
                    data: {
                        action: "wc_points_functions_remove_discount",
                        nonce: wcPointsFunctions.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showPointsMessage(response.data.message, "success");
                            $("body").trigger("update_checkout");
                            location.reload();
                        } else {
                            showPointsMessage(response.data, "error");
                        }
                    },
                    error: function() {
                        showPointsMessage(wcPointsFunctions.messages.error, "error");
                    },
                    complete: function() {
                        $button.prop("disabled", false).text(wcPointsFunctions.messages.removePoints);
                    }
                });
            });
        });
        ';
    }
    
    /**
     * 在購物車頁面顯示點數使用區塊
     */
    public static function display_cart_points_section() {
        if (!is_user_logged_in()) {
            return;
        }
        
        echo '<tr class="points-functions-cart-row">';
        echo '<th>' . __('使用點數', 'wc-points-rewards') . '</th>';
        echo '<td>';
        self::render_points_section('cart');
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * 在結帳頁面顯示點數使用區塊
     */
    public static function display_checkout_points_section() {
        if (!is_user_logged_in()) {
            return;
        }
        
        echo '<tr class="points-functions-checkout-row">';
        echo '<th>' . __('使用點數', 'wc-points-rewards') . '</th>';
        echo '<td>';
        self::render_points_section('checkout');
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * 渲染點數使用區塊
     */
    private static function render_points_section($context = 'cart') {
        // 取得外掛設定
        $settings = get_option('wc_points_rewards_settings', array());
        
        // 檢查是否啟用購物車點數折抵
        if (!isset($settings['enable_cart_redemption']) || $settings['enable_cart_redemption'] !== 'yes') {
            return;
        }
        
        $user_id = get_current_user_id();
        $database = WC_Points_Rewards_Database::instance();
        $calculator = WC_Points_Rewards_Points_Calculator::instance();
        
        $available_points = $database->get_user_points($user_id);
        $cart_total = WC()->cart->get_subtotal();
        $min_cart_total = isset($settings['min_cart_total']) ? floatval($settings['min_cart_total']) : 0;
        
        // 檢查購物車金額是否達到最低要求
        if ($cart_total < $min_cart_total) {
            if ($min_cart_total > 0) {
                echo '<div class="wc-points-functions-wrapper">';
                echo '<div class="wc-points-message info">';
                echo sprintf(__('購物車滿 %s 即可使用點數折抵', 'wc-points-rewards'), wc_price($min_cart_total));
                echo '</div>';
                echo '</div>';
            }
            return;
        }
        
        if ($available_points <= 0) {
            echo '<div class="wc-points-functions-wrapper">';
            echo '<div class="wc-points-message info">';
            echo __('您目前沒有可用的點數', 'wc-points-rewards');
            echo '</div>';
            echo '</div>';
            return;
        }
        
        // 計算最大可使用點數
        $max_discount_percent = isset($settings['max_discount_percent']) ? floatval($settings['max_discount_percent']) : 100;
        $max_discount_amount = ($cart_total * $max_discount_percent) / 100;
        $max_points = min($available_points, $max_discount_amount);
        
        $current_discount = WC()->session->get('wc_points_functions_discount_amount', 0);
        
        self::render_points_form($available_points, $max_points, $current_discount, $context);
    }
    
    /**
     * 渲染點數表單
     */
    private static function render_points_form($available_points, $max_points, $used_points, $context) {
        $calculator = WC_Points_Rewards_Points_Calculator::instance();
        $remaining_points = $available_points - $used_points;
        $discount_amount = 0;
        
        if ($used_points > 0) {
            $discount_amount = $calculator->calculate_discount_amount($used_points);
        }
        
        ?>
        <div class="wc-points-functions-wrapper">
            <h3><?php _e('使用點數折抵', 'wc-points-rewards'); ?></h3>
            
            <!-- 點數資訊總覽 -->
            <div class="points-info-grid">
                <div class="points-info-item">
                    <span class="points-label"><?php _e('可用點數', 'wc-points-rewards'); ?>：</span>
                    <span class="points-value available-points"><?php echo wc_points_rewards_number_format($available_points); ?></span>
                </div>
                
                <?php if ($used_points > 0): ?>
                <div class="points-info-item">
                    <span class="points-label"><?php _e('已使用點數', 'wc-points-rewards'); ?>：</span>
                    <span class="points-value used-points"><?php echo wc_points_rewards_number_format($used_points); ?></span>
                </div>
                
                <div class="points-info-item">
                    <span class="points-label"><?php _e('目前剩餘點數', 'wc-points-rewards'); ?>：</span>
                    <span class="points-value remaining-points"><?php echo wc_points_rewards_number_format($remaining_points); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="points-info-item">
                    <span class="points-label"><?php _e('本次最多可用', 'wc-points-rewards'); ?>：</span>
                    <span class="points-value max-points"><?php echo wc_points_rewards_number_format($max_points); ?></span>
                </div>
                
                <?php if ($discount_amount > 0): ?>
                <div class="points-info-item">
                    <span class="points-label"><?php _e('折抵金額', 'wc-points-rewards'); ?>：</span>
                    <span class="points-value discount-value">-<?php echo wc_price($discount_amount); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($used_points > 0): ?>
                <!-- 已使用點數狀態 -->
                <div class="points-applied-section">
                    <div class="points-applied-summary">
                        <span class="points-applied-text">
                            <?php printf(__('✓ 已使用 %s 點數，折抵 %s', 'wc-points-rewards'), 
                                wc_points_rewards_number_format($used_points), 
                                wc_price($discount_amount)
                            ); ?>
                        </span>
                        <button type="button" class="button button-secondary wc-points-functions-remove-discount">
                            <?php _e('取消使用', 'wc-points-rewards'); ?>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <!-- 點數輸入區域 -->
                <div class="points-input-section">
                    <div class="points-input-wrapper">
                        <label for="points-to-use"><?php _e('輸入要使用的點數', 'wc-points-rewards'); ?>：</label>
                        <input type="number" id="points-to-use" min="1" max="<?php echo esc_attr($max_points); ?>" step="1" placeholder="<?php echo esc_attr($max_points); ?>">
                        <button type="button" class="button wc-points-functions-apply-discount">
                            <?php _e('使用點數', 'wc-points-rewards'); ?>
                        </button>
                    </div>
                    
                    <!-- 快捷選項 -->
                    <?php if ($max_points > 0): ?>
                    <div class="points-quick-options">
                        <?php
                        $quick_options = array(
                            array('points' => min($max_points, 100), 'label' => __('使用 100 點', 'wc-points-rewards')),
                            array('points' => round($max_points * 0.5), 'label' => __('使用 50%', 'wc-points-rewards')),
                            array('points' => $max_points, 'label' => __('全部使用', 'wc-points-rewards'))
                        );
                        
                        foreach ($quick_options as $option):
                            if ($option['points'] > 0):
                        ?>
                        <button type="button" class="button-link points-quick-use" data-points="<?php echo esc_attr($option['points']); ?>">
                            <?php echo esc_html($option['label']); ?>
                        </button>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="points-messages" style="display: none;"></div>
        </div>
        <?php
    }
    
    /**
     * 應用點數折扣
     */
    public static function apply_points_discount() {
        if (!is_admin() && !defined('DOING_AJAX')) {
            $discount_amount = WC()->session->get('wc_points_functions_discount_amount', 0);
            
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
     * AJAX: 應用點數折扣
     */
    public static function ajax_apply_points_discount() {
        check_ajax_referer('wc_points_functions_nonce', 'nonce');
        
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
        WC()->session->set('wc_points_functions_discount_amount', $points_to_use);
        
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
    public static function ajax_remove_points_discount() {
        check_ajax_referer('wc_points_functions_nonce', 'nonce');
        
        WC()->session->__unset('wc_points_functions_discount_amount');
        
        wp_send_json_success(array(
            'message' => __('已移除點數折抵', 'wc-points-rewards')
        ));
    }
    
    /**
     * 記錄點數使用
     */
    public static function record_points_usage($order_id, $posted_data) {
        $discount_amount = WC()->session->get('wc_points_functions_discount_amount', 0);
        
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
                update_post_meta($order_id, '_points_functions_discount_amount', $discount_value);
                update_post_meta($order_id, '_points_functions_used', $discount_amount);
                
                // 清除 session
                WC()->session->__unset('wc_points_functions_discount_amount');
            }
        }
    }
    
    /**
     * 驗證點數使用
     */
    public static function validate_points_usage() {
        $discount_amount = WC()->session->get('wc_points_functions_discount_amount', 0);
        
        if ($discount_amount > 0) {
            $user_id = get_current_user_id();
            if (!$user_id) {
                WC()->session->__unset('wc_points_functions_discount_amount');
                return;
            }
            
            $database = WC_Points_Rewards_Database::instance();
            $calculator = WC_Points_Rewards_Points_Calculator::instance();
            
            $available_points = $database->get_user_points($user_id);
            $cart_total = WC()->cart->get_subtotal();
            
            // 如果點數不足或不符合條件，移除折扣
            if ($discount_amount > $available_points || !$calculator->can_use_points($cart_total, $discount_amount)) {
                WC()->session->__unset('wc_points_functions_discount_amount');
                wc_add_notice(__('您的點數使用已自動調整', 'wc-points-rewards'), 'notice');
            }
        }
    }
}

// 初始化點數折抵區塊
add_action('plugins_loaded', array('WC_Points_Rewards_Functions_Block', 'init'));

/**
 * 使用說明：
 * 
 * 1. 將此代碼複製並貼上到您的佈景主題 functions.php 檔案中
 * 2. 確保 WooCommerce Points & Rewards 外掛已啟用
 * 3. 在外掛設定中啟用「購物車點數折抵」功能
 * 4. 設定最低購物車金額和最大折抵比例
 * 5. 點數折抵區塊將自動顯示在購物車和結帳頁面
 * 
 * 功能特色：
 * - 完全響應式設計，支援手機和平板
 * - 與外掛設定完全同步
 * - 支援 AJAX 即時更新
 * - 包含快捷點數使用選項
 * - 自動驗證點數餘額和使用條件
 * - 完整的錯誤處理和用戶反饋
 * 
 * 注意事項：
 * - 需要用戶登入才會顯示點數折抵選項
 * - 購物車金額需要達到後台設定的最低金額
 * - 最大折抵金額受後台最大折抵比例限制
 * - 與原外掛完全相容，不會產生衝突
 */