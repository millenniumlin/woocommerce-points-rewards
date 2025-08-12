<?php
/**
 * 管理後台 - 會員等級列表
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('會員等級管理', 'wc-points-rewards'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=wc-points-rewards-tiers&action=add'); ?>" class="page-title-action">
        <?php _e('新增等級', 'wc-points-rewards'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <div class="tier-management-intro">
        <p><?php _e('管理會員等級設定，包括升級條件和回饋百分比。等級會根據用戶的年度消費金額自動升級。', 'wc-points-rewards'); ?></p>
    </div>
    
    <?php if (!empty($tiers)): ?>
    <div class="tiers-list-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-order"><?php _e('排序', 'wc-points-rewards'); ?></th>
                    <th class="column-name"><?php _e('等級名稱', 'wc-points-rewards'); ?></th>
                    <th class="column-amount"><?php _e('最低消費金額', 'wc-points-rewards'); ?></th>
                    <th class="column-bonus"><?php _e('額外回饋', 'wc-points-rewards'); ?></th>
                    <th class="column-users"><?php _e('用戶數量', 'wc-points-rewards'); ?></th>
                    <th class="column-actions"><?php _e('操作', 'wc-points-rewards'); ?></th>
                </tr>
            </thead>
            <tbody class="tiers-sortable">
                <?php foreach ($tiers as $tier): ?>
                <?php
                // 獲取使用此等級的用戶數量
                global $wpdb;
                $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
                $user_count = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM $stats_table 
                    WHERE current_tier_id = %d 
                    AND year = %d
                    AND (tier_expiry_date IS NULL OR tier_expiry_date > NOW())
                ", $tier->id, date('Y')));
                ?>
                <tr class="tier-row" data-tier-id="<?php echo esc_attr($tier->id); ?>">
                    <td class="column-order">
                        <span class="tier-handle dashicons dashicons-menu" title="<?php _e('拖拽排序', 'wc-points-rewards'); ?>"></span>
                        <span class="tier-order-number"><?php echo esc_html($tier->tier_order); ?></span>
                    </td>
                    <td class="column-name">
                        <strong><?php echo esc_html($tier->name); ?></strong>
                        <?php if ($tier->tier_order === 1): ?>
                            <span class="default-tier-badge"><?php _e('預設', 'wc-points-rewards'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="column-amount">
                        <?php if ($tier->min_amount > 0): ?>
                            <?php echo wc_price($tier->min_amount); ?>
                        <?php else: ?>
                            <span class="no-requirement"><?php _e('無門檻', 'wc-points-rewards'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="column-bonus">
                        <?php if ($tier->bonus_percentage > 0): ?>
                            <span class="bonus-percentage">+<?php echo esc_html(wc_points_rewards_format_percentage($tier->bonus_percentage)); ?></span>
                        <?php else: ?>
                            <span class="no-bonus"><?php _e('無加成', 'wc-points-rewards'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="column-users">
                        <span class="user-count"><?php echo number_format($user_count); ?></span>
                        <?php if ($user_count > 0): ?>
                            <a href="<?php echo admin_url('users.php?tier_id=' . $tier->id); ?>" class="view-users-link">
                                <?php _e('查看', 'wc-points-rewards'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td class="column-actions">
                        <div class="tier-actions">
                            <a href="<?php echo admin_url('admin.php?page=wc-points-rewards-tiers&action=edit&tier_id=' . $tier->id); ?>" 
                               class="button button-small">
                                <?php _e('編輯', 'wc-points-rewards'); ?>
                            </a>
                            
                            <?php if ($tier->tier_order > 1 && $user_count == 0): ?>
                            <button type="button" 
                                    class="button button-small delete-tier-btn" 
                                    data-tier-id="<?php echo esc_attr($tier->id); ?>">
                                <?php _e('刪除', 'wc-points-rewards'); ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="tier-management-notes">
        <h3><?php _e('使用說明', 'wc-points-rewards'); ?></h3>
        <ul>
            <li><?php _e('拖拽等級名稱左側的圖標可以調整等級順序', 'wc-points-rewards'); ?></li>
            <li><?php _e('最低消費金額為0的等級會成為預設等級', 'wc-points-rewards'); ?></li>
            <li><?php _e('有用戶使用的等級無法刪除，請先遷移用戶至其他等級', 'wc-points-rewards'); ?></li>
            <li><?php _e('等級升級基於用戶的年度累積消費金額', 'wc-points-rewards'); ?></li>
            <li><?php _e('額外回饋百分比會應用於用戶購物獲得的點數', 'wc-points-rewards'); ?></li>
        </ul>
    </div>
    
    <?php else: ?>
    <div class="no-tiers-message">
        <div class="no-tiers-icon">⭐</div>
        <h3><?php _e('尚未設定會員等級', 'wc-points-rewards'); ?></h3>
        <p><?php _e('建立您的第一個會員等級，開始提供用戶更好的購物體驗。', 'wc-points-rewards'); ?></p>
        <a href="<?php echo admin_url('admin.php?page=wc-points-rewards-tiers&action=add'); ?>" class="button button-primary">
            <?php _e('建立等級', 'wc-points-rewards'); ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<style>
.tier-management-intro {
    background: #f0f6fc;
    border: 1px solid #c5d9ed;
    border-radius: 4px;
    padding: 15px;
    margin: 20px 0;
}

.tier-management-intro p {
    margin: 0;
    color: #1e3a5f;
}

.tiers-list-container {
    margin: 20px 0;
}

.column-order {
    width: 80px;
}

.column-name {
    width: 200px;
}

.column-amount,
.column-bonus,
.column-users {
    width: 120px;
}

.column-actions {
    width: 150px;
}

.tier-handle {
    cursor: move;
    color: #8c8f94;
    margin-right: 10px;
}

.tier-handle:hover {
    color: #2c3338;
}

.tier-order-number {
    font-weight: bold;
    color: #646970;
}

.default-tier-badge {
    display: inline-block;
    background: #00a32a;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    margin-left: 8px;
    font-weight: normal;
}

.no-requirement,
.no-bonus {
    color: #8c8f94;
    font-style: italic;
}

.bonus-percentage {
    color: #00a32a;
    font-weight: bold;
}

.user-count {
    font-weight: bold;
}

.view-users-link {
    margin-left: 5px;
    font-size: 12px;
    text-decoration: none;
}

.tier-actions {
    display: flex;
    gap: 5px;
}

.tier-management-notes {
    background: white;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    margin: 30px 0;
}

.tier-management-notes h3 {
    margin-top: 0;
    color: #1d2327;
}

.tier-management-notes ul {
    margin: 15px 0;
    padding-left: 25px;
}

.tier-management-notes li {
    margin-bottom: 8px;
    line-height: 1.5;
    color: #646970;
}

.no-tiers-message {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    margin: 20px 0;
}

.no-tiers-icon {
    font-size: 4em;
    margin-bottom: 20px;
}

.no-tiers-message h3 {
    margin-bottom: 15px;
    color: #1d2327;
}

.no-tiers-message p {
    color: #646970;
    margin-bottom: 25px;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.tier-row.ui-sortable-helper {
    background: #f6f7f7 !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.tier-row.ui-sortable-placeholder {
    background: #ddd !important;
    visibility: visible !important;
    height: 50px;
}

@media (max-width: 768px) {
    .tier-actions {
        flex-direction: column;
    }
    
    .column-order,
    .column-users {
        display: none;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // 初始化排序功能
    if ($('.tiers-sortable').length > 0) {
        $('.tiers-sortable').sortable({
            handle: '.tier-handle',
            axis: 'y',
            placeholder: 'ui-sortable-placeholder',
            update: function(event, ui) {
                updateTierOrder();
            }
        });
    }
    
    function updateTierOrder() {
        var orders = [];
        $('.tier-row').each(function(index) {
            var tierId = $(this).data('tier-id');
            if (tierId) {
                orders.push({
                    id: tierId,
                    order: index + 1
                });
                // 更新顯示的順序號碼
                $(this).find('.tier-order-number').text(index + 1);
            }
        });
        
        // 發送 AJAX 請求保存排序
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_points_rewards_update_tier_order',
                nonce: '<?php echo wp_create_nonce('wc_points_rewards_admin_nonce'); ?>',
                orders: orders
            }
        });
    }
    
    // 刪除等級
    $('.delete-tier-btn').on('click', function() {
        if (!confirm('<?php _e('確定要刪除此等級嗎？此操作無法復原。', 'wc-points-rewards'); ?>')) {
            return;
        }
        
        var $button = $(this);
        var tierId = $button.data('tier-id');
        
        $button.prop('disabled', true).text('<?php _e('刪除中...', 'wc-points-rewards'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_points_rewards_delete_tier',
                nonce: '<?php echo wp_create_nonce('wc_points_rewards_admin_nonce'); ?>',
                tier_id: tierId
            },
            success: function(response) {
                if (response.success) {
                    $button.closest('.tier-row').fadeOut(500, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data);
                    $button.prop('disabled', false).text('<?php _e('刪除', 'wc-points-rewards'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('發生錯誤，請稍後再試', 'wc-points-rewards'); ?>');
                $button.prop('disabled', false).text('<?php _e('刪除', 'wc-points-rewards'); ?>');
            }
        });
    });
});
</script>