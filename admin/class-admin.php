<?php
/**
 * 管理後台主類別
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 管理後台主類別
 */
class WC_Points_Rewards_Admin {
    
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
        // 添加管理選單
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // 添加 WooCommerce 設定頁籤
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_points_rewards', array($this, 'settings_tab_content'));
        add_action('woocommerce_update_options_points_rewards', array($this, 'update_settings'));
        
        // 載入管理後台腳本和樣式
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // 用戶列表添加點數欄位
        add_filter('manage_users_columns', array($this, 'add_user_columns'));
        add_action('manage_users_custom_column', array($this, 'add_user_column_content'), 10, 3);
        
        // 用戶編輯頁面添加點數管理
        add_action('show_user_profile', array($this, 'add_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_user_profile_fields'));
        add_action('personal_options_update', array($this, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_profile_fields'));
        
        // 訂單列表添加點數資訊
        add_action('manage_shop_order_posts_custom_column', array($this, 'add_order_column_content'), 10, 2);
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_columns'));
        
        // 訂單詳情頁面添加點數資訊
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_order_points_info'));
        
        // 處理表單提交
        add_action('admin_post_wc_points_rewards_save_tier', array($this, 'handle_save_tier'));
    }
    
    /**
     * 添加管理選單
     */
    public function add_admin_menu() {
        add_menu_page(
            __('點數獎勵', 'wc-points-rewards'),
            __('點數獎勵', 'wc-points-rewards'),
            'manage_woocommerce',
            'wc-points-rewards',
            array($this, 'admin_dashboard_page'),
            'dashicons-awards',
            56
        );
        
        add_submenu_page(
            'wc-points-rewards',
            __('儀表板', 'wc-points-rewards'),
            __('儀表板', 'wc-points-rewards'),
            'manage_woocommerce',
            'wc-points-rewards',
            array($this, 'admin_dashboard_page')
        );
        
        add_submenu_page(
            'wc-points-rewards',
            __('會員等級', 'wc-points-rewards'),
            __('會員等級', 'wc-points-rewards'),
            'manage_woocommerce',
            'wc-points-rewards-tiers',
            array($this, 'admin_tiers_page')
        );
        
        add_submenu_page(
            'wc-points-rewards',
            __('點數記錄', 'wc-points-rewards'),
            __('點數記錄', 'wc-points-rewards'),
            'manage_woocommerce',
            'wc-points-rewards-points',
            array($this, 'admin_points_page')
        );
        
        add_submenu_page(
            'wc-points-rewards',
            __('歷史訂單處理', 'wc-points-rewards'),
            __('歷史訂單處理', 'wc-points-rewards'),
            'manage_woocommerce',
            'wc-points-rewards-historical',
            array($this, 'admin_historical_page')
        );
        
        add_submenu_page(
            'wc-points-rewards',
            __('報表', 'wc-points-rewards'),
            __('報表', 'wc-points-rewards'),
            'manage_woocommerce',
            'wc-points-rewards-reports',
            array($this, 'admin_reports_page')
        );
        
        add_submenu_page(
            'wc-points-rewards',
            __('設定', 'wc-points-rewards'),
            __('設定', 'wc-points-rewards'),
            'manage_woocommerce',
            'wc-points-rewards-settings',
            array($this, 'admin_settings_page')
        );
    }
    
    /**
     * 載入管理後台腳本和樣式 - 已移除非功能性前端資源
     */
    public function enqueue_admin_scripts($hook) {
        // 只在相關頁面載入
        if (strpos($hook, 'wc-points-rewards') === false && $hook !== 'user-edit.php' && $hook !== 'profile.php') {
            return;
        }
        
        wp_enqueue_script('jquery-ui-sortable');
        
        // 前端 CSS/JS 資源已移除，只保留必要的本地化腳本
        wp_localize_script('jquery', 'wcPointsRewardsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_points_rewards_admin_nonce'),
            'confirmDelete' => __('確定要刪除嗎？此操作無法復原。', 'wc-points-rewards'),
            'processing' => __('處理中...', 'wc-points-rewards'),
            'error' => __('發生錯誤，請稍後再試。', 'wc-points-rewards')
        ));
    }
    
    /**
     * 儀表板頁面
     */
    public function admin_dashboard_page() {
    global $wpdb;
    
    // 獲取統計數據，安全處理 null 值
    $total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
    $total_users = intval($total_users ?? 0);
    
    $points_table = $wpdb->prefix . 'wc_points_rewards_points';
    $total_points_issued = $wpdb->get_var("SELECT SUM(points) FROM $points_table WHERE type = 'earned'");
    $total_points_issued = floatval($total_points_issued ?? 0);
    
    // 修正第 185 行 - 先處理 null 值再使用 abs()
    $total_points_redeemed_raw = $wpdb->get_var("SELECT SUM(points) FROM $points_table WHERE type = 'redeemed'");
    $total_points_redeemed = abs(floatval($total_points_redeemed_raw ?? 0));
    
    $tiers_table = $wpdb->prefix . 'wc_points_rewards_tiers';
    $total_tiers = $wpdb->get_var("SELECT COUNT(*) FROM $tiers_table");
    $total_tiers = intval($total_tiers ?? 0);
    
    // 其餘代碼保持不變...
    $recent_activities = $wpdb->get_results("
        SELECT p.*, u.display_name 
        FROM $points_table p 
        LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID 
        ORDER BY p.created_at DESC 
        LIMIT 10
    ");
    
    if (!$recent_activities) {
        $recent_activities = array();
    }
    
    if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/dashboard.php')) {
        include WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/dashboard.php';
    } else {
        $this->show_fallback_page(__('儀表板', 'wc-points-rewards'), __('儀表板頁面開發中...', 'wc-points-rewards'));
    }
}
    
    /**
     * 會員等級管理頁面
     */
    public function admin_tiers_page() {
        $action = $_GET['action'] ?? 'list';
        $tier_id = $_GET['tier_id'] ?? 0;
        
        $tier_manager = WC_Points_Rewards_Member_Tier::instance();
        
        if ($action === 'edit' && $tier_id) {
            $tier = $tier_manager->get_tier($tier_id);
            if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/tier-edit.php')) {
                include WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/tier-edit.php';
            } else {
                $this->show_fallback_page(__('編輯等級', 'wc-points-rewards'), __('編輯頁面開發中...', 'wc-points-rewards'));
            }
        } elseif ($action === 'add') {
            $tier = null;
            if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/tier-edit.php')) {
                include WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/tier-edit.php';
            } else {
                $this->show_fallback_page(__('新增等級', 'wc-points-rewards'), __('新增頁面開發中...', 'wc-points-rewards'));
            }
        } else {
            $tiers = $tier_manager->get_all_tiers();
            if (!$tiers) {
                $tiers = array();
            }
            
            if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/tiers-list.php')) {
                include WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/tiers-list.php';
            } else {
                $this->show_fallback_page(__('會員等級', 'wc-points-rewards'), __('列表頁面開發中...', 'wc-points-rewards'));
            }
        }
    }
    
    /**
     * 點數記錄頁面
     */
    public function admin_points_page() {
        global $wpdb;
        
        $points_table = $wpdb->prefix . 'wc_points_rewards_points';
        
        // 處理搜尋和篩選
        $search = sanitize_text_field($_GET['search'] ?? '');
        $type_filter = sanitize_text_field($_GET['type'] ?? '');
        $user_filter = intval($_GET['user'] ?? 0);
        
        $where_clauses = array();
        $params = array();
        
        if ($search) {
            $where_clauses[] = "(u.display_name LIKE %s OR u.user_email LIKE %s OR p.description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        if ($type_filter) {
            $where_clauses[] = "p.type = %s";
            $params[] = $type_filter;
        }
        
        if ($user_filter) {
            $where_clauses[] = "p.user_id = %d";
            $params[] = $user_filter;
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // 分頁
        $per_page = 20;
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        $offset = ($current_page - 1) * $per_page;
        
        // 獲取總數
        $total_query = "
            SELECT COUNT(*) 
            FROM $points_table p 
            LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID 
            $where_sql
        ";
        
        if (!empty($params)) {
            $total_items = $wpdb->get_var($wpdb->prepare($total_query, $params));
        } else {
            $total_items = $wpdb->get_var($total_query);
        }
        $total_items = intval($total_items ?? 0);
        
        // 獲取記錄
        $records_query = "
            SELECT p.*, u.display_name, u.user_email 
            FROM $points_table p 
            LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID 
            $where_sql 
            ORDER BY p.created_at DESC 
            LIMIT %d OFFSET %d
        ";
        
        $query_params = array_merge($params, array($per_page, $offset));
        $records = $wpdb->get_results($wpdb->prepare($records_query, $query_params));
        
        if (!$records) {
            $records = array();
        }
        
        $total_pages = ceil($total_items / $per_page);
        
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/points-list.php')) {
            include WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/points-list.php';
        } else {
            $this->show_fallback_page(__('點數記錄', 'wc-points-rewards'), __('點數記錄頁面開發中...', 'wc-points-rewards'));
        }
    }
    
    /**
     * 歷史訂單處理頁面
     */
    public function admin_historical_page() {
        // 安全檢查
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('您沒有權限訪問此頁面', 'wc-points-rewards'));
        }
        
        include WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/historical-orders.php';
    }
    
    /**
     * 報表頁面
     */
    public function admin_reports_page() {
        if (class_exists('WC_Points_Rewards_Reports')) {
            $reports = WC_Points_Rewards_Reports::instance();
        }
        
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/reports.php')) {
            include WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/reports.php';
        } else {
            $this->show_fallback_page(__('報表', 'wc-points-rewards'), __('報表頁面開發中...', 'wc-points-rewards'));
        }
    }
    
    /**
     * 設定頁面
     */
    public function admin_settings_page() {
        if (class_exists('WC_Points_Rewards_Settings')) {
            $settings = WC_Points_Rewards_Settings::instance();
            $settings->output();
        } else {
            if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/settings.php')) {
                include WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/settings.php';
            } else {
                $this->show_fallback_page(__('設定', 'wc-points-rewards'), __('設定頁面開發中...', 'wc-points-rewards'));
            }
        }
    }
    
    /**
     * 顯示後備頁面
     */
    private function show_fallback_page($title, $message) {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<div class="notice notice-info"><p>' . esc_html($message) . '</p></div>';
        echo '</div>';
    }
    
    /**
     * 處理會員等級儲存
     */
    public function handle_save_tier() {
        // 檢查權限
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('權限不足', 'wc-points-rewards'));
        }
        
        // 檢查 nonce
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'wc_points_rewards_save_tier')) {
            wp_die(__('安全驗證失敗', 'wc-points-rewards'));
        }
        
        $tier_data = array(
            'id' => intval($_POST['tier_id'] ?? 0),
            'name' => sanitize_text_field($_POST['tier_name'] ?? ''),
            'min_amount' => floatval($_POST['min_amount'] ?? 0),
            'bonus_percentage' => floatval($_POST['bonus_percentage'] ?? 0),
            'tier_order' => intval($_POST['tier_order'] ?? 1)
        );
        
        if (empty($tier_data['name'])) {
            wp_redirect(add_query_arg(array(
                'page' => 'wc-points-rewards-tiers',
                'error' => 'name_required'
            ), admin_url('admin.php')));
            exit;
        }
        
        $tier_manager = WC_Points_Rewards_Member_Tier::instance();
        $result = $tier_manager->save_tier($tier_data);
        
        if ($result) {
            wp_redirect(add_query_arg(array(
                'page' => 'wc-points-rewards-tiers',
                'message' => 'tier_saved'
            ), admin_url('admin.php')));
        } else {
            wp_redirect(add_query_arg(array(
                'page' => 'wc-points-rewards-tiers',
                'error' => 'save_failed'
            ), admin_url('admin.php')));
        }
        exit;
    }
    
    /**
     * 添加 WooCommerce 設定頁籤
     */
    public function add_settings_tab($settings_tabs) {
        $settings_tabs['points_rewards'] = __('點數獎勵', 'wc-points-rewards');
        return $settings_tabs;
    }
    
    /**
     * 設定頁籤內容
     */
    public function settings_tab_content() {
        woocommerce_admin_fields($this->get_settings());
    }
    
    /**
     * 更新設定
     */
    public function update_settings() {
        woocommerce_update_options($this->get_settings());
    }
    
    /**
     * 獲取設定欄位
     */
    private function get_settings() {
        return array(
            array(
                'name' => __('點數獎勵設定', 'wc-points-rewards'),
                'type' => 'title',
                'desc' => __('配置點數獎勵系統的基本設定', 'wc-points-rewards'),
                'id'   => 'wc_points_rewards_title'
            ),
            array(
                'name' => __('啟用點數系統', 'wc-points-rewards'),
                'type' => 'checkbox',
                'desc' => __('啟用點數獎勵功能', 'wc-points-rewards'),
                'id'   => 'wc_points_rewards_enabled'
            ),
            array(
                'name' => __('點數回饋比例', 'wc-points-rewards'),
                'type' => 'text',
                'desc' => __('每多少金額回饋1點（例如：100 表示每100元回饋1點）', 'wc-points-rewards'),
                'id'   => 'wc_points_rewards_ratio',
                'default' => '100'
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'wc_points_rewards_end'
            )
        );
    }
    
    /**
     * 添加用戶列表欄位
     */
    public function add_user_columns($columns) {
        $columns['points_balance'] = __('點數餘額', 'wc-points-rewards');
        $columns['member_tier'] = __('會員等級', 'wc-points-rewards');
        return $columns;
    }
    
    /**
     * 用戶列表欄位內容
     */
    public function add_user_column_content($value, $column_name, $user_id) {
        if ($column_name === 'points_balance') {
            if (class_exists('WC_Points_Rewards_Database')) {
                $database = WC_Points_Rewards_Database::instance();
                $points = $database->get_user_points($user_id);
                return wc_points_rewards_number_format(floatval($points ?? 0));
            }
            return '0.00';
        }
        
        if ($column_name === 'member_tier') {
            if (class_exists('WC_Points_Rewards_Database')) {
                $database = WC_Points_Rewards_Database::instance();
                $tier = $database->get_user_current_tier($user_id);
                return $tier ? esc_html($tier->name) : __('一般會員', 'wc-points-rewards');
            }
            return __('一般會員', 'wc-points-rewards');
        }
        
        return $value;
    }
    
    /**
     * 用戶編輯頁面添加點數管理欄位
     */
    public function add_user_profile_fields($user) {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        if (!class_exists('WC_Points_Rewards_Database') || !class_exists('WC_Points_Rewards_Member_Tier')) {
            return;
        }
        
        $database = WC_Points_Rewards_Database::instance();
        $tier_manager = WC_Points_Rewards_Member_Tier::instance();
        
        $user_points = $database->get_user_points($user->ID);
        $current_tier = $database->get_user_current_tier($user->ID);
        $all_tiers = $tier_manager->get_all_tiers();
        $recent_history = $database->get_user_points_history($user->ID, 10);
        
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/user-profile-fields.php')) {
            include WC_POINTS_REWARDS_PLUGIN_DIR . 'admin/views/user-profile-fields.php';
        }
    }
    
    /**
     * 儲存用戶資料欄位
     */
    public function save_user_profile_fields($user_id) {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        // 這裡可以處理手動設定會員等級等功能
        if (isset($_POST['wc_points_rewards_tier']) && $_POST['wc_points_rewards_tier'] && class_exists('WC_Points_Rewards_Member_Tier')) {
            $tier_manager = WC_Points_Rewards_Member_Tier::instance();
            $tier_manager->set_user_tier($user_id, intval($_POST['wc_points_rewards_tier']));
        }
    }
    
    /**
     * 添加訂單列表欄位
     */
    public function add_order_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'order_total') {
                $new_columns['points_earned'] = __('獲得點數', 'wc-points-rewards');
                $new_columns['points_used'] = __('使用點數', 'wc-points-rewards');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * 訂單列表欄位內容
     */
    public function add_order_column_content($column, $post_id) {
        if ($column === 'points_earned') {
            $points_awarded = get_post_meta($post_id, '_points_awarded', true);
            echo $points_awarded ? wc_points_rewards_number_format(floatval($points_awarded)) : '-';
        }
        
        if ($column === 'points_used') {
            $points_discount = get_post_meta($post_id, '_points_discount_amount', true);
            echo $points_discount ? wc_points_rewards_number_format(floatval($points_discount)) : '-';
        }
    }
    
    /**
     * 訂單詳情頁面顯示點數資訊
     */
    public function display_order_points_info($order) {
        $points_awarded = get_post_meta($order->get_id(), '_points_awarded', true);
        $points_discount = get_post_meta($order->get_id(), '_points_discount_amount', true);
        
        if ($points_awarded || $points_discount) {
            echo '<div class="order_data_column">';
            echo '<h3>' . __('點數資訊', 'wc-points-rewards') . '</h3>';
            
            if ($points_awarded) {
                echo '<p><strong>' . __('獲得點數:', 'wc-points-rewards') . '</strong> ' . wc_points_rewards_number_format(floatval($points_awarded)) . '</p>';
            }
            
            if ($points_discount) {
                echo '<p><strong>' . __('使用點數:', 'wc-points-rewards') . '</strong> ' . wc_points_rewards_number_format(floatval($points_discount)) . '</p>';
            }
            
            echo '</div>';
        }
    }
}