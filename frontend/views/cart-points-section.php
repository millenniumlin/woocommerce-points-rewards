<?php
/**
 * 購物車點數使用區塊模板 - 增強版
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

// 計算相關數值
$used_points = WC()->session->get('wc_points_rewards_discount_amount', 0);
$remaining_points = $available_points - $used_points;
$discount_amount = 0;

if ($used_points > 0) {
    $calculator = WC_Points_Rewards_Points_Calculator::instance();
    $discount_amount = $calculator->calculate_discount_amount($used_points);
}

// 計算本次最多可用點數 - 使用已傳入的變數
$max_usable_points = isset($max_points) ? $max_points : $available_points;
?>

<tr class="points-redemption-section">
    <th><?php _e('使用點數', 'wc-points-rewards'); ?></th>
    <td>
        <div class="wc-points-redemption-wrapper">
            <!-- 點數資訊總覽 -->
            <div class="points-overview-section">
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
                        <span class="points-value max-usable"><?php echo wc_points_rewards_number_format($max_usable_points); ?></span>
                        <?php if ($max_discount_percent < 100): ?>
                            <span class="points-note">（<?php printf(__('最多可折抵 %s%%', 'wc-points-rewards'), $max_discount_percent); ?>）</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($used_points > 0 && $discount_amount > 0): ?>
                    <div class="points-info-item discount-amount">
                        <span class="points-label"><?php _e('已使用點數折抵金額', 'wc-points-rewards'); ?>：</span>
                        <span class="points-value discount-value">-<?php echo wc_price($discount_amount); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
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
                        <button type="button" class="button button-secondary wc-points-remove-discount" data-nonce="<?php echo wp_create_nonce('wc_points_rewards_nonce'); ?>">
                            <?php _e('取消使用', 'wc-points-rewards'); ?>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <!-- 點數輸入區域 -->
                <div class="points-input-section">
                    <div class="points-input-group">
                        <input type="number" 
                               id="points-to-use" 
                               class="input-text points-input" 
                               min="1" 
                               max="<?php echo esc_attr($max_usable_points); ?>" 
                               step="0.01" 
                               placeholder="<?php _e('輸入要使用的點數', 'wc-points-rewards'); ?>" />
                        <button type="button" class="button button-primary wc-points-apply-discount" data-nonce="<?php echo wp_create_nonce('wc_points_rewards_nonce'); ?>">
                            <?php _e('使用點數', 'wc-points-rewards'); ?>
                        </button>
                    </div>
                    

                </div>
            <?php endif; ?>
            
            <div class="points-messages" style="display: none;"></div>
        </div>
    </td>
</tr>