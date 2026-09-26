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
 * @param bool $truncate 是否截斷而非四捨五入（點數顯示建議使用）
 * @return string 格式化後的數字
 */
function wc_points_rewards_number_format($value, $decimals = null, $truncate = true) {
    // 如果沒有指定小數位數，使用 WooCommerce 的設定
    if ($decimals === null) {
        $decimals = wc_get_price_decimals();
    }
    
    $value = floatval($value ?? 0);
    
    // 如果要求截斷而非四捨五入，先進行截斷處理
    if ($truncate && $decimals >= 0) {
        if ($decimals > 0) {
            $scale = pow(10, $decimals);
            $value = floor($value * $scale) / $scale;
        } else {
            // 如果小數位數為0，直接向下取整到整數
            $value = floor($value);
        }
    }
    
    return number_format($value, $decimals);
}

/**
 * 檢查功能是否啟用
 */
function wc_points_rewards_is_enabled() {
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
    $decimal_places = wc_get_price_decimals();
    
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
    return get_option('wc_points_rewards_points_name', __('點', 'wc-points-rewards'));
}

/**
 * 獲取點數價值（1點等於多少錢）
 */
function wc_points_rewards_get_points_value() {
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
    return get_option('wc_points_rewards_' . $key, $default);
}

/**
 * 更新外掛設定
 */
function wc_points_rewards_update_option($key, $value) {
    return update_option('wc_points_rewards_' . $key, $value);
}

/**
 * 獲取用戶點數（安全版本）
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
    $decimals = wc_get_price_decimals();
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
 * 檢查目前用戶是否為網站管理員或 Multisite Super Admin
 *
 * [修正 W4] 改用 user_can() 檢查 capability，避免只檢查 'administrator' role 遺漏客製化管理員角色。
 *
 * @param int|null $user_id 用戶 ID。
 * @return bool
 */
function wc_points_rewards_is_site_administrator($user_id = null) {
    $user_id = $user_id ? intval($user_id) : get_current_user_id();

    if ($user_id <= 0) {
        return false;
    }

    if (is_multisite() && is_super_admin($user_id)) {
        return true;
    }

    return user_can($user_id, 'manage_woocommerce');
}

/**
 * 取得網站目前時區的 DateTimeImmutable。
 *
 * @param string|null $modify DateTime::modify() 字串。
 * @return DateTimeImmutable
 */
function wc_points_rewards_get_site_datetime($modify = null) {
    $datetime = current_datetime();

    if (!empty($modify)) {
        $modified = $datetime->modify($modify);
        if ($modified instanceof DateTimeImmutable) {
            return $modified;
        }
    }

    return $datetime;
}

/**
 * 取得網站目前時區的 MySQL datetime 字串。
 *
 * @param string|null $modify DateTime::modify() 字串。
 * @return string
 */
function wc_points_rewards_get_site_mysql_datetime($modify = null) {
    return wc_points_rewards_get_site_datetime($modify)->format('Y-m-d H:i:s');
}

/**
 * 取得網站時區的當日開始與結束時間（MySQL 格式）。
 *
 * @return array<string,string>
 */
function wc_points_rewards_get_site_day_window_mysql() {
    $current = wc_points_rewards_get_site_datetime();
    $start   = $current->setTime(0, 0, 0);
    $end     = $current->setTime(23, 59, 59);

    return array(
        'start' => $start->format('Y-m-d H:i:s'),
        'end'   => $end->format('Y-m-d H:i:s'),
        'date'  => $start->format('Y-m-d'),
    );
}

/**
 * 計算點數到期日（使用網站時區）。
 *
 * @return string|null
 */
function wc_points_rewards_calculate_points_expiry_date() {
    $expiry_months = intval(wc_points_rewards_get_option('points_expiry_months', 12));

    if ($expiry_months <= 0) {
        return null;
    }

    return wc_points_rewards_get_site_datetime('+' . $expiry_months . ' months')->format('Y-m-d H:i:s');
}

/**
 * 取得手動補發設定（以個別 option 為準）。
 *
 * @return array<string,mixed>
 */
function wc_points_rewards_get_manual_grant_settings() {
    $defaults = array(
        'enabled'             => 'yes',
        'per_grant_max'       => 1000.0,
        'per_admin_daily_max' => 1000.0,
        'site_daily_max'      => 3000.0,
    );

    $settings = array(
        'enabled'             => get_option('wc_points_rewards_enable_manual_admin_points', $defaults['enabled']),
        'per_grant_max'       => floatval(get_option('wc_points_rewards_manual_admin_points_per_grant_max', $defaults['per_grant_max'])),
        'per_admin_daily_max' => floatval(get_option('wc_points_rewards_manual_admin_points_per_admin_daily_max', $defaults['per_admin_daily_max'])),
        'site_daily_max'      => floatval(get_option('wc_points_rewards_manual_admin_points_site_daily_max', $defaults['site_daily_max'])),
    );

    $settings['enabled']             = ('no' === $settings['enabled']) ? 'no' : 'yes';
    $settings['per_grant_max']       = max(0, min($settings['per_grant_max'], 100000));
    $settings['per_admin_daily_max'] = max(0, min($settings['per_admin_daily_max'], 100000));
    $settings['site_daily_max']      = max(0, min($settings['site_daily_max'], 500000));

    return $settings;
}

/**
 * 格式化點數與金額等值。
 *
 * @param float $points 點數。
 * @return string
 */
function wc_points_rewards_format_points_with_value($points) {
    $formatted_value = wp_strip_all_tags(wc_price(wc_points_rewards_calculate_points_value($points)));

    return sprintf(
        __('%1$s %2$s（約 %3$s）', 'wc-points-rewards'),
        wc_points_rewards_number_format($points),
        wc_points_rewards_get_points_name(),
        $formatted_value
    );
}

/**
 * 計算點數價值
 */
function wc_points_rewards_calculate_points_value($points) {
    $rate = wc_points_rewards_get_redemption_rate();
    return floatval($points) * $rate;
}

/**
 * 強制使用點數 - 廢棄且安全的版本
 * [修正 S5] 此功能過於危險且有 CSRF 風險，改為無效化處理。
 */
function wc_points_rewards_force_enable_points_usage() {
    return false;
}

/**
 * 檢查是否啟用了管理員覆蓋功能
 * [修正 M2] 改為使用獨立 option 取代舊的陣列
 */
function wc_points_rewards_is_admin_override_enabled() {
    return get_option('wc_points_rewards_allow_admin_override', 'no') === 'yes';
}

/**
 * 為當前用戶強制啟用點數使用 - 廢棄且安全的版本
 * [修正 S6] 此功能過於危險，且直接過濾設定容易造成資料混亂，改為無效化處理。
 */
function wc_points_rewards_emergency_enable_points() {
    return;
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
    
    $available_points = $database->get_user_points($user_id);
    $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;
    
    $debug_info = array(
        'user_id' => $user_id,
        'available_points' => $available_points,
        'points_to_use' => $points_to_use,
        'cart_total' => $cart_total,
        'settings' => array(
            'min_cart_total' => get_option('wc_points_rewards_min_cart_total', '0'),
            'max_discount_percent' => get_option('wc_points_rewards_max_discount_percent', '100'),
            'points_value' => get_option('wc_points_rewards_points_value', '1'),
            'allow_admin_override' => get_option('wc_points_rewards_allow_admin_override', 'no')
        ),
        'checks' => array()
    );
    
    $debug_info['checks']['sufficient_points'] = $points_to_use <= $available_points;
    $debug_info['checks']['min_cart_total'] = $cart_total >= floatval($debug_info['settings']['min_cart_total']);
    
    if ($points_to_use > 0) {
        $discount_amount = $calculator->calculate_discount_amount($points_to_use);
        $max_discount_amount = ($cart_total * floatval($debug_info['settings']['max_discount_percent'])) / 100;
        $debug_info['checks']['max_discount_check'] = $discount_amount <= $max_discount_amount;
        $debug_info['discount_amount'] = $discount_amount;
        $debug_info['max_discount_amount'] = $max_discount_amount;
    }
    
    $debug_info['can_use_points'] = $calculator->can_use_points($cart_total, $points_to_use);
    $debug_info['is_admin'] = current_user_can('manage_woocommerce');
    
    return $debug_info;
}

/**
 * 修正：產生帳戶端點 URL（確保與所有永久連結結構兼容）
 * [修正 L12] 正確使用 WooCommerce 的內建函式
 */
function wc_points_rewards_get_account_endpoint_url($endpoint) {
    if (function_exists('wc_get_account_endpoint_url')) {
        return wc_get_account_endpoint_url($endpoint);
    }
    
    // 後備方案
    $account_page_id = wc_get_page_id('myaccount');
    $account_page_url = get_permalink($account_page_id);
    
    if (!$account_page_url) {
        return home_url('/my-account/?' . $endpoint);
    }
    
    $permalink_structure = get_option('permalink_structure');
    
    if (empty($permalink_structure)) {
        return add_query_arg($endpoint, '', $account_page_url);
    } else {
        return trailingslashit($account_page_url) . $endpoint;
    }
}
