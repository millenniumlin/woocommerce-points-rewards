// Fixing the percentage display issue in render_points_overview method

public function render_points_overview() {
    // ... other code ...

    // Update the printf statement for proper percentage formatting
    printf(__('額外 +%s 點數回饋', 'wc-points-rewards'), wc_points_rewards_format_percentage(round($current_tier->bonus_percentage))); // Rounding to avoid decimal places

    // ... other code ...
}

/**
 * 🚀 強制：產生帳戶端點 URL（只用 query string，不理會永久連結結構）
 */
public static function get_account_endpoint_url($endpoint) {
    $account_page_id = wc_get_page_id('myaccount');
    $account_page_url = get_permalink($account_page_id);
    return add_query_arg($endpoint, '', $account_page_url);
}