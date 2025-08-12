// Fixing the percentage display issue in render_points_overview method

public function render_points_overview() {
    // ... other code ...

    // Update the printf statement for proper percentage formatting
    printf(__('額外 +%s 點數回饋', 'wc-points-rewards'), wc_points_rewards_format_percentage(round($current_tier->bonus_percentage))); // Rounding to avoid decimal places

    // ... other code ...
}