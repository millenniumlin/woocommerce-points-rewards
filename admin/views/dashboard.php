<?php
/**
 * 管理後台 - 儀表板頁面
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

// 安全地處理數值，避免 null 值
$total_users = intval($total_users);
$total_points_issued = floatval($total_points_issued ?? 0);
$total_points_redeemed = floatval($total_points_redeemed ?? 0);
$total_tiers = intval($total_tiers);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('點數獎勵儀表板', 'wc-points-rewards'); ?></h1>
    
    <!-- 統計卡片 -->
    <div class="dashboard-stats">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <h3><?php _e('總用戶數', 'wc-points-rewards'); ?></h3>
                    <div class="stat-number"><?php echo number_format($total_users); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💎</div>
                <div class="stat-content">
                    <h3><?php _e('已發放點數', 'wc-points-rewards'); ?></h3>
                    <div class="stat-number"><?php echo wc_points_rewards_number_format($total_points_issued); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🛒</div>
                <div class="stat-content">
                    <h3><?php _e('已使用點數', 'wc-points-rewards'); ?></h3>
                    <div class="stat-number"><?php echo wc_points_rewards_number_format($total_points_redeemed); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-content">
                    <h3><?php _e('會員等級數', 'wc-points-rewards'); ?></h3>
                    <div class="stat-number"><?php echo number_format($total_tiers); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 快速操作 -->
    <div class="dashboard-actions">
        <h2><?php _e('快速操作', 'wc-points-rewards'); ?></h2>
        <div class="action-buttons">
            <a href="<?php echo admin_url('admin.php?page=wc-points-rewards-settings'); ?>" class="button button-primary">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('系統設定', 'wc-points-rewards'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=wc-points-rewards-tiers'); ?>" class="button">
                <span class="dashicons dashicons-star-filled"></span>
                <?php _e('管理等級', 'wc-points-rewards'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=wc-points-rewards-points'); ?>" class="button">
                <span class="dashicons dashicons-list-view"></span>
                <?php _e('點數記錄', 'wc-points-rewards'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=wc-points-rewards-reports'); ?>" class="button">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php _e('查看報表', 'wc-points-rewards'); ?>
            </a>
        </div>
    </div>
    
    <div class="dashboard-content">
        <div class="left-column">
            <!-- 最近活動 -->
            <div class="dashboard-widget">
                <h3><?php _e('最近點數活動', 'wc-points-rewards'); ?></h3>
                
                <?php if (!empty($recent_activities)): ?>
                <div class="recent-activities">
                    <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item activity-<?php echo esc_attr($activity->type); ?>">
                        <div class="activity-icon">
                            <?php
                            switch ($activity->type) {
                                case 'earned':
                                    echo '💎';
                                    break;
                                case 'redeemed':
                                    echo '🛒';
                                    break;
                                case 'expired':
                                    echo '⏰';
                                    break;
                                case 'admin':
                                    echo '⚙️';
                                    break;
                                default:
                                    echo '📝';
                            }
                            ?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-user">
                                <?php echo esc_html($activity->display_name ?: __('未知用戶', 'wc-points-rewards')); ?>
                            </div>
                            <div class="activity-description">
                                <?php echo esc_html($activity->description); ?>
                            </div>
                            <div class="activity-meta">
                                <span class="activity-points <?php echo floatval($activity->points) > 0 ? 'positive' : 'negative'; ?>">
                                    <?php echo floatval($activity->points) > 0 ? '+' : ''; ?><?php echo wc_points_rewards_number_format(floatval($activity->points)); ?>
                                </span>
                                <span class="activity-date">
                                    <?php echo human_time_diff(strtotime($activity->created_at), current_time('timestamp')); ?> <?php _e('前', 'wc-points-rewards'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="widget-footer">
                    <a href="<?php echo admin_url('admin.php?page=wc-points-rewards-points'); ?>" class="button">
                        <?php _e('查看全部記錄', 'wc-points-rewards'); ?>
                    </a>
                </div>
                
                <?php else: ?>
                <div class="no-activities">
                    <p><?php _e('暫無點數活動記錄', 'wc-points-rewards'); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="right-column">
            <!-- 系統狀態 -->
            <div class="dashboard-widget">
                <h3><?php _e('系統狀態', 'wc-points-rewards'); ?></h3>
                
                <div class="system-status">
                    <?php
                    $settings = get_option('wc_points_rewards_settings', array());
                    $plugin_version = WC_POINTS_REWARDS_VERSION;
                    $wp_version = get_bloginfo('version');
                    $wc_version = defined('WC_VERSION') ? WC_VERSION : 'N/A';
                    ?>
                    
                    <div class="status-item">
                        <span class="status-label"><?php _e('外掛版本', 'wc-points-rewards'); ?>:</span>
                        <span class="status-value"><?php echo esc_html($plugin_version); ?></span>
                    </div>
                    
                    <div class="status-item">
                        <span class="status-label"><?php _e('WordPress 版本', 'wc-points-rewards'); ?>:</span>
                        <span class="status-value"><?php echo esc_html($wp_version); ?></span>
                    </div>
                    
                    <div class="status-item">
                        <span class="status-label"><?php _e('WooCommerce 版本', 'wc-points-rewards'); ?>:</span>
                        <span class="status-value"><?php echo esc_html($wc_version); ?></span>
                    </div>
                    
                    <div class="status-item">
                        <span class="status-label"><?php _e('點數系統狀態', 'wc-points-rewards'); ?>:</span>
                        <span class="status-value status-<?php echo isset($settings['enable_points_system']) && $settings['enable_points_system'] === 'yes' ? 'enabled' : 'disabled'; ?>">
                            <?php echo isset($settings['enable_points_system']) && $settings['enable_points_system'] === 'yes' ? __('已啟用', 'wc-points-rewards') : __('已停用', 'wc-points-rewards'); ?>
                        </span>
                    </div>
                    
                    <div class="status-item">
                        <span class="status-label"><?php _e('通知功能', 'wc-points-rewards'); ?>:</span>
                        <span class="status-value status-<?php echo isset($settings['enable_notifications']) && $settings['enable_notifications'] === 'yes' ? 'enabled' : 'disabled'; ?>">
                            <?php echo isset($settings['enable_notifications']) && $settings['enable_notifications'] === 'yes' ? __('已啟用', 'wc-points-rewards') : __('已停用', 'wc-points-rewards'); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- 快速統計 -->
            <div class="dashboard-widget">
    <h3><?php _e('今日統計', 'wc-points-rewards'); ?></h3>
    
    <?php
    global $wpdb;
    $points_table = $wpdb->prefix . 'wc_points_rewards_points';
    $today = date('Y-m-d');
    
    $today_earned_raw = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(points) FROM $points_table 
        WHERE type = 'earned' AND DATE(created_at) = %s
    ", $today));
    
    // 修正第 204 行 - 先處理 null 值再使用 abs()
    $today_redeemed_raw = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(points) FROM $points_table 
        WHERE type = 'redeemed' AND DATE(created_at) = %s
    ", $today));
    
    $today_users_raw = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT user_id) FROM $points_table 
        WHERE DATE(created_at) = %s
    ", $today));
    
    // 安全處理 null 值
    $today_earned = floatval($today_earned_raw ?? 0);
    $today_redeemed = abs(floatval($today_redeemed_raw ?? 0));
    $today_users = intval($today_users_raw ?? 0);
    ?>
    
    <div class="today-stats">
        <div class="today-stat">
            <div class="today-stat-label"><?php _e('今日發放點數', 'wc-points-rewards'); ?></div>
            <div class="today-stat-value"><?php echo wc_points_rewards_number_format($today_earned); ?></div>
        </div>
        
        <div class="today-stat">
            <div class="today-stat-label"><?php _e('今日使用點數', 'wc-points-rewards'); ?></div>
            <div class="today-stat-value"><?php echo wc_points_rewards_number_format($today_redeemed); ?></div>
        </div>
        
        <div class="today-stat">
            <div class="today-stat-label"><?php _e('活躍用戶數', 'wc-points-rewards'); ?></div>
            <div class="today-stat-value"><?php echo number_format($today_users); ?></div>
        </div>
    </div>
</div>
        </div>
    </div>
</div>