<?php
/**
 * 外掛卸載腳本
 * 
 * @package WC_Points_Rewards
 */

// 如果不是通過 WordPress 卸載，則退出
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * 清理資料庫
 */
function wc_points_rewards_cleanup_database() {
    global $wpdb;
    
    // 刪除外掛表格
    $tables = array(
        $wpdb->prefix . 'wc_points_rewards_tiers',
        $wpdb->prefix . 'wc_points_rewards_points',
        $wpdb->prefix . 'wc_points_rewards_user_stats',
        $wpdb->prefix . 'wc_points_rewards_settings'
    );
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
    
    // 刪除選項
    $options_to_delete = array(
        'wc_points_rewards_settings',
        'wc_points_rewards_version',
        'wc_points_rewards_db_version',
        'wc_points_rewards_installed_time'
    );
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // 刪除用戶 meta
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wc_points_rewards_%'");
    
    // 刪除訂單 meta
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_points_awarded', '_points_discount_amount', '_points_used')");
}

/**
 * 清理排程任務
 */
function wc_points_rewards_cleanup_cron() {
    wp_clear_scheduled_hook('wc_points_rewards_daily_cleanup');
    wp_clear_scheduled_hook('wc_points_rewards_notification_check');
    wp_clear_scheduled_hook('wc_points_rewards_weekly_report');
}

/**
 * 清理快取
 */
function wc_points_rewards_cleanup_cache() {
    // 清除物件快取
    wp_cache_flush();
    
    // 如果使用了 Redis 或 Memcached
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('wc_points_rewards');
    }
    
    // 清除 transients
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_points_rewards_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wc_points_rewards_%'");
}

/**
 * 清理上傳的文件
 */
function wc_points_rewards_cleanup_files() {
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/wc-points-rewards/';
    
    if (is_dir($plugin_upload_dir)) {
        // 遞歸刪除目錄
        wc_points_rewards_recursive_rmdir($plugin_upload_dir);
    }
}

/**
 * 遞歸刪除目錄
 */
function wc_points_rewards_recursive_rmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . "/" . $object)) {
                    wc_points_rewards_recursive_rmdir($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }
            }
        }
        rmdir($dir);
    }
}

/**
 * 重置重寫規則
 */
function wc_points_rewards_cleanup_rewrite_rules() {
    flush_rewrite_rules();
}

/**
 * 記錄卸載日誌
 */
function wc_points_rewards_log_uninstall() {
    $log_data = array(
        'timestamp' => current_time('mysql'),
        'user_id' => get_current_user_id(),
        'site_url' => site_url(),
        'plugin_version' => get_option('wc_points_rewards_version', 'unknown')
    );
    
    // 可以發送到外部服務進行統計（可選）
    // wp_remote_post('https://your-analytics-endpoint.com/uninstall', array('body' => $log_data));
}

// 詢問用戶是否要刪除所有數據
$delete_data = get_option('wc_points_rewards_delete_data_on_uninstall', false);

if ($delete_data) {
    // 執行完整清理
    wc_points_rewards_cleanup_database();
    wc_points_rewards_cleanup_cron();
    wc_points_rewards_cleanup_cache();
    wc_points_rewards_cleanup_files();
    wc_points_rewards_cleanup_rewrite_rules();
    wc_points_rewards_log_uninstall();
} else {
    // 只清理臨時數據
    wc_points_rewards_cleanup_cron();
    wc_points_rewards_cleanup_cache();
    wc_points_rewards_cleanup_rewrite_rules();
}