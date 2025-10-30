<?php
/**
 * 資料管理類別 - 處理匯出/匯入功能
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 資料管理類別
 */
class WC_Points_Rewards_Data_Manager {
    
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
        $this->init_hooks();
    }
    
    /**
     * 初始化 hooks
     */
    private function init_hooks() {
        // 處理匯出請求
        add_action('admin_post_wc_points_rewards_export_data', array($this, 'handle_export'));
        
        // 處理匯入請求
        add_action('admin_post_wc_points_rewards_import_data', array($this, 'handle_import'));
    }
    
    /**
     * 匯出所有記錄
     */
    public function export_all_records() {
        global $wpdb;
        
        // 檢查權限
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('您沒有權限執行此操作', 'wc-points-rewards'));
        }
        
        $export_data = array(
            'version' => WC_POINTS_REWARDS_VERSION,
            'export_date' => current_time('mysql'),
            'points_records' => $this->export_points_records(),
            'tiers' => $this->export_tiers(),
            'user_stats' => $this->export_user_stats(),
            'settings' => $this->export_settings()
        );
        
        return $export_data;
    }
    
    /**
     * 匯出點數記錄
     */
    private function export_points_records() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wc_points_rewards_points';
        
        $records = $wpdb->get_results("
            SELECT 
                p.id,
                p.user_id,
                u.user_login,
                u.user_email,
                p.order_id,
                p.points,
                p.type,
                p.description,
                p.expiry_date,
                p.created_at
            FROM {$table} p
            LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
            ORDER BY p.id ASC
        ", ARRAY_A);
        
        return $records;
    }
    
    /**
     * 匯出會員等級
     */
    private function export_tiers() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        $tiers = $wpdb->get_results("
            SELECT *
            FROM {$table}
            ORDER BY tier_order ASC
        ", ARRAY_A);
        
        return $tiers;
    }
    
    /**
     * 匯出用戶統計
     */
    private function export_user_stats() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        
        $stats = $wpdb->get_results("
            SELECT 
                s.id,
                s.user_id,
                u.user_login,
                u.user_email,
                s.year,
                s.total_spent,
                s.current_tier_id,
                s.tier_start_date,
                s.tier_expiry_date,
                s.updated_at
            FROM {$table} s
            LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
            ORDER BY s.id ASC
        ", ARRAY_A);
        
        return $stats;
    }
    
    /**
     * 匯出設定
     */
    private function export_settings() {
        $settings = array(
            'main_settings' => get_option('wc_points_rewards_settings', array()),
            'enable_notifications' => get_option('wc_points_rewards_enable_notifications', 'yes'),
            'expiry_notification_days' => get_option('wc_points_rewards_expiry_notification_days', '7'),
            'enable_birthday_notification' => get_option('wc_points_rewards_enable_birthday_notification', 'yes'),
            'db_version' => get_option('wc_points_rewards_db_version', '1.0.0')
        );
        
        return $settings;
    }
    
    /**
     * 處理匯出請求
     */
    public function handle_export() {
        // 檢查權限
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('您沒有權限執行此操作', 'wc-points-rewards'));
        }
        
        // 驗證 nonce
        if (!isset($_POST['export_nonce']) || !wp_verify_nonce($_POST['export_nonce'], 'wc_points_rewards_export_data')) {
            wp_die(__('安全驗證失敗', 'wc-points-rewards'));
        }
        
        // 獲取匯出類型
        $export_type = isset($_POST['export_type']) ? sanitize_text_field($_POST['export_type']) : 'all';
        
        // 記錄安全事件
        if (class_exists('WC_Points_Rewards_Security')) {
            $security = WC_Points_Rewards_Security::instance();
            $security->log_security_event('data_export', "管理員匯出 {$export_type} 資料", get_current_user_id());
        }
        
        // 準備匯出資料
        $export_data = array();
        
        switch ($export_type) {
            case 'points':
                $export_data = array(
                    'version' => WC_POINTS_REWARDS_VERSION,
                    'export_date' => current_time('mysql'),
                    'type' => 'points',
                    'points_records' => $this->export_points_records()
                );
                break;
                
            case 'tiers':
                $export_data = array(
                    'version' => WC_POINTS_REWARDS_VERSION,
                    'export_date' => current_time('mysql'),
                    'type' => 'tiers',
                    'tiers' => $this->export_tiers()
                );
                break;
                
            case 'user_stats':
                $export_data = array(
                    'version' => WC_POINTS_REWARDS_VERSION,
                    'export_date' => current_time('mysql'),
                    'type' => 'user_stats',
                    'user_stats' => $this->export_user_stats()
                );
                break;
                
            case 'settings':
                $export_data = array(
                    'version' => WC_POINTS_REWARDS_VERSION,
                    'export_date' => current_time('mysql'),
                    'type' => 'settings',
                    'settings' => $this->export_settings()
                );
                break;
                
            default: // all
                $export_data = $this->export_all_records();
                $export_data['type'] = 'all';
                break;
        }
        
        // 設定檔案名稱
        $filename = 'wc-points-rewards-' . $export_type . '-' . date('Y-m-d-His') . '.json';
        
        // 輸出 JSON 檔案
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 處理匯入請求
     */
    public function handle_import() {
        // 檢查權限
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('您沒有權限執行此操作', 'wc-points-rewards'));
        }
        
        // 驗證 nonce
        if (!isset($_POST['import_nonce']) || !wp_verify_nonce($_POST['import_nonce'], 'wc_points_rewards_import_data')) {
            wp_die(__('安全驗證失敗', 'wc-points-rewards'));
        }
        
        // 檢查檔案上傳
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(add_query_arg(array(
                'page' => 'wc-points-rewards-data',
                'import' => 'error',
                'message' => urlencode(__('檔案上傳失敗', 'wc-points-rewards'))
            ), admin_url('admin.php')));
            exit;
        }
        
        // 讀取檔案內容
        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($file_content, true);
        
        // 驗證 JSON 格式
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_redirect(add_query_arg(array(
                'page' => 'wc-points-rewards-data',
                'import' => 'error',
                'message' => urlencode(__('無效的 JSON 格式', 'wc-points-rewards'))
            ), admin_url('admin.php')));
            exit;
        }
        
        // 驗證資料結構
        if (!isset($import_data['version']) || !isset($import_data['export_date'])) {
            wp_redirect(add_query_arg(array(
                'page' => 'wc-points-rewards-data',
                'import' => 'error',
                'message' => urlencode(__('無效的匯入檔案格式', 'wc-points-rewards'))
            ), admin_url('admin.php')));
            exit;
        }
        
        // 記錄安全事件
        if (class_exists('WC_Points_Rewards_Security')) {
            $security = WC_Points_Rewards_Security::instance();
            $security->log_security_event('data_import', "管理員匯入資料", get_current_user_id());
        }
        
        // 獲取匯入模式
        $import_mode = isset($_POST['import_mode']) ? sanitize_text_field($_POST['import_mode']) : 'add';
        
        // 執行匯入
        $result = $this->import_data($import_data, $import_mode);
        
        if ($result['success']) {
            wp_redirect(add_query_arg(array(
                'page' => 'wc-points-rewards-data',
                'import' => 'success',
                'imported' => $result['imported']
            ), admin_url('admin.php')));
        } else {
            wp_redirect(add_query_arg(array(
                'page' => 'wc-points-rewards-data',
                'import' => 'error',
                'message' => urlencode($result['message'])
            ), admin_url('admin.php')));
        }
        exit;
    }
    
    /**
     * 匯入資料
     */
    private function import_data($data, $mode = 'add') {
        global $wpdb;
        
        $imported_count = 0;
        $errors = array();
        
        try {
            // 開始交易
            $wpdb->query('START TRANSACTION');
            
            // 匯入會員等級
            if (isset($data['tiers']) && is_array($data['tiers'])) {
                foreach ($data['tiers'] as $tier) {
                    if ($mode === 'replace') {
                        // 替換模式：先刪除現有資料
                        if (isset($tier['id'])) {
                            $wpdb->delete(
                                $wpdb->prefix . 'wc_points_rewards_tiers',
                                array('id' => $tier['id']),
                                array('%d')
                            );
                        }
                    }
                    
                    // 移除不需要的欄位
                    unset($tier['created_at']);
                    unset($tier['updated_at']);
                    if ($mode === 'add') {
                        unset($tier['id']); // 新增模式不使用舊 ID
                    }
                    
                    $wpdb->insert(
                        $wpdb->prefix . 'wc_points_rewards_tiers',
                        $tier
                    );
                    $imported_count++;
                }
            }
            
            // 匯入點數記錄
            if (isset($data['points_records']) && is_array($data['points_records'])) {
                foreach ($data['points_records'] as $record) {
                    // 透過 user_login 或 user_email 查找用戶
                    $user = null;
                    if (isset($record['user_login'])) {
                        $user = get_user_by('login', $record['user_login']);
                    }
                    if (!$user && isset($record['user_email'])) {
                        $user = get_user_by('email', $record['user_email']);
                    }
                    
                    if (!$user) {
                        $errors[] = sprintf(__('找不到用戶: %s', 'wc-points-rewards'), 
                            $record['user_login'] ?? $record['user_email'] ?? 'unknown');
                        continue;
                    }
                    
                    // 更新 user_id
                    $record['user_id'] = $user->ID;
                    
                    // 移除不需要的欄位
                    unset($record['user_login']);
                    unset($record['user_email']);
                    unset($record['created_at']);
                    if ($mode === 'add') {
                        unset($record['id']); // 新增模式不使用舊 ID
                    }
                    
                    if ($mode === 'replace' && isset($record['id'])) {
                        $wpdb->delete(
                            $wpdb->prefix . 'wc_points_rewards_points',
                            array('id' => $record['id']),
                            array('%d')
                        );
                    }
                    
                    $wpdb->insert(
                        $wpdb->prefix . 'wc_points_rewards_points',
                        $record
                    );
                    $imported_count++;
                }
            }
            
            // 匯入用戶統計
            if (isset($data['user_stats']) && is_array($data['user_stats'])) {
                foreach ($data['user_stats'] as $stat) {
                    // 透過 user_login 或 user_email 查找用戶
                    $user = null;
                    if (isset($stat['user_login'])) {
                        $user = get_user_by('login', $stat['user_login']);
                    }
                    if (!$user && isset($stat['user_email'])) {
                        $user = get_user_by('email', $stat['user_email']);
                    }
                    
                    if (!$user) {
                        continue;
                    }
                    
                    // 更新 user_id
                    $stat['user_id'] = $user->ID;
                    
                    // 移除不需要的欄位
                    unset($stat['user_login']);
                    unset($stat['user_email']);
                    unset($stat['updated_at']);
                    if ($mode === 'add') {
                        unset($stat['id']); // 新增模式不使用舊 ID
                    }
                    
                    if ($mode === 'replace' && isset($stat['id'])) {
                        $wpdb->delete(
                            $wpdb->prefix . 'wc_points_rewards_user_stats',
                            array('id' => $stat['id']),
                            array('%d')
                        );
                    }
                    
                    $wpdb->insert(
                        $wpdb->prefix . 'wc_points_rewards_user_stats',
                        $stat
                    );
                    $imported_count++;
                }
            }
            
            // 匯入設定
            if (isset($data['settings']) && is_array($data['settings'])) {
                foreach ($data['settings'] as $key => $value) {
                    update_option('wc_points_rewards_' . $key, $value);
                }
                $imported_count++;
            }
            
            // 提交交易
            $wpdb->query('COMMIT');
            
            return array(
                'success' => true,
                'imported' => $imported_count,
                'errors' => $errors
            );
            
        } catch (Exception $e) {
            // 回滾交易
            $wpdb->query('ROLLBACK');
            
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $errors
            );
        }
    }
}
