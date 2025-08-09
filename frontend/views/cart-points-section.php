<?php
/**
 * 購物車點數使用區塊模板 - 重新設計版本
 * 根據需求移除三個預設按鈕，重新設計為RWD響應式布局
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

// 確保點數折抵金額不顯示小數點
$formatted_discount_amount = $discount_amount > 0 ? wc_price(floor($discount_amount)) : '';
?>

<tr class="points-redemption-section">
    <th><?php _e('點數折抵', 'wc-points-rewards'); ?></th>
    <td>
        <div class="wc-points-redemption-wrapper wc-points-redesigned">
            <?php if ($used_points > 0): ?>
                <!-- 已使用點數狀態 -->
                <div class="points-applied-section">
                    <div class="points-applied-summary">
                        <span class="points-applied-text">
                            <?php printf(__('✓ 已使用 %s 點數，折抵 %s', 'wc-points-rewards'), 
                                wc_points_rewards_number_format($used_points), 
                                $formatted_discount_amount
                            ); ?>
                        </span>
                        <button type="button" class="button button-secondary wc-points-remove-discount" data-nonce="<?php echo wp_create_nonce('wc_points_rewards_nonce'); ?>">
                            <?php _e('取消使用', 'wc-points-rewards'); ?>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <!-- 新設計的點數使用區塊 -->
                <div class="points-usage-redesigned">
                    <!-- a. 顯示標題：點數折抵 (已在th中顯示) -->
                    
                    <!-- b. 目前可用點數 -->
                    <div class="points-info-item available-points-info">
                        <span class="points-label"><?php _e('目前可用點數', 'wc-points-rewards'); ?>：</span>
                        <span class="points-value"><?php echo wc_points_rewards_number_format($available_points); ?><?php echo wc_points_rewards_get_points_name(); ?></span>
                    </div>
                    
                    <!-- c. 本次最多可用 -->
                    <div class="points-info-item max-usable-info">
                        <span class="points-label"><?php _e('本次最多可用', 'wc-points-rewards'); ?>：</span>
                        <span class="points-value"><?php echo wc_points_rewards_number_format($max_usable_points); ?><?php echo wc_points_rewards_get_points_name(); ?></span>
                        <?php if ($max_discount_percent < 100): ?>
                            <span class="points-note">（<?php printf(__('最多可折抵 %s%%', 'wc-points-rewards'), $max_discount_percent); ?>）</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- d. 使用點數數量輸入框 -->
                    <div class="points-input-section">
                        <div class="points-input-wrapper">
                            <label for="points-to-use" class="points-input-label"><?php _e('使用點數數量', 'wc-points-rewards'); ?>：</label>
                            <div class="points-input-group">
                                <input type="number" 
                                       id="points-to-use" 
                                       class="input-text points-input" 
                                       min="1" 
                                       max="<?php echo esc_attr($max_usable_points); ?>" 
                                       step="1" 
                                       placeholder="<?php _e('輸入點數數量', 'wc-points-rewards'); ?>" />
                                <span class="points-unit"><?php echo wc_points_rewards_get_points_name(); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- e. 使用點數按鈕 -->
                    <div class="points-action-section">
                        <button type="button" class="button button-primary wc-points-apply-discount" data-nonce="<?php echo wp_create_nonce('wc_points_rewards_nonce'); ?>" disabled>
                            <?php _e('使用點數', 'wc-points-rewards'); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="points-messages" style="display: none;"></div>
        </div>
    </td>
</tr>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // 監聽輸入框變化，控制按鈕狀態
    $('#points-to-use').on('input change', function() {
        var $input = $(this);
        var $button = $('.wc-points-apply-discount');
        var points = parseFloat($input.val());
        var maxPoints = parseFloat($input.attr('max'));
        
        // 檢查輸入是否有效
        if (points && points > 0 && points <= maxPoints) {
            $button.prop('disabled', false);
        } else {
            $button.prop('disabled', true);
        }
    });
    
    // 應用點數折扣
    $('.wc-points-apply-discount').on('click', function() {
        var $button = $(this);
        var $input = $('#points-to-use');
        var points = parseFloat($input.val());
        var nonce = $button.data('nonce');
        
        if (!points || points <= 0) {
            showPointsMessage('<?php _e('請輸入有效的點數', 'wc-points-rewards'); ?>', 'error');
            return;
        }
        
        // 確保點數為整數
        points = Math.floor(points);
        
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
                    $('body').trigger('update_checkout');
                    location.reload(); // 重新載入頁面以更新顯示
                } else {
                    showPointsMessage(response.data, 'error');
                }
            },
            error: function() {
                showPointsMessage('<?php _e('發生錯誤，請稍後再試', 'wc-points-rewards'); ?>', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e('使用點數', 'wc-points-rewards'); ?>');
            }
        });
    });
    
    // 移除點數折扣
    $('.wc-points-remove-discount').on('click', function() {
        var $button = $(this);
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
                    $('body').trigger('update_checkout');
                    location.reload(); // 重新載入頁面以更新顯示
                } else {
                    showPointsMessage(response.data, 'error');
                }
            },
            error: function() {
                showPointsMessage('<?php _e('發生錯誤，請稍後再試', 'wc-points-rewards'); ?>', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e('取消使用', 'wc-points-rewards'); ?>');
            }
        });
    });
    
    function showPointsMessage(message, type) {
        var $messages = $('.points-messages');
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