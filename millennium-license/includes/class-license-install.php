<?php
/**
 * License Installation and Database Setup
 */

if (!defined('ABSPATH')) {
    exit;
}

class Millennium_License_Install {
    
    /**
     * 創建資料庫表格
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 授權碼表格
        $table_licenses = $wpdb->prefix . 'millennium_licenses';
        
        $sql_licenses = "CREATE TABLE IF NOT EXISTS $table_licenses (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            license_key varchar(255) NOT NULL,
            product_id bigint(20) unsigned DEFAULT NULL,
            order_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            max_activations int(11) DEFAULT 1,
            activation_count int(11) DEFAULT 0,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY license_key (license_key),
            KEY product_id (product_id),
            KEY order_id (order_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        // 授權碼啟用記錄表格
        $table_activations = $wpdb->prefix . 'millennium_license_activations';
        
        $sql_activations = "CREATE TABLE IF NOT EXISTS $table_activations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            license_id bigint(20) unsigned NOT NULL,
            activation_token varchar(255) NOT NULL,
            site_url varchar(255) DEFAULT NULL,
            instance_id varchar(255) DEFAULT NULL,
            activated_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_checked datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            metadata longtext DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY activation_token (activation_token),
            KEY license_id (license_id),
            KEY status (status)
        ) $charset_collate;";
        
        // 授權碼使用日誌表格
        $table_logs = $wpdb->prefix . 'millennium_license_logs';
        
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            license_id bigint(20) unsigned NOT NULL,
            action varchar(50) NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY license_id (license_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_licenses);
        dbDelta($sql_activations);
        dbDelta($sql_logs);
    }
    
    /**
     * 刪除資料庫表格
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'millennium_licenses',
            $wpdb->prefix . 'millennium_license_activations',
            $wpdb->prefix . 'millennium_license_logs'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}
