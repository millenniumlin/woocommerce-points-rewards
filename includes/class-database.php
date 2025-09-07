<?php
/**
 * 資料庫管理類別
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 資料庫管理類別
 */
class WC_Points_Rewards_Database {
    
    /**
     * 單例實例
     */
    private static $instance = null;
    
    /**
     * 獲取單例實例
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 建構函式
     */
    public function __construct() {
        // 初始化 hooks
        add_action('init', array($this, 'init'));
    }
    
    /**
     * 初始化
     */
    public function init() {
        // 這裡可以添加其他初始化邏輯
    }
    
    /**
     * 創建所有必要的資料庫表格
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 會員等級表
        $tiers_table = $wpdb->prefix . 'wc_points_rewards_tiers';
        $tiers_sql = "CREATE TABLE $tiers_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL COMMENT '等級名稱',
            min_amount decimal(10,2) NOT NULL DEFAULT 0 COMMENT '最低消費金額',
            bonus_percentage decimal(5,2) NOT NULL DEFAULT 0 COMMENT '額外回饋百分比',
            tier_order int(11) NOT NULL DEFAULT 0 COMMENT '等級順序',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_min_amount (min_amount),
            KEY idx_tier_order (tier_order)
        ) $charset_collate COMMENT='會員等級表';";
        
        // 點數記錄表
        $points_table = $wpdb->prefix . 'wc_points_rewards_points';
        $points_sql = "CREATE TABLE $points_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL COMMENT '用戶ID',
            order_id bigint(20) unsigned DEFAULT NULL COMMENT '訂單ID',
            points decimal(10,2) NOT NULL COMMENT '點數變化量',
            type varchar(50) NOT NULL COMMENT '點數類型：earned, redeemed, expired, admin',
            description text COMMENT '描述',
            expiry_date datetime DEFAULT NULL COMMENT '過期時間',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_order_id (order_id),
            KEY idx_type (type),
            KEY idx_expiry_date (expiry_date),
            KEY idx_created_at (created_at)
        ) $charset_collate COMMENT='點數記錄表';";
        
        // 會員年度消費統計表
        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $stats_sql = "CREATE TABLE $stats_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL COMMENT '用戶ID',
            year int(4) NOT NULL COMMENT '年份',
            total_spent decimal(10,2) NOT NULL DEFAULT 0 COMMENT '年度總消費',
            current_tier_id bigint(20) unsigned DEFAULT NULL COMMENT '當前等級ID',
            tier_start_date datetime DEFAULT NULL COMMENT '等級開始時間',
            tier_expiry_date datetime DEFAULT NULL COMMENT '等級過期時間',
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_year (user_id, year),
            KEY idx_user_id (user_id),
            KEY idx_year (year),
            KEY idx_current_tier_id (current_tier_id),
            KEY idx_tier_expiry_date (tier_expiry_date)
        ) $charset_collate COMMENT='會員年度消費統計表';";
        
        // 外掛設定表
        $settings_table = $wpdb->prefix . 'wc_points_rewards_settings';
        $settings_sql = "CREATE TABLE $settings_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL COMMENT '設定鍵',
            setting_value longtext COMMENT '設定值',
            autoload varchar(20) NOT NULL DEFAULT 'yes',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_setting_key (setting_key),
            KEY idx_autoload (autoload)
        ) $charset_collate COMMENT='外掛設定表';";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($tiers_sql);
        dbDelta($points_sql);
        dbDelta($stats_sql);
        dbDelta($settings_sql);
        
        // 更新資料庫版本
        update_option('wc_points_rewards_db_version', '1.0.0');
    }
    
    /**
     * 刪除所有資料庫表格
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'wc_points_rewards_tiers',
            $wpdb->prefix . 'wc_points_rewards_points',
            $wpdb->prefix . 'wc_points_rewards_user_stats',
            $wpdb->prefix . 'wc_points_rewards_settings'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('wc_points_rewards_db_version');
    }
    
    /**
     * 獲取用戶總點數 - 改進空值處理和資料型別安全
     */
    public function get_user_points($user_id) {
        global $wpdb;
        
        // 驗證用戶ID
        $user_id = intval($user_id);
        if ($user_id <= 0) {
            return 0.0;
        }
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_points';
        
        $total_points = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points), 0) 
            FROM `{$table_name}` 
            WHERE user_id = %d 
            AND (expiry_date IS NULL OR expiry_date > NOW())
        ", $user_id));
        
        // 確保返回有效的浮點數
        $result = floatval($total_points);
        return max(0, $result); // 確保不會返回負數
    }
    
    /**
     * 添加點數記錄 - 使用事務確保一致性
     */
    public function add_points($user_id, $points, $type, $description = '', $order_id = null, $expiry_date = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_points';
        
        // 驗證輸入參數
        $user_id = intval($user_id);
        $points = floatval($points);
        $type = sanitize_text_field($type);
        $description = sanitize_textarea_field($description);
        
        if ($user_id <= 0) {
            return false;
        }
        
        // 檢查點數範圍（防止極大的值導致問題）
        if (abs($points) > 999999999.99) {
            return false;
        }
        
        // 開始事務
        $wpdb->query('START TRANSACTION');
        
        try {
            $data = array(
                'user_id' => $user_id,
                'points' => $points,
                'type' => $type,
                'description' => $description,
                'created_at' => current_time('mysql')
            );
            
            if ($order_id) {
                $data['order_id'] = intval($order_id);
            }
            
            if ($expiry_date) {
                $data['expiry_date'] = sanitize_text_field($expiry_date);
            }
            
            $result = $wpdb->insert($table_name, $data);
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                return false;
            }
            
            // 提交事務
            $wpdb->query('COMMIT');
            
            // 觸發動作鉤子
            do_action('wc_points_rewards_points_added', $user_id, $points, $type, $description, $order_id);
            return $wpdb->insert_id;
            
        } catch (Exception $e) {
            // 回滾事務
            $wpdb->query('ROLLBACK');
            error_log('WC Points Rewards: Error adding points - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 獲取用戶點數記錄
     */
    public function get_user_points_history($user_id, $limit = 50, $offset = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_points';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT * 
            FROM $table_name 
            WHERE user_id = %d 
            ORDER BY created_at DESC 
            LIMIT %d OFFSET %d
        ", $user_id, $limit, $offset));
        
        return $results;
    }
    
    /**
     * 更新用戶年度消費統計
     */
    public function update_user_yearly_stats($user_id, $amount, $year = null) {
        global $wpdb;
        
        if (!$year) {
            $year = date('Y');
        }
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_user_stats';
        
        // 檢查是否已存在記錄
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE user_id = %d AND year = %d
        ", $user_id, $year));
        
        if ($existing) {
            // 更新現有記錄
            $wpdb->update(
                $table_name,
                array('total_spent' => $existing->total_spent + $amount),
                array('user_id' => $user_id, 'year' => $year)
            );
        } else {
            // 插入新記錄
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'year' => $year,
                    'total_spent' => $amount
                )
            );
        }
        
        // 檢查會員等級升級
        $this->check_tier_upgrade($user_id, $year);
    }
    
    /**
     * 檢查會員等級升級 - 添加鎖定機制防止競態條件，支援最高等級延期
     */
    private function check_tier_upgrade($user_id, $year) {
        global $wpdb;
        
        $user_id = intval($user_id);
        $year = intval($year);
        
        if ($user_id <= 0 || $year < 2000 || $year > 2100) {
            return false;
        }
        
        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $tiers_table = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        // 使用資料庫鎖定防止競態條件
        $lock_name = "wc_points_tier_upgrade_{$user_id}_{$year}";
        $lock_acquired = $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 10)", $lock_name));
        
        if (!$lock_acquired) {
            return false; // 無法獲得鎖定，可能有其他進程正在處理
        }
        
        try {
            // 獲取用戶年度消費總額
            $total_spent = $wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(total_spent, 0) FROM `{$stats_table}` 
                WHERE user_id = %d AND year = %d
            ", $user_id, $year));
            
            $total_spent = floatval($total_spent);
            
            // 獲取用戶當前等級
            $current_stats = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM `{$stats_table}` 
                WHERE user_id = %d AND year = %d
            ", $user_id, $year));
            
            // 獲取最高等級
            $highest_tier = $wpdb->get_row("
                SELECT * FROM `{$tiers_table}` 
                ORDER BY min_amount DESC 
                LIMIT 1
            ");
            
            // 獲取符合的最高等級
            $new_tier = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM `{$tiers_table}` 
                WHERE min_amount <= %f 
                ORDER BY min_amount DESC 
                LIMIT 1
            ", $total_spent));
            
            if ($new_tier) {
                $update_data = array(
                    'current_tier_id' => intval($new_tier->id),
                    'tier_start_date' => current_time('mysql'),
                );
                
                // 檢查是否為最高等級的延期邏輯
                if ($current_stats && $current_stats->current_tier_id && $highest_tier && 
                    $current_stats->current_tier_id == $highest_tier->id && 
                    $new_tier->id == $highest_tier->id && 
                    $total_spent >= $highest_tier->min_amount) {
                    
                    // 如果用戶已經是最高等級且再次達到最高等級消費門檻，延長一年
                    $current_expiry = $current_stats->tier_expiry_date;
                    if ($current_expiry && strtotime($current_expiry) > time()) {
                        // 如果現有到期日還未過，從現有到期日延長一年
                        $update_data['tier_expiry_date'] = date('Y-m-d H:i:s', strtotime($current_expiry . ' +1 year'));
                    } else {
                        // 如果已過期或沒有到期日，從現在開始計算一年
                        $update_data['tier_expiry_date'] = date('Y-m-d H:i:s', strtotime('+1 year'));
                    }
                } else {
                    // 正常等級升級邏輯
                    $update_data['tier_expiry_date'] = date('Y-m-d H:i:s', strtotime('+1 year'));
                }
                
                // 更新用戶等級
                $wpdb->update(
                    $stats_table,
                    $update_data,
                    array('user_id' => $user_id, 'year' => $year),
                    array('%d', '%s', '%s'),
                    array('%d', '%d')
                );
                
                // 觸發等級升級動作
                do_action('wc_points_rewards_tier_upgraded', $user_id, $new_tier);
                return true;
            }
            
        } finally {
            // 釋放鎖定
            $wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
        }
        
        return false;
    }
    
    /**
     * 獲取用戶當前等級
     */
    public function get_user_current_tier($user_id) {
        global $wpdb;
        
        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $tiers_table = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        $current_year = date('Y');
        
        $tier = $wpdb->get_row($wpdb->prepare("
            SELECT t.* 
            FROM $tiers_table t
            INNER JOIN $stats_table s ON t.id = s.current_tier_id
            WHERE s.user_id = %d 
            AND s.year = %d
            AND (s.tier_expiry_date IS NULL OR s.tier_expiry_date > NOW())
        ", $user_id, $current_year));
        
        // 如果沒有等級或等級已過期，返回預設等級
        if (!$tier) {
            // 使用安全的表名和參數
            $safe_query = "SELECT * FROM `{$tiers_table}` WHERE tier_order = %d LIMIT 1";
            $tier = $wpdb->get_row($wpdb->prepare($safe_query, 1));
        }
        
        return $tier;
    }
    
    /**
     * 清理過期點數
     */
    public function cleanup_expired_points() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_points';
        
        // 標記過期點數 - 使用準備好的語句
        $expired_query = "
            UPDATE `{$table_name}` 
            SET type = %s 
            WHERE expiry_date IS NOT NULL 
            AND expiry_date <= NOW() 
            AND type = %s
        ";
        
        $wpdb->query($wpdb->prepare($expired_query, 'expired', 'earned'));
        
        // 觸發清理動作
        do_action('wc_points_rewards_points_expired');
    }
}