<?php
/**
 * WooCommerce Points & Rewards - 簡化版 Functions.php 點數折抵區塊
 * 
 * 簡化版本，只包含核心功能
 * 
 * @package WC_Points_Rewards
 * @version 1.0.0
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 簡化版點數折抵功能
 */
function wc_points_simple_init() {
    // 確保外掛已載入
    if (!class_exists('WooCommerce') || !class_exists('WC_Points_Rewards_Database')) {
        return;
    }
    
    // 購物車和結帳頁面顯示點數區塊
    add_action('woocommerce_cart_totals_before_order_total', 'wc_points_simple_display_cart');
    add_action('woocommerce_review_order_after_order_total', 'wc_points_simple_display_checkout');
    
    // 處理點數折扣
    add_action('woocommerce_cart_calculate_fees', 'wc_points_simple_apply_discount');
    
    // AJAX 處理
    add_action('wp_ajax_wc_points_simple_apply', 'wc_points_simple_ajax_apply');
    add_action('wp_ajax_wc_points_simple_remove', 'wc_points_simple_ajax_remove');
    
    // 訂單完成記錄
    add_action('woocommerce_checkout_order_processed', 'wc_points_simple_record_usage', 10, 2);
    
    // 載入腳本
    add_action('wp_enqueue_scripts', 'wc_points_simple_scripts');
}
add_action('plugins_loaded', 'wc_points_simple_init');

/**
 * 載入腳本和樣式
 */
function wc_points_simple_scripts() {
    if (!is_cart() && !is_checkout()) {
        return;
    }
    
    wp_enqueue_script('jquery');
    
    // 簡單樣式
    wp_add_inline_style('woocommerce-general', '
        .wc-points-simple { 
            padding: 15px; 
            border: 1px solid #ddd; 
            margin: 10px 0; 
            background: #f9f9f9; 
        }
        .points-info { margin-bottom: 10px; }
        .points-input { margin: 10px 0; }
        .points-input input { width: 100px; margin-right: 10px; }
        .points-message { padding: 10px; margin: 10px 0; border-radius: 3px; }
        .points-success { background: #d4edda; color: #155724; }
        .points-error { background: #f8d7da; color: #721c24; }
    ');
    
    // JavaScript
    wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            $(document).on("click", ".points-apply-btn", function() {
                var points = $("#points-input").val();
                if (!points) return;
                
                $.post("' . admin_url('admin-ajax.php') . '", {
                    action: "wc_points_simple_apply",
                    points: points,
                    nonce: "' . wp_create_nonce('wc_points_simple') . '"
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                });
            });
            
            $(document).on("click", ".points-remove-btn", function() {
                $.post("' . admin_url('admin-ajax.php') . '", {
                    action: "wc_points_simple_remove",
                    nonce: "' . wp_create_nonce('wc_points_simple') . '"
                }, function(response) {
                    location.reload();
                });
            });
        });
    ');
}

/**
 * 購物車顯示
 */
function wc_points_simple_display_cart() {
    if (!is_user_logged_in()) return;
    
    echo '<tr><th>使用點數</th><td>';
    wc_points_simple_render();
    echo '</td></tr>';
}

/**
 * 結帳顯示
 */
function wc_points_simple_display_checkout() {
    if (!is_user_logged_in()) return;
    
    echo '<tr><th>使用點數</th><td>';
    wc_points_simple_render();
    echo '</td></tr>';
}

/**
 * 渲染點數區塊
 */
function wc_points_simple_render() {
    $settings = get_option('wc_points_rewards_settings', array());
    
    // 檢查設定
    if (!isset($settings['enable_cart_redemption']) || $settings['enable_cart_redemption'] !== 'yes') {
        return;
    }
    
    $user_id = get_current_user_id();
    $database = WC_Points_Rewards_Database::instance();
    $available = $database->get_user_points($user_id);
    $cart_total = WC()->cart->get_subtotal();
    $min_total = floatval($settings['min_cart_total'] ?? 0);
    $used = WC()->session->get('wc_points_simple_discount', 0);
    
    // 檢查最低金額
    if ($cart_total < $min_total && $min_total > 0) {
        echo '<div class="wc-points-simple">購物車滿 ' . wc_price($min_total) . ' 可使用點數</div>';
        return;
    }
    
    // 檢查可用點數
    if ($available <= 0) {
        echo '<div class="wc-points-simple">目前沒有可用點數</div>';
        return;
    }
    
    // 計算最大可用
    $max_percent = floatval($settings['max_discount_percent'] ?? 100);
    $max_discount = ($cart_total * $max_percent) / 100;
    $max_points = min($available, $max_discount);
    
    echo '<div class="wc-points-simple">';
    echo '<div class="points-info">可用點數：' . number_format($available) . ' 點</div>';
    
    if ($used > 0) {
        $calculator = WC_Points_Rewards_Points_Calculator::instance();
        $discount = $calculator->calculate_discount_amount($used);
        echo '<div class="points-success">已使用 ' . number_format($used) . ' 點，折抵 ' . wc_price($discount) . '</div>';
        echo '<button class="button points-remove-btn">取消使用</button>';
    } else {
        echo '<div class="points-input">';
        echo '<input type="number" id="points-input" min="1" max="' . $max_points . '" placeholder="輸入點數">';
        echo '<button class="button points-apply-btn">使用點數</button>';
        echo '</div>';
        echo '<div>最多可用：' . number_format($max_points) . ' 點</div>';
    }
    
    echo '</div>';
}

/**
 * 應用折扣
 */
function wc_points_simple_apply_discount() {
    $discount = WC()->session->get('wc_points_simple_discount', 0);
    
    if ($discount > 0) {
        $calculator = WC_Points_Rewards_Points_Calculator::instance();
        $amount = $calculator->calculate_discount_amount($discount);
        
        WC()->cart->add_fee('點數折抵', -$amount, false);
    }
}

/**
 * AJAX 應用點數
 */
function wc_points_simple_ajax_apply() {
    check_ajax_referer('wc_points_simple', 'nonce');
    
    $points = floatval($_POST['points'] ?? 0);
    $user_id = get_current_user_id();
    
    if (!$user_id || $points <= 0) {
        wp_send_json_error('無效參數');
    }
    
    $database = WC_Points_Rewards_Database::instance();
    $available = $database->get_user_points($user_id);
    
    if ($points > $available) {
        wp_send_json_error('點數不足');
    }
    
    WC()->session->set('wc_points_simple_discount', $points);
    wp_send_json_success('已應用點數折扣');
}

/**
 * AJAX 移除點數
 */
function wc_points_simple_ajax_remove() {
    check_ajax_referer('wc_points_simple', 'nonce');
    
    WC()->session->__unset('wc_points_simple_discount');
    wp_send_json_success('已移除點數折扣');
}

/**
 * 記錄使用
 */
function wc_points_simple_record_usage($order_id) {
    $discount = WC()->session->get('wc_points_simple_discount', 0);
    
    if ($discount > 0) {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        
        if ($user_id) {
            $database = WC_Points_Rewards_Database::instance();
            $database->add_points(
                $user_id,
                -$discount,
                'redeemed',
                '訂單 #' . $order->get_order_number() . ' 點數折抵',
                $order_id
            );
            
            WC()->session->__unset('wc_points_simple_discount');
        }
    }
}

/**
 * 簡化版使用說明：
 * 
 * 1. 複製此代碼到 functions.php
 * 2. 確保點數外掛已啟用
 * 3. 在外掛設定中啟用購物車點數折抵
 * 4. 點數區塊會自動顯示在購物車和結帳頁面
 * 
 * 此版本特色：
 * - 代碼簡潔，易於理解和修改
 * - 包含基本的點數折抵功能
 * - 支援後台設定同步
 * - 基本的錯誤處理
 */