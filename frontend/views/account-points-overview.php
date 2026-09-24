<?php
/**
 * 我的帳戶 - 點數總覽模板
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wc-points-rewards-overview">
    <div class="points-summary-cards">
        <!-- 目前點數餘額 -->
        <div class="points-card points-balance-card">
            <div class="card-icon">💎</div>
            <div class="card-content">
                <h3><?php _e('目前點數', 'wc-points-rewards'); ?></h3>
                <div class="points-value"><?php echo wc_points_rewards_number_format($current_points); ?></div>
                <div class="points-label"><?php echo wc_points_rewards_get_points_name(); ?></div>
                <div class="points-value-info"><?php echo wc_price($current_points * wc_points_rewards_get_points_value()); ?></div>
            </div>
        </div>
        
        <!-- 會員等級 -->
        <div class="points-card tier-card">
            <div class="card-icon">⭐</div>
            <div class="card-content">
                <h3><?php _e('會員等級', 'wc-points-rewards'); ?></h3>
                <div class="tier-name"><?php echo esc_html($current_tier->name); ?></div>
                <?php if ($current_tier->bonus_percentage > 0): ?>
                    <div class="tier-benefit">+<?php echo wc_points_rewards_format_percentage($current_tier->bonus_percentage); ?> <?php _e('回饋', 'wc-points-rewards'); ?></div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 即將到期點數 -->
        <?php if ($expiring_points > 0): ?>
        <div class="points-card expiring-card">
            <div class="card-icon">⏰</div>
            <div class="card-content">
                <h3><?php _e('即將到期', 'wc-points-rewards'); ?></h3>
                <div class="expiring-points"><?php echo wc_points_rewards_number_format($expiring_points); ?></div>
                <div class="expiring-label"><?php _e('30天內到期', 'wc-points-rewards'); ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- 最近點數記錄 -->
    <?php if (!empty($recent_history)): ?>
    <div class="recent-history-section">
        <h3><?php _e('最近記錄', 'wc-points-rewards'); ?></h3>
        <div class="points-history-list">
            <?php foreach ($recent_history as $record): ?>
            <div class="history-item history-<?php echo esc_attr($record->type); ?>">
                <div class="history-date">
                    <?php echo date('m/d H:i', strtotime($record->created_at)); ?>
                </div>
                <div class="history-description">
                    <?php echo esc_html($record->description); ?>
                </div>
                <div class="history-points <?php echo $record->points > 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $record->points > 0 ? '+' : ''; ?><?php echo wc_points_rewards_number_format($record->points); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="view-all-link">
            <a href="<?php echo wc_points_rewards_get_account_endpoint_url('points-history'); ?>" class="button">
                <?php _e('查看全部記錄', 'wc-points-rewards'); ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 快速操作 -->
    <div class="quick-actions-section">
        <h3><?php _e('快速操作', 'wc-points-rewards'); ?></h3>
        <div class="action-buttons">
            <a href="<?php echo wc_get_page_permalink('shop'); ?>" class="button button-primary">
                <?php _e('立即購物', 'wc-points-rewards'); ?>
            </a>
            <a href="<?php echo wc_points_rewards_get_account_endpoint_url('points-history'); ?>" class="button">
                <?php _e('查看記錄', 'wc-points-rewards'); ?>
            </a>
            <a href="<?php echo wc_points_rewards_get_account_endpoint_url('member-tier'); ?>" class="button">
                <?php _e('會員等級', 'wc-points-rewards'); ?>
            </a>
        </div>
    </div>
</div>