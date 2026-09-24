<?php
/**
 * WooCommerce Points & Rewards - Theme Functions.php Override Examples
 * 
 * 將以下代碼添加到您的主題的 functions.php 文件中，可以臨時解決點數使用問題
 * 
 * @package WC_Points_Rewards
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 方法 1: 緊急啟用點數使用（最高權限）
 * 此方法會完全跳過所有點數使用限制，僅供緊急情況使用
 */
function emergency_fix_points_usage() {
    // 只對管理員有效
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    
    // 強制設置為允許點數使用
    add_filter('wc_points_rewards_can_use_points', '__return_true', 999);
    
    // 覆蓋設置以移除所有限制
    add_filter('pre_option_wc_points_rewards_settings', function($value) {
        if (!is_array($value)) {
            $value = get_option('wc_points_rewards_settings', array());
        }
        $value['allow_admin_override'] = 'yes';
        $value['max_discount_percent'] = '100';
        $value['min_cart_total'] = '0';
        return $value;
    });
}

// 在購物車和結帳頁面啟用緊急修復
add_action('woocommerce_before_cart', 'emergency_fix_points_usage');
add_action('woocommerce_before_checkout_form', 'emergency_fix_points_usage');


/**
 * 方法 2: 調整點數使用設置（較溫和的方法）
 * 此方法只是調整設置，使點數更容易使用
 */
function adjust_points_settings() {
    add_filter('pre_option_wc_points_rewards_max_discount_percent', function() {
        return '100'; // 允許 100% 折抵
    });
    
    add_filter('pre_option_wc_points_rewards_min_cart_total', function() {
        return '0'; // 移除最低購物車金額限制
    });
    
    add_filter('pre_option_wc_points_rewards_allow_admin_override', function() {
        return 'yes'; // 啟用管理員覆蓋
    });
}

// 如果您想使用較溫和的方法，請取消以下註釋
// add_action('init', 'adjust_points_settings');


/**
 * 方法 3: 調試點數使用問題
 * 此函數會在頁面底部顯示調試信息（僅管理員可見）
 */
function debug_points_usage() {
    if (!current_user_can('manage_woocommerce') || !function_exists('wc_points_rewards_debug_points_usage')) {
        return;
    }
    
    $debug_info = wc_points_rewards_debug_points_usage();
    
    echo '<div style="position: fixed; bottom: 0; right: 0; background: #fff; border: 1px solid #ccc; padding: 10px; z-index: 9999; max-width: 400px; max-height: 300px; overflow: auto;">';
    echo '<h4>點數調試信息</h4>';
    echo '<pre>' . print_r($debug_info, true) . '</pre>';
    echo '</div>';
}

// 如果您需要調試信息，請取消以下註釋
// add_action('wp_footer', 'debug_points_usage');


/**
 * 方法 4: 完全自定義點數驗證邏輯
 * 這是最靈活的方法，您可以完全控制點數使用邏輯
 */
function custom_points_validation($can_use, $cart_total, $points_to_use) {
    // 如果是管理員，總是允許使用點數
    if (current_user_can('manage_woocommerce')) {
        return true;
    }
    
    // 如果用戶有足夠的點數，就允許使用（忽略其他限制）
    if (function_exists('wc_points_rewards_get_user_points')) {
        $user_id = get_current_user_id();
        $available_points = wc_points_rewards_get_user_points($user_id);
        return $points_to_use <= $available_points;
    }
    
    return $can_use;
}

// 如果您想使用自定義驗證邏輯，請取消以下註釋
// add_filter('wc_points_rewards_can_use_points_custom', 'custom_points_validation', 10, 3);


/**
 * 方法 5: 記錄點數使用嘗試的詳細日誌
 * 此方法會記錄所有點數使用嘗試，幫助您診斷問題
 */
function log_points_usage_attempts() {
    add_action('wp_ajax_wc_points_rewards_apply_discount', function() {
        error_log('Points usage attempt: ' . print_r($_POST, true));
    }, 1);
    
    add_action('wp_ajax_nopriv_wc_points_rewards_apply_discount', function() {
        error_log('Points usage attempt (not logged in): ' . print_r($_POST, true));
    }, 1);
}

// 如果您想記錄詳細日誌，請取消以下註釋
// add_action('init', 'log_points_usage_attempts');


/**
 * 使用說明：
 * 
 * 1. 複製您需要的方法到主題的 functions.php 文件
 * 2. 取消相對應的 add_action 或 add_filter 註釋
 * 3. 保存文件
 * 4. 測試點數使用功能
 * 
 * 建議順序：
 * - 先嘗試方法 2（較溫和）
 * - 如果還是不行，嘗試方法 1（緊急修復）
 * - 使用方法 3 來調試問題
 * - 使用方法 5 來記錄詳細信息
 */