<?php
/**
 * 安裝管理類別
 * 
 * @package ML_License_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 安裝管理類別
 */
class ML_License_Manager_Install {
    
    /**
     * 執行安裝
     */
    public static function install() {
        // 創建資料庫表格
        self::create_tables();
        
        // 設定預設選項
        self::set_default_options();
        
        // 記錄安裝時間
        update_option('ml_license_manager_installed_time', current_time('mysql'));
        
        // 清除快取
        wp_cache_flush();
    }
    
    /**
     * 創建資料庫表格
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // 授權碼表
        $licenses_table = $wpdb->prefix . 'ml_license_keys';
        $licenses_sql = "CREATE TABLE $licenses_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            license_key varchar(255) NOT NULL COMMENT '授權碼',
            product_id bigint(20) unsigned DEFAULT NULL COMMENT '產品ID',
            order_id bigint(20) unsigned DEFAULT NULL COMMENT '訂單ID',
            user_id bigint(20) unsigned DEFAULT NULL COMMENT '用戶ID',
            status varchar(50) NOT NULL DEFAULT 'active' COMMENT '狀態：active, inactive, expired, revoked',
            activation_limit int(11) NOT NULL DEFAULT 1 COMMENT '啟用次數限制',
            activation_count int(11) NOT NULL DEFAULT 0 COMMENT '已啟用次數',
            expires_at datetime DEFAULT NULL COMMENT '過期時間',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_license_key (license_key),
            KEY idx_product_id (product_id),
            KEY idx_order_id (order_id),
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_expires_at (expires_at)
        ) $charset_collate COMMENT='授權碼表';";
        
        dbDelta($licenses_sql);
        
        // 授權碼啟用記錄表
        $activations_table = $wpdb->prefix . 'ml_license_activations';
        $activations_sql = "CREATE TABLE $activations_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            license_key_id bigint(20) unsigned NOT NULL COMMENT '授權碼ID',
            activation_token varchar(255) NOT NULL COMMENT '啟用令牌',
            instance_name varchar(255) DEFAULT NULL COMMENT '實例名稱',
            instance_id varchar(255) DEFAULT NULL COMMENT '實例識別碼',
            ip_address varchar(45) DEFAULT NULL COMMENT 'IP地址',
            status varchar(50) NOT NULL DEFAULT 'active' COMMENT '狀態：active, deactivated',
            activated_at datetime DEFAULT CURRENT_TIMESTAMP,
            deactivated_at datetime DEFAULT NULL,
            last_checked_at datetime DEFAULT NULL COMMENT '最後檢查時間',
            PRIMARY KEY (id),
            UNIQUE KEY uk_activation_token (activation_token),
            KEY idx_license_key_id (license_key_id),
            KEY idx_status (status),
            KEY idx_instance_id (instance_id)
        ) $charset_collate COMMENT='授權碼啟用記錄表';";
        
        dbDelta($activations_sql);
        
        // 授權碼活動日誌表
        $logs_table = $wpdb->prefix . 'ml_license_logs';
        $logs_sql = "CREATE TABLE $logs_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            license_key_id bigint(20) unsigned NOT NULL COMMENT '授權碼ID',
            action varchar(50) NOT NULL COMMENT '動作：created, activated, deactivated, expired, renewed, revoked',
            description text COMMENT '描述',
            ip_address varchar(45) DEFAULT NULL COMMENT 'IP地址',
            user_agent text COMMENT 'User Agent',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_license_key_id (license_key_id),
            KEY idx_action (action),
            KEY idx_created_at (created_at)
        ) $charset_collate COMMENT='授權碼活動日誌表';";
        
        dbDelta($logs_sql);
        
        // 授權碼元數據表
        $meta_table = $wpdb->prefix . 'ml_license_meta';
        $meta_sql = "CREATE TABLE $meta_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            license_key_id bigint(20) unsigned NOT NULL COMMENT '授權碼ID',
            meta_key varchar(255) NOT NULL COMMENT '鍵',
            meta_value longtext COMMENT '值',
            PRIMARY KEY (id),
            KEY idx_license_key_id (license_key_id),
            KEY idx_meta_key (meta_key)
        ) $charset_collate COMMENT='授權碼元數據表';";
        
        dbDelta($meta_sql);
    }
    
    /**
     * 設定預設選項
     */
    private static function set_default_options() {
        // 檢查是否已經有設定
        if (get_option('ml_license_manager_settings')) {
            return;
        }
        
        $default_settings = array(
            // 基本設定
            'enable_license_system' => 'yes',
            'default_activation_limit' => 1,
            'default_expiry_days' => 365,
            
            // 授權碼生成設定
            'license_key_length' => 32,
            'license_key_format' => 'alphanumeric', // alphanumeric, hex, custom
            'license_key_prefix' => '',
            'license_key_suffix' => '',
            
            // 驗證設定
            'require_domain_binding' => 'no',
            'allow_localhost' => 'yes',
            'check_interval_hours' => 24,
            
            // 通知設定
            'notify_on_activation' => 'yes',
            'notify_on_expiry' => 'yes',
            'notify_days_before_expiry' => 30,
            
            // API設定
            'enable_api' => 'yes',
            'require_api_authentication' => 'yes',
        );
        
        add_option('ml_license_manager_settings', $default_settings);
    }
    
    /**
     * 更新資料庫
     */
    public static function update_database() {
        self::create_tables();
    }
    
    /**
     * 刪除資料庫表格（用於卸載）
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'ml_license_keys',
            $wpdb->prefix . 'ml_license_activations',
            $wpdb->prefix . 'ml_license_logs',
            $wpdb->prefix . 'ml_license_meta',
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
}
