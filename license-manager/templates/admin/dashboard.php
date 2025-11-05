<?php
/**
 * 管理介面 - 儀表板
 * 
 * @package ML_License_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('授權管理儀表板', 'ml-license-manager'); ?></h1>
    
    <div class="ml-license-dashboard">
        <div class="ml-stats-grid">
            <!-- 總授權碼數 -->
            <div class="ml-stat-box">
                <div class="ml-stat-icon dashicons dashicons-admin-network"></div>
                <div class="ml-stat-content">
                    <h3><?php echo esc_html($total_licenses); ?></h3>
                    <p><?php echo esc_html__('總授權碼數', 'ml-license-manager'); ?></p>
                </div>
            </div>
            
            <!-- 啟用中 -->
            <div class="ml-stat-box ml-stat-success">
                <div class="ml-stat-icon dashicons dashicons-yes-alt"></div>
                <div class="ml-stat-content">
                    <h3><?php echo esc_html($active_licenses); ?></h3>
                    <p><?php echo esc_html__('啟用中', 'ml-license-manager'); ?></p>
                </div>
            </div>
            
            <!-- 已過期 -->
            <div class="ml-stat-box ml-stat-warning">
                <div class="ml-stat-icon dashicons dashicons-clock"></div>
                <div class="ml-stat-content">
                    <h3><?php echo esc_html($expired_licenses); ?></h3>
                    <p><?php echo esc_html__('已過期', 'ml-license-manager'); ?></p>
                </div>
            </div>
            
            <!-- 啟用實例數 -->
            <div class="ml-stat-box ml-stat-info">
                <div class="ml-stat-icon dashicons dashicons-desktop"></div>
                <div class="ml-stat-content">
                    <h3><?php echo esc_html($total_activations); ?></h3>
                    <p><?php echo esc_html__('啟用實例數', 'ml-license-manager'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="ml-quick-actions">
            <h2><?php echo esc_html__('快速操作', 'ml-license-manager'); ?></h2>
            <div class="ml-actions-grid">
                <a href="<?php echo esc_url(admin_url('admin.php?page=ml-license-add')); ?>" class="button button-primary button-large">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php echo esc_html__('新增授權碼', 'ml-license-manager'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ml-license-keys')); ?>" class="button button-large">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php echo esc_html__('查看所有授權碼', 'ml-license-manager'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ml-license-settings')); ?>" class="button button-large">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php echo esc_html__('系統設定', 'ml-license-manager'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.ml-license-dashboard {
    margin-top: 20px;
}

.ml-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.ml-stat-box {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.ml-stat-box.ml-stat-success {
    border-left: 4px solid #46b450;
}

.ml-stat-box.ml-stat-warning {
    border-left: 4px solid #ffb900;
}

.ml-stat-box.ml-stat-info {
    border-left: 4px solid #00a0d2;
}

.ml-stat-icon {
    font-size: 48px;
    width: 64px;
    height: 64px;
    margin-right: 20px;
    color: #666;
}

.ml-stat-content h3 {
    margin: 0;
    font-size: 32px;
    font-weight: 600;
    color: #333;
}

.ml-stat-content p {
    margin: 5px 0 0 0;
    color: #666;
    font-size: 14px;
}

.ml-quick-actions {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.ml-quick-actions h2 {
    margin-top: 0;
    margin-bottom: 15px;
}

.ml-actions-grid {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.ml-actions-grid .button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.ml-actions-grid .button .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}
</style>
