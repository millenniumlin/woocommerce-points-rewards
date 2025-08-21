<?php
/**
 * 結帳頁面點數顯示區塊模板 - 僅顯示用
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

// 計算相關數值
$used_points = WC()->session->get('wc_points_rewards_discount_amount', 0);
$discount_amount = 0;

if ($used_points > 0) {
    $calculator = WC_Points_Rewards_Points_Calculator::instance();
    $discount_amount = $calculator->calculate_discount_amount($used_points);
}

// 如果沒有使用點數，不顯示任何內容
if ($used_points <= 0) {
    return;
}
?>

<tr class="points-redemption-section checkout-display">
    <th><?php _e('點數折抵', 'wc-points-rewards'); ?></th>
    <td>
        <div class="wc-points-checkout-display">
            <!-- 僅顯示已使用的點數折抵 -->
            <div class="points-applied-summary">
                <span class="points-applied-text">
                    <?php printf(__('已使用 %s 點數，折抵 %s', 'wc-points-rewards'), 
                        wc_points_rewards_number_format($used_points), 
                        wc_price($discount_amount)
                    ); ?>
                </span>
            </div>
            <div class="checkout-notice">
                <small><?php _e('如需修改點數使用，請返回購物車頁面', 'wc-points-rewards'); ?></small>
            </div>
        </div>
    </td>
</tr>