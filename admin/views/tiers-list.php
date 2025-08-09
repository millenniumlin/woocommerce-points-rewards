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
                            <span class="bonus-percentage">+<?php echo esc_html($tier->bonus_percentage); ?>%</span>
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
.tier-management-intro {
