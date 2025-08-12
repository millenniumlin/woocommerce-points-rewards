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
    return number_format(floatval($value ?? 0), $decimals);
}

/**
 * 檢查功能是否啟用
 */
function wc_points_rewards_is_enabled() {
    $settings = get_option('wc_points_rewards_settings', array());
    return isset($settings['enable_points_system']) && $settings['enable_points_system'] === 'yes';
}

/**
 * 格式化百分比顯示 - 根據需求只顯示到百位數，不顯示小數點
 * 
 * @param float $percentage 百分比值
 * @return string 格式化後的百分比
 */
function wc_points_rewards_format_percentage($percentage) {
    // 根據需求，點數回饋百分比只顯示到百位數，不顯示小數點
    $percentage = floatval($percentage ?? 0);

    // 如果是小數，顯示一位小數；如果是整數，不顯示小數點
    if ($percentage == floor($percentage)) {
        // 整數，不顯示小數點
        $formatted = number_format($percentage, 0);
    } else {
        // 小數，最多顯示一位小數
        $formatted = number_format($percentage, 1);
        // 移除不必要的 .0
        $formatted = rtrim(rtrim($formatted, '0'), '.');
    }

    return $formatted . '%';
}

/**
 * 獲取點數名稱
 */
function wc_points_rewards_get_points_name() {
    return get_option('wc_points_rewards_points_name', __('點', 'wc-points-rewards'));
}

/**
 * 獲取點數價值（1點等於多少錢）
 */
function wc_points_rewards_get_points_value() {
    return floatval(get_option('wc_points_rewards_points_value', '0.01'));
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
    $settings = get_option('wc_points_rewards_settings', array());
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * 更新外掛設定
 */
function wc_points_rewards_update_option($key, $value) {
    $settings = get_option('wc_points_rewards_settings', array());
    $settings[$key] = $value;
    return update_option('wc_points_rewards_settings', $settings);
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