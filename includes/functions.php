<?php
/**
 * 輔助函數
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 安全的絕對值函數
 * 
 * @param mixed $value 輸入值
 * @return float 絕對值
 */
function wc_points_rewards_abs($value) {
    return abs(floatval($value ?? 0));
}

/**
 * 安全的整數轉換
 * 
 * @param mixed $value 輸入值
 * @return int 整數值
 */
function wc_points_rewards_intval($value) {
    return intval($value ?? 0);
}

/**
 * 安全的浮點數轉換
 * 
 * @param mixed $value 輸入值
 * @return float 浮點數值
 */
function wc_points_rewards_floatval($value) {
    return floatval($value ?? 0);
}

/**
 * 安全的數字格式化 - 使用 WooCommerce 小數位數設定
 * 修正：當小數位數為0時，使用 floor 截斷而非四捨五入，確保顯示的點數可以實際使用
 * 
 * @param mixed $value 輸入值
 * @param int $decimals 小數位數 (可選，預設使用 WooCommerce 設定)
 * @return string 格式化後的數字
 */
function wc_points_rewards_number_format($value, $decimals = null) {
    // 如果沒有指定小數位數，使用 WooCommerce 的設定
    if ($decimals === null) {
        $decimals = wc_get_price_decimals();
    }
    
    $value = floatval($value ?? 0);
    
    // 當小數位數為0時，使用 floor 截斷而非四捨五入
    // 這確保顯示的點數金額與實際可用金額一致
    if ($decimals == 0) {
        $value = floor($value);
    }
    
    return number_format($value, $decimals);
}

/**
 * 檢查功能是否啟用
 */
function wc_points_rewards_is_enabled() {
    // 修正：直接從個別選項讀取，而非從組合設定陣列
    return get_option('wc_points_rewards_enable_points_system', 'yes') === 'yes';
}

/**
 * 格式化百分比顯示 - 與 WooCommerce 貨幣小數位數同步
 * 
 * @param float $percentage 百分比值
 * @return string 格式化後的百分比
 */
function wc_points_rewards_format_percentage($percentage) {
    $percentage = floatval($percentage ?? 0);

    // 獲取 WooCommerce 貨幣小數位數設定
    $decimal_places = wc_get_price_decimals();
    
    // 使用 WooCommerce 的小數位數格式化百分比
    $formatted = number_format($percentage, $decimal_places);
    
    // 如果小數位數為0或所有小數都是0，則移除不必要的小數點和0
    if ($decimal_places == 0 || rtrim(substr($formatted, strpos($formatted, '.') + 1), '0') === '') {
        $formatted = number_format($percentage, 0);
    }

    return $formatted . '%';
}

/**
 * 獲取點數名稱
 */
function wc_points_rewards_get_points_name() {
    // 修正：直接從個別選項讀取
    return get_option('wc_points_rewards_points_name', __('點', 'wc-points-rewards'));
}

/**
 * 獲取點數價值（1點等於多少錢）
 */
function wc_points_rewards_get_points_value() {
    // 修正：直接從個別選項讀取
    return floatval(get_option('wc_points_rewards_points_value', '1'));
}

/**
 * 格式化點數價值顯示
 */
function wc_points_rewards_format_points_value($points = 1) {
    $points_name = wc_points_rewards_get_points_name();
    $points_value = wc_points_rewards_get_points_value();
    $value_formatted = wc_price($points * $points_value);
    
    return sprintf(__('%s%s等於%s', 'wc-points-rewards'), $points_name, $points, $value_formatted);
}

/**
 * 獲取外掛設定
 */
function wc_points_rewards_get_option($key, $default = null) {
    // 修正：直接從個別選項讀取
    return get_option('wc_points_rewards_' . $key, $default);
}

/**
 * 更新外掛設定
 */
function wc_points_rewards_update_option($key, $value) {
    // 修正：直接更新個別選項
    return update_option('wc_points_rewards_' . $key, $value);
}

/**
 * 獲取用戶點數（安全版本）
 * 
 * @param int $user_id 用戶ID
 * @return float 點數餘額
 */
function wc_points_rewards_get_user_points($user_id) {
    if (!class_exists('WC_Points_Rewards_Database')) {
        return 0.0;
    }
    
    $database = WC_Points_Rewards_Database::instance();
    $points = $database->get_user_points($user_id);
    return floatval($points ?? 0);
}

/**
 * 添加點數（安全版本）
 * 
 * @param int $user_id 用戶ID
 * @param float $points 點數
 * @param string $description 描述
 * @return bool 是否成功
 */
function wc_points_rewards_add_points($user_id, $points, $description = '') {
    if (!class_exists('WC_Points_Rewards_Database')) {
        return false;
    }
    
    $database = WC_Points_Rewards_Database::instance();
    return $database->add_points($user_id, floatval($points), 'admin', $description);
}

/**
 * 獲取用戶會員等級（安全版本）
 * 
 * @param int $user_id 用戶ID
 * @return object|null 會員等級物件
 */
function wc_points_rewards_get_user_tier($user_id) {
    if (!class_exists('WC_Points_Rewards_Database')) {
        return null;
    }
    
    $database = WC_Points_Rewards_Database::instance();
    return $database->get_user_current_tier($user_id);
}

/**
 * 格式化點數顯示 - 使用 WooCommerce 小數位數設定
 */
function wc_points_rewards_format_points($points) {
    $decimals = wc_get_price_decimals(); // 使用 WooCommerce 設定
    $points_name = wc_points_rewards_get_points_name();
    
    return wc_points_rewards_number_format($points, $decimals) . ' ' . $points_name;
}

/**
 * 檢查用戶是否可以使用點數
 */
function wc_points_rewards_can_user_redeem($user_id) {
    if (!$user_id || !wc_points_rewards_is_enabled()) {
        return false;
    }
    
    $user_points = wc_points_rewards_get_user_points($user_id);
    $min_redemption = floatval(wc_points_rewards_get_option('min_points_redemption', 1));
    
    return $user_points >= $min_redemption;
}

/**
 * 獲取點數兌換率
 */
function wc_points_rewards_get_redemption_rate() {
    return floatval(wc_points_rewards_get_option('points_value', 1));
}

/**
 * 計算點數價值
 */
function wc_points_rewards_calculate_points_value($points) {
    $rate = wc_points_rewards_get_redemption_rate();
    return floatval($points) * $rate;
}

/**
 * 強制使用點數 - 跳過所有限制（僅供管理員使用，加強安全檢查）
 * 此函數可以添加到主題的 functions.php 中來強制啟用點數使用
 */
function wc_points_rewards_force_enable_points_usage() {
    // 多重安全檢查
    if (!current_user_can('manage_woocommerce')) {
        return false;
    }
    
    // 檢查是否在管理後台或具有適當的 nonce
    if (!is_admin() && !wp_verify_nonce($_REQUEST['force_points_nonce'] ?? '', 'wc_points_force_enable')) {
        return false;
    }
    
    // 記錄此操作
    if (class_exists('WC_Points_Rewards_Security')) {
        $security = WC_Points_Rewards_Security::instance();
        $security->log_security_event('admin_force_points', '管理員強制啟用點數使用', get_current_user_id());
    }
    
    // 添加一個 hook 來允許管理員跳過所有限制
    add_filter('wc_points_rewards_can_use_points', '__return_true', 999);
    add_filter('wc_points_rewards_override_restrictions', '__return_true', 999);
    
    return true;
}

/**
 * 檢查是否啟用了管理員覆蓋功能
 */
function wc_points_rewards_is_admin_override_enabled() {
    $settings = get_option('wc_points_rewards_settings', array());
    return isset($settings['allow_admin_override']) && $settings['allow_admin_override'] === 'yes';
}

/**
 * 為當前用戶強制啟用點數使用（緊急修復功能，加強安全性）
 * 可以在主題的 functions.php 中調用此函數來臨時解決點數使用問題
 */
function wc_points_rewards_emergency_enable_points() {
    // 嚴格的權限檢查
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    
    // 只能在管理後台使用
    if (!is_admin()) {
        return;
    }
    
    // 記錄緊急操作
    if (class_exists('WC_Points_Rewards_Security')) {
        $security = WC_Points_Rewards_Security::instance();
        $security->log_security_event('emergency_points_enable', '管理員使用緊急點數啟用功能', get_current_user_id());
    }
    
    // 臨時設置允許管理員覆蓋
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

/**
 * 調試點數使用問題的助手函數
 */
function wc_points_rewards_debug_points_usage($user_id = null, $points_to_use = 0) {
    if (!current_user_can('manage_woocommerce')) {
        return array('error' => '權限不足');
    }
    
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!class_exists('WC_Points_Rewards_Database') || !class_exists('WC_Points_Rewards_Points_Calculator')) {
        return array('error' => '點數系統未初始化');
    }
    
    $database = WC_Points_Rewards_Database::instance();
    $calculator = WC_Points_Rewards_Points_Calculator::instance();
    $settings = get_option('wc_points_rewards_settings', array());
    
    $available_points = $database->get_user_points($user_id);
    $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;
    
    $debug_info = array(
        'user_id' => $user_id,
        'available_points' => $available_points,
        'points_to_use' => $points_to_use,
        'cart_total' => $cart_total,
        'settings' => array(
            'min_cart_total' => isset($settings['min_cart_total']) ? $settings['min_cart_total'] : '0',
            'max_discount_percent' => isset($settings['max_discount_percent']) ? $settings['max_discount_percent'] : '100',
            'points_value' => isset($settings['points_value']) ? $settings['points_value'] : '1',
            'allow_admin_override' => isset($settings['allow_admin_override']) ? $settings['allow_admin_override'] : 'no'
        ),
        'checks' => array()
    );
    
    // 執行各項檢查
    $debug_info['checks']['sufficient_points'] = $points_to_use <= $available_points;
    $debug_info['checks']['min_cart_total'] = $cart_total >= floatval($settings['min_cart_total'] ?? 0);
    
    if ($points_to_use > 0) {
        $discount_amount = $calculator->calculate_discount_amount($points_to_use);
        $max_discount_amount = ($cart_total * floatval($settings['max_discount_percent'] ?? 100)) / 100;
        $debug_info['checks']['max_discount_check'] = $discount_amount <= $max_discount_amount;
        $debug_info['discount_amount'] = $discount_amount;
        $debug_info['max_discount_amount'] = $max_discount_amount;
    }
    
    $debug_info['can_use_points'] = $calculator->can_use_points($cart_total, $points_to_use);
    $debug_info['is_admin'] = current_user_can('manage_woocommerce');
    
    return $debug_info;
}

/**
 * 🚀 修正：產生帳戶端點 URL（確保與所有永久連結結構兼容）
 */
function wc_points_rewards_get_account_endpoint_url($endpoint) {
    if (class_exists('WC_Points_Rewards_Account')) {
        return WC_Points_Rewards_Account::get_account_endpoint_url($endpoint);
    }
    
    // 後備方案
    $account_page_id = wc_get_page_id('myaccount');
    $account_page_url = get_permalink($account_page_id);
    
    if (!$account_page_url) {
        return home_url('/my-account/?' . $endpoint);
    }
    
    $permalink_structure = get_option('permalink_structure');
    
    if (empty($permalink_structure)) {
        // 預設永久連結結構
        return add_query_arg($endpoint, '', $account_page_url);
    } else {
        // 美化永久連結結構 - 使用查詢參數
        return trailingslashit($account_page_url) . '?' . $endpoint;
    }
}