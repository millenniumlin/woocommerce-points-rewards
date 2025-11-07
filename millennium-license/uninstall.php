<?php
/**
 * Millennium License Manager - Uninstall
 * 
 * 當外掛被卸載時執行清理工作
 */

// 如果不是透過 WordPress 卸載程序，則退出
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 刪除資料庫表格
global $wpdb;

$tables = array(
    $wpdb->prefix . 'millennium_licenses',
    $wpdb->prefix . 'millennium_license_activations',
    $wpdb->prefix . 'millennium_license_logs'
);

foreach ($tables as $table) {
    // 驗證表格名稱以防止 SQL 注入
    $table_name = esc_sql($table);
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_name));
}

// 刪除選項設定
delete_option('millennium_license_settings');
delete_option('millennium_license_version');
delete_option('millennium_license_api_key');

// 刪除所有產品的授權設定 meta
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} 
     WHERE meta_key LIKE '_millennium_license_%'"
);

// 刪除所有訂單的授權碼 meta
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} 
     WHERE meta_key = '_millennium_license_keys'"
);

// 清除快取
wp_cache_flush();
