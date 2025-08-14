<?php
/**
 * 購物車點數使用區塊模板 - 重新設計版
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

// 計算本次最多可用點數
$max_usable_points = isset($max_points) ? $max_points : $available_points;

// 檢查上下文以確定渲染方式
$is_cart = ($context === 'cart');
$wrapper_class = $is_cart ? 'wc-points-cart-wrapper' : 'wc-points-checkout-wrapper';
?>

<?php if ($is_cart): ?>
<!-- 購物車頁面點數區塊 -->
<div class="<?php echo esc_attr($wrapper_class); ?>">
    <div class="wc-points-redemption-section">
        <h3 class="wc-points-section-title">
            <i class="wc-points-icon"></i>
            <?php _e('點數折抵', 'wc-points-rewards'); ?>
        </h3>
        
        <?php if ($used_points > 0): ?>
            <!-- 已使用點數狀態 -->
            <div class="wc-points-applied-status">
                <div class="points-success-message">
                    <span class="success-icon">✓</span>
                    <div class="success-content">
                        <div class="success-text">
                            <?php printf(__('已使用 %s 點數', 'wc-points-rewards'), 
                                '<strong>' . wc_points_rewards_number_format($used_points) . '</strong>'
                            ); ?>
                        </div>
                        <div class="success-amount">
                            <?php printf(__('折抵金額：%s', 'wc-points-rewards'), 
                                '<strong>' . wc_price($discount_amount) . '</strong>'
                            ); ?>
                        </div>
                    </div>
                    <button type="button" class="wc-points-remove-btn" data-nonce="<?php echo wp_create_nonce('wc_points_rewards_nonce'); ?>">
                        <?php _e('取消', 'wc-points-rewards'); ?>
                    </button>
                </div>
            </div>
        <?php else: ?>
            <!-- 點數使用輸入區域 -->
            <div class="wc-points-input-area">
                <div class="points-info-summary">
                    <div class="points-balance">
                        <span class="balance-label"><?php _e('可用點數', 'wc-points-rewards'); ?>：</span>
                        <span class="balance-value"><?php echo wc_points_rewards_number_format($available_points); ?></span>
                    </div>
                    <div class="points-max-use">
                        <span class="max-label"><?php _e('本次最多可用', 'wc-points-rewards'); ?>：</span>
                        <span class="max-value"><?php echo wc_points_rewards_number_format($max_usable_points); ?></span>
                    </div>
                </div>
                
                <div class="points-input-controls">
                    <div class="input-group">
                        <input type="number" 
                               id="points-to-use" 
                               class="points-input" 
                               min="1" 
                               max="<?php echo esc_attr($max_usable_points); ?>" 
                               step="0.01" 
                               placeholder="<?php _e('輸入點數', 'wc-points-rewards'); ?>" />
                        <button type="button" class="wc-points-apply-btn" data-nonce="<?php echo wp_create_nonce('wc_points_rewards_nonce'); ?>">
                            <?php _e('使用', 'wc-points-rewards'); ?>
                        </button>
                    </div>
                    
                    <div class="quick-actions">
                        <?php 
                        $quick_options = array(
                            array('points' => min($max_usable_points, 100), 'label' => '100點'),
                            array('points' => round($max_usable_points * 0.5), 'label' => '50%'),
                            array('points' => $max_usable_points, 'label' => '全部')
                        );
                        
                        foreach ($quick_options as $option):
                            if ($option['points'] > 0):
                        ?>
                        <button type="button" class="quick-btn" data-points="<?php echo esc_attr($option['points']); ?>">
                            <?php echo esc_html($option['label']); ?>
                        </button>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="wc-points-messages" style="display: none;"></div>
    </div>
</div>

<?php else: ?>
<!-- 結帳頁面點數區塊 -->
<tr class="points-redemption-section">
    <th><?php _e('點數折抵', 'wc-points-rewards'); ?></th>
    <td>
        <div class="<?php echo esc_attr($wrapper_class); ?>">
            <?php if ($used_points > 0): ?>
                <!-- 已使用點數狀態 -->
                <div class="wc-points-applied-compact">
                    <span class="applied-info">
                        <?php printf(__('已使用 %s 點數，折抵 %s', 'wc-points-rewards'), 
                            wc_points_rewards_number_format($used_points), 
                            wc_price($discount_amount)
                        ); ?>
                    </span>
                    <button type="button" class="wc-points-remove-compact" data-nonce="<?php echo wp_create_nonce('wc_points_rewards_nonce'); ?>">
                        <?php _e('取消', 'wc-points-rewards'); ?>
                    </button>
                </div>
            <?php else: ?>
                <!-- 點數使用輸入區域（簡化版） -->
                <div class="wc-points-input-compact">
                    <div class="compact-info">
                        <span><?php printf(__('可用：%s | 最多：%s', 'wc-points-rewards'), 
                            wc_points_rewards_number_format($available_points),
                            wc_points_rewards_number_format($max_usable_points)
                        ); ?></span>
                    </div>
                    <div class="compact-controls">
                        <input type="number" 
                               id="points-to-use-checkout" 
                               class="points-input-compact" 
                               min="1" 
                               max="<?php echo esc_attr($max_usable_points); ?>" 
                               step="0.01" 
                               placeholder="<?php _e('點數', 'wc-points-rewards'); ?>" />
                        <button type="button" class="wc-points-apply-compact" data-nonce="<?php echo wp_create_nonce('wc_points_rewards_nonce'); ?>">
                            <?php _e('使用', 'wc-points-rewards'); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="wc-points-messages" style="display: none;"></div>
        </div>
    </td>
</tr>
<?php endif; ?>

<style>
/* 現代化點數折抵區塊樣式 */
.wc-points-redemption-section {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}

/* 購物車頁面樣式 */
.wc-points-cart-wrapper {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.wc-points-section-title {
    display: flex;
    align-items: center;
    margin: 0 0 15px 0;
    font-size: 18px;
    color: #333;
    font-weight: 600;
}

.wc-points-icon::before {
    content: "🎁";
    margin-right: 8px;
}

.wc-points-applied-status {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 6px;
    padding: 15px;
}

.points-success-message {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.success-icon {
    font-size: 20px;
    color: #155724;
    margin-right: 12px;
}

.success-content {
    flex: 1;
}

.success-text {
    font-size: 16px;
    color: #155724;
    margin-bottom: 4px;
}

.success-amount {
    font-size: 14px;
    color: #155724;
}

.wc-points-remove-btn {
    background: #dc3545;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.2s;
}

.wc-points-remove-btn:hover {
    background: #c82333;
}

.wc-points-input-area {
    background: white;
    border-radius: 6px;
    padding: 15px;
}

.points-info-summary {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e9ecef;
}

.points-balance, .points-max-use {
    display: flex;
    flex-direction: column;
}

.balance-label, .max-label {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 4px;
}

.balance-value, .max-value {
    font-size: 16px;
    font-weight: 600;
    color: #495057;
}

.points-input-controls {
    margin-top: 15px;
}

.input-group {
    display: flex;
    margin-bottom: 12px;
}

.points-input {
    flex: 1;
    padding: 10px 12px;
    border: 2px solid #ced4da;
    border-radius: 4px 0 0 4px;
    font-size: 16px;
    outline: none;
    transition: border-color 0.2s;
}

.points-input:focus {
    border-color: #007cba;
}

.wc-points-apply-btn {
    background: #007cba;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 0 4px 4px 0;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    transition: background-color 0.2s;
}

.wc-points-apply-btn:hover {
    background: #005a87;
}

.quick-actions {
    display: flex;
    gap: 8px;
}

.quick-btn {
    background: #f8f9fa;
    border: 1px solid #ced4da;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}

.quick-btn:hover {
    background: #e9ecef;
    border-color: #adb5bd;
}

/* 結帳頁面樣式 */
.wc-points-checkout-wrapper {
    font-size: 14px;
}

.wc-points-applied-compact {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #d4edda;
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #c3e6cb;
}

.applied-info {
    color: #155724;
    font-size: 14px;
}

.wc-points-remove-compact {
    background: #dc3545;
    color: white;
    border: none;
    padding: 4px 8px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
}

.wc-points-input-compact {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 4px;
}

.compact-info {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 8px;
}

.compact-controls {
    display: flex;
    gap: 8px;
}

.points-input-compact {
    flex: 1;
    padding: 6px 8px;
    border: 1px solid #ced4da;
    border-radius: 3px;
    font-size: 14px;
}

.wc-points-apply-compact {
    background: #007cba;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 14px;
}

/* 訊息樣式 */
.wc-points-messages {
    margin-top: 10px;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 14px;
}

.wc-points-messages.wc-points-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.wc-points-messages.wc-points-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

/* 響應式設計 */
@media (max-width: 768px) {
    .points-info-summary {
        flex-direction: column;
        gap: 10px;
    }
    
    .quick-actions {
        flex-wrap: wrap;
    }
    
    .compact-controls {
        flex-direction: column;
        gap: 8px;
    }
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // 快速選擇點數
    $('.quick-btn').on('click', function() {
        var points = $(this).data('points');
        $('#points-to-use').val(points);
    });
    
    // 應用點數折扣（購物車頁面）
    $('.wc-points-apply-btn').on('click', function() {
        applyPoints($(this), '#points-to-use');
    });
    
    // 應用點數折扣（結帳頁面）
    $('.wc-points-apply-compact').on('click', function() {
        applyPoints($(this), '#points-to-use-checkout');
    });
    
    // 移除點數折扣
    $('.wc-points-remove-btn, .wc-points-remove-compact').on('click', function() {
        removePoints($(this));
    });
    
    function applyPoints($button, inputSelector) {
        var $input = $(inputSelector);
        var points = parseFloat($input.val());
        var nonce = $button.data('nonce');
        
        if (!points || points <= 0) {
            showPointsMessage('<?php _e('請輸入有效的點數', 'wc-points-rewards'); ?>', 'error');
            return;
        }
        
        $button.prop('disabled', true).text('<?php _e('處理中...', 'wc-points-rewards'); ?>');
        
        $.ajax({
            url: wcPointsRewards.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wc_points_rewards_apply_discount',
                points: points,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    showPointsMessage(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showPointsMessage(response.data, 'error');
                }
            },
            error: function() {
                showPointsMessage('<?php _e('發生錯誤，請稍後再試', 'wc-points-rewards'); ?>', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e('使用', 'wc-points-rewards'); ?>');
            }
        });
    }
    
    function removePoints($button) {
        var nonce = $button.data('nonce');
        
        $button.prop('disabled', true).text('<?php _e('處理中...', 'wc-points-rewards'); ?>');
        
        $.ajax({
            url: wcPointsRewards.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wc_points_rewards_remove_discount',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    showPointsMessage(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showPointsMessage(response.data, 'error');
                }
            },
            error: function() {
                showPointsMessage('<?php _e('發生錯誤，請稍後再試', 'wc-points-rewards'); ?>', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e('取消', 'wc-points-rewards'); ?>');
            }
        });
    }
    
    function showPointsMessage(message, type) {
        var $messages = $('.wc-points-messages');
        $messages.removeClass('wc-points-success wc-points-error')
                .addClass('wc-points-' + type)
                .html(message)
                .show();
        
        setTimeout(function() {
            $messages.fadeOut();
        }, 5000);
    }
});
</script>