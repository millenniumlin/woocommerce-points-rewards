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

    $tables = array(
        $wpdb->prefix . 'wc_points_rewards_tiers',
        $wpdb->prefix . 'wc_points_rewards_points',
        $wpdb->prefix . 'wc_points_rewards_user_stats',
        $wpdb->prefix . 'wc_points_rewards_settings',
    );

    foreach ($tables as $table) {
        if (preg_match('/^' . preg_quote($wpdb->prefix, '/') . 'wc_points_rewards_[a-z_]+$/', $table)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated by regex above
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    $options_to_delete = array(
        'wc_points_rewards_settings',
        'wc_points_rewards_version',
        'wc_points_rewards_db_version',
        'wc_points_rewards_installed_time',
        'wc_points_rewards_enable_manual_admin_points',
        'wc_points_rewards_manual_admin_points_per_grant_max',
        'wc_points_rewards_manual_admin_points_per_admin_daily_max',
        'wc_points_rewards_manual_admin_points_site_daily_max',
        'wc_points_rewards_historical_backfill_log',
        'wc_points_rewards_flush_rewrite_rules',
        'wc_points_rewards_endpoints_flushed',
    );

    foreach ($options_to_delete as $option) {
        delete_option($option);
    }

    // 刪除用戶 meta
    $wpdb->query($wpdb->prepare(
        "DELETE FROM `{$wpdb->usermeta}` WHERE meta_key LIKE %s",
        'wc_points_rewards_%'
    ));

    // 刪除訂單 meta（同時清理 HPOS 和舊版 postmeta）
    $meta_keys    = array('_points_awarded', '_points_discount_amount', '_points_used');
    $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));

    // 清理傳統 post meta
    $wpdb->query($wpdb->prepare(
        "DELETE FROM `{$wpdb->postmeta}` WHERE meta_key IN ({$placeholders})",
        ...$meta_keys
    ));

    // 清理 HPOS order meta（如存在）
    $hpos_meta_table = $wpdb->prefix . 'wc_orders_meta';
    $hpos_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $hpos_meta_table)) === $hpos_meta_table;
    if ($hpos_table_exists) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$hpos_meta_table}` WHERE meta_key IN ({$placeholders})",
            ...$meta_keys
        ));
    }
}

/**
 * 清理排程任務
 *
 * [修正 LOGIC-10] 補上 daily_birthday_check cron 的清理，原先僅清除三個，漏掉此項
 */
function wc_points_rewards_cleanup_cron() {
    wp_clear_scheduled_hook('wc_points_rewards_daily_cleanup');
    wp_clear_scheduled_hook('wc_points_rewards_notification_check');
    wp_clear_scheduled_hook('wc_points_rewards_weekly_report');
    wp_clear_scheduled_hook('wc_points_rewards_daily_birthday_check'); // [修正 LOGIC-10]
}

/**
 * 清理快取
 */
function wc_points_rewards_cleanup_cache() {
    wp_cache_flush();

    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('wc_points_rewards');
    }

    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_points_rewards_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wc_points_rewards_%'");
}

/**
 * 清理上傳的文件
 */
function wc_points_rewards_cleanup_files() {
    $upload_dir       = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/wc-points-rewards/';

    if (is_dir($plugin_upload_dir)) {
        wc_points_rewards_recursive_rmdir($plugin_upload_dir);
    }
}

/**
 * 遞歸刪除目錄
 */
function wc_points_rewards_recursive_rmdir($dir) {
    if (!is_dir($dir)) {
        return;
    }

    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ('.' === $object || '..' === $object) {
            continue;
        }
        $path = $dir . '/' . $object;
        if (is_dir($path)) {
            wc_points_rewards_recursive_rmdir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
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
        'timestamp'      => current_time('mysql'),
        'user_id'        => get_current_user_id(),
        'site_url'       => site_url(),
        'plugin_version' => get_option('wc_points_rewards_version', 'unknown'),
    );
    // 可選：記錄至外部統計服務
    // wp_remote_post('https://your-analytics-endpoint.com/uninstall', array('body' => $log_data));
}

// 詢問用戶是否要刪除所有數據
$delete_data = get_option('wc_points_rewards_delete_data_on_uninstall', false);

if ($delete_data) {
    wc_points_rewards_cleanup_database();
    wc_points_rewards_cleanup_cron();
    wc_points_rewards_cleanup_cache();
    wc_points_rewards_cleanup_files();
    wc_points_rewards_cleanup_rewrite_rules();
    wc_points_rewards_log_uninstall();
} else {
    // 只清理臨時數據（cron 一定要清）
    wc_points_rewards_cleanup_cron();
    wc_points_rewards_cleanup_cache();
    wc_points_rewards_cleanup_rewrite_rules();
}
