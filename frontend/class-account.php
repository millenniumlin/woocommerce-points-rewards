<?php
/**
 * 我的帳戶頁面類別 - 修正永久連結兼容性
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 我的帳戶頁面類別
 */
class WC_Points_Rewards_Account {
    
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
        // 添加我的帳戶選單項目
        add_filter('woocommerce_account_menu_items', array($this, 'add_account_menu_items'));
        
        // 🚀 新增：過濾整個導航選單的 HTML 以修正 URL
        add_filter('woocommerce_account_navigation', array($this, 'fix_account_navigation_urls'));
        
        // 🚀 新增：使用輸出緩衝修正導航 URL
        add_action('woocommerce_account_navigation', array($this, 'start_navigation_buffer'), 1);
        add_action('woocommerce_after_account_navigation', array($this, 'end_navigation_buffer'), 999);
        
        // 🚀 關鍵修正：更早註冊端點，並使用正確的優先級
        add_action('init', array($this, 'add_endpoints'), 0);
        add_action('init', array($this, 'add_custom_rewrite_rules'), 5);
        
        // 🚀 關鍵修正：使用正確的 WooCommerce hook
        add_action('woocommerce_account_points-rewards_endpoint', array($this, 'points_rewards_content'));
        add_action('woocommerce_account_points-history_endpoint', array($this, 'points_history_content'));
        add_action('woocommerce_account_member-tier_endpoint', array($this, 'member_tier_content'));
        
        // 在帳戶儀表板顯示點數資訊
        add_action('woocommerce_account_dashboard', array($this, 'display_dashboard_points_info'));
        
        // 查詢變數
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // 設定頁面標題
        add_filter('the_title', array($this, 'endpoint_title'), 10, 2);
        
        // 修正 D-4: 編輯帳戶頁面新增欄位
        add_action('woocommerce_edit_account_form', array($this, 'add_edit_account_fields'));
        add_action('woocommerce_save_account_details', array($this, 'save_edit_account_fields'));
        
        // 🚀 關鍵修正：更改 hook 時機
        add_action('wp_loaded', array($this, 'maybe_flush_rewrite_rules'));
        
        // 🚀 關鍵修正：處理端點請求
        add_action('parse_request', array($this, 'parse_endpoint_request'), 5);
        
        // 🚀 關鍵修正：確保 WooCommerce 整合
        add_action('woocommerce_init', array($this, 'setup_woocommerce_integration'));
        
        // 🚀 新增：修正導航選單 URL 格式
        add_filter('wc_get_endpoint_url', array($this, 'filter_navigation_endpoint_urls'), 10, 4);
        add_action('wp_enqueue_scripts', array($this, 'fix_navigation_urls_with_js'), 20);
        
        // 🚀 新增：直接過濾 WooCommerce 端點 URL
        add_filter('woocommerce_get_endpoint_url', array($this, 'filter_wc_endpoint_urls'), 10, 4);
        
        // 🚀 新增：嘗試過濾 wc_get_endpoint_url（如果存在）
        if (function_exists('wc_get_endpoint_url')) {
            add_filter('wc_get_endpoint_url', array($this, 'filter_navigation_endpoint_urls'), 10, 4);
        }
        
        // 🚀 新增：在輸出前修正連結
        add_action('wp_footer', array($this, 'fix_navigation_urls_on_footer'), 5);
    }
    
    /**
     * 🚀 關鍵修正：設定 WooCommerce 整合
     */
    public function setup_woocommerce_integration() {
        // 確保端點被註冊到 WooCommerce
        if (class_exists('WC_Query') && WC()->query) {
            WC()->query->query_vars['points-rewards'] = 'points-rewards';
            WC()->query->query_vars['points-history'] = 'points-history';
            WC()->query->query_vars['member-tier'] = 'member-tier';
        }
        
        // 🚀 新增：確保端點被加入到 WooCommerce 端點列表
        add_filter('woocommerce_get_query_vars', array($this, 'add_wc_query_vars'));
    }
    
    /**
     * 🚀 新增：添加查詢變數到 WooCommerce
     */
    public function add_wc_query_vars($vars) {
        $vars['points-rewards'] = 'points-rewards';
        $vars['points-history'] = 'points-history';
        $vars['member-tier'] = 'member-tier';
        return $vars;
    }
    
    /**
     * 🚀 關鍵修正：解析端點請求（支援查詢參數格式）
     */
    public function parse_endpoint_request($wp) {
        // 檢查是否為我們的端點
        if (isset($wp->query_vars['pagename']) || isset($wp->query_vars['page_id'])) {
            $account_page_id = wc_get_page_id('myaccount');
            
            // 如果是我的帳戶頁面
            if ((isset($wp->query_vars['page_id']) && $wp->query_vars['page_id'] == $account_page_id) ||
                (isset($wp->query_vars['pagename']) && $wp->query_vars['pagename'] === get_post_field('post_name', $account_page_id))) {
                
                // 檢查 URL 路徑和查詢參數
                $request_uri = $_SERVER['REQUEST_URI'];
                $endpoints = array('points-rewards', 'points-history', 'member-tier');
                
                foreach ($endpoints as $endpoint) {
                    // 檢查路徑段格式: /endpoint/ 或 /endpoint
                    if (strpos($request_uri, '/' . $endpoint . '/') !== false || strpos($request_uri, '/' . $endpoint) !== false) {
                        $wp->query_vars[$endpoint] = '1';
                        break;
                    }
                    // 檢查查詢參數格式: ?endpoint 或 &endpoint
                    if (strpos($request_uri, '?' . $endpoint) !== false || strpos($request_uri, '&' . $endpoint) !== false) {
                        $wp->query_vars[$endpoint] = '1';
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * 🚀 修正：產生帳戶端點 URL 的輔助函數（確保所有永久連結結構都正常工作）
     */
    public static function get_account_endpoint_url($endpoint) {
        $account_page_id = wc_get_page_id('myaccount');
        $account_page_url = get_permalink($account_page_id);
        
        if (!$account_page_url) {
            return home_url('/my-account/?' . $endpoint);
        }
        
        $permalink_structure = get_option('permalink_structure');
        
        if (empty($permalink_structure)) {
            // 預設永久連結結構 - 使用頁面ID和查詢參數
            return add_query_arg($endpoint, '', $account_page_url);
        } else {
            // 美化永久連結結構 - 使用查詢參數而非路徑段
            return trailingslashit($account_page_url) . '?' . $endpoint;
        }
    }
    
    /**
     * 添加我的帳戶選單項目
     */
    public function add_account_menu_items($items) {
        // 檢查是否啟用點數系統
        if (!wc_points_rewards_is_enabled()) {
            return $items;
        }
        
        // 在訂單後面插入點數相關選單
        $new_items = array();
        
        foreach ($items as $key => $item) {
            $new_items[$key] = $item;
            
            if ($key === 'orders') {
                $new_items['points-rewards'] = __('我的點數', 'wc-points-rewards');
                $new_items['points-history'] = __('點數記錄', 'wc-points-rewards');
                $new_items['member-tier'] = __('會員等級', 'wc-points-rewards');
            }
        }
        
        return $new_items;
    }
    
    /**
     * 添加端點
     */
    public function add_endpoints() {
        // 🚀 關鍵修正：確保端點被正確註冊
        add_rewrite_endpoint('points-rewards', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('points-history', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('member-tier', EP_ROOT | EP_PAGES);
        
        // 設定重新整理標記
        if (!get_option('wc_points_rewards_endpoints_flushed')) {
            update_option('wc_points_rewards_endpoints_flushed', 'yes');
            update_option('wc_points_rewards_flush_rewrite_rules', 'yes');
        }
    }
    
    /**
     * 🚀 關鍵修正：添加自訂重寫規則 - 支援所有永久連結結構
     */
    public function add_custom_rewrite_rules() {
        $account_page_id = wc_get_page_id('myaccount');
        
        if ($account_page_id > 0) {
            $account_page = get_post($account_page_id);
            if ($account_page) {
                $account_slug = $account_page->post_name;
                
                // 🚀 修正：支援所有永久連結結構
                $permalink_structure = get_option('permalink_structure');
                
                if (empty($permalink_structure)) {
                    // 預設永久連結結構 (?p=123)
                    add_rewrite_rule(
                        '^index\.php\?page_id=' . $account_page_id . '&points-rewards=1$',
                        'index.php?page_id=' . $account_page_id . '&points-rewards=1',
                        'top'
                    );
                    
                    add_rewrite_rule(
                        '^index\.php\?page_id=' . $account_page_id . '&points-history=1$',
                        'index.php?page_id=' . $account_page_id . '&points-history=1',
                        'top'
                    );
                    
                    add_rewrite_rule(
                        '^index\.php\?page_id=' . $account_page_id . '&member-tier=1$',
                        'index.php?page_id=' . $account_page_id . '&member-tier=1',
                        'top'
                    );
                } else {
                    // 美化連結結構（文章名稱、自訂結構等）
                    add_rewrite_rule(
                        '^' . $account_slug . '/points-rewards/?$',
                        'index.php?page_id=' . $account_page_id . '&points-rewards=1',
                        'top'
                    );
                    
                    add_rewrite_rule(
                        '^' . $account_slug . '/points-history/?$',
                        'index.php?page_id=' . $account_page_id . '&points-history=1',
                        'top'
                    );
                    
                    add_rewrite_rule(
                        '^' . $account_slug . '/member-tier/?$',
                        'index.php?page_id=' . $account_page_id . '&member-tier=1',
                        'top'
                    );
                    
                    // 支援分頁
                    add_rewrite_rule(
                        '^' . $account_slug . '/points-history/page/([0-9]{1,})/?$',
                        'index.php?page_id=' . $account_page_id . '&points-history=1&paged=$matches[1]',
                        'top'
                    );
                }
                
                // 📌 關鍵：增加查詢字串支援，確保向後兼容
                add_rewrite_rule(
                    '(.*)[\?&]points-rewards=1(.*)$',
                    'index.php?page_id=' . $account_page_id . '&points-rewards=1',
                    'top'
                );
                
                add_rewrite_rule(
                    '(.*)[\?&]points-history=1(.*)$',
                    'index.php?page_id=' . $account_page_id . '&points-history=1',
                    'top'
                );
                
                add_rewrite_rule(
                    '(.*)[\?&]member-tier=1(.*)$',
                    'index.php?page_id=' . $account_page_id . '&member-tier=1',
                    'top'
                );
            }
        }
    }
    
    /**
     * 重新整理重寫規則
     */
    public function maybe_flush_rewrite_rules() {
        if (get_option('wc_points_rewards_flush_rewrite_rules') === 'yes') {
            flush_rewrite_rules(false);
            delete_option('wc_points_rewards_flush_rewrite_rules');
        }
    }
    
    /**
     * 添加查詢變數
     */
    public function add_query_vars($vars) {
        $vars[] = 'points-rewards';
        $vars[] = 'points-history';
        $vars[] = 'member-tier';
        return $vars;
    }
    
    /**
     * 設定端點標題
     */
    public function endpoint_title($title, $id = null) {
        if (is_wc_endpoint_url() && is_main_query() && in_the_loop()) {
            if (get_query_var('points-rewards')) {
                $title = __('我的點數', 'wc-points-rewards');
            } elseif (get_query_var('points-history')) {
                $title = __('點數記錄', 'wc-points-rewards');
            } elseif (get_query_var('member-tier')) {
                $title = __('會員等級', 'wc-points-rewards');
            }
        }
        
        return $title;
    }
    
    // 🚀 其他方法保持完全不變，只是為了確保完整性...
    
    /**
     * 修正 D-1: 我的點數頁面內容
     */
    public function points_rewards_content() {
        // 檢查是否啟用點數系統
        if (!wc_points_rewards_is_enabled()) {
            echo '<div class="woocommerce-message">' . __('點數系統目前未啟用', 'wc-points-rewards') . '</div>';
            return;
        }
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            echo '<div class="woocommerce-message">' . __('請先登入', 'wc-points-rewards') . '</div>';
            return;
        }
        
        $database = WC_Points_Rewards_Database::instance();
        $tier_manager = WC_Points_Rewards_Member_Tier::instance();
        
        $current_points = $database->get_user_points($user_id);
        $current_tier = $database->get_user_current_tier($user_id);
        $tier_progress = $tier_manager->get_tier_progress($user_id);
        $recent_history = $database->get_user_points_history($user_id, 5);
        
        // 獲取即將到期的點數
        global $wpdb;
        $points_table = $wpdb->prefix . 'wc_points_rewards_points';
        $expiring_points = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(points) 
            FROM $points_table 
            WHERE user_id = %d 
            AND type = 'earned' 
            AND expiry_date IS NOT NULL 
            AND expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
        ", $user_id));
        
        $expiring_points = floatval($expiring_points ?? 0);
        
        // 修正 Warning: 確保 $tier_progress 有必要的鍵
        if ($tier_progress && !isset($tier_progress['current_spending'])) {
            $tier_progress['current_spending'] = 0;
        }
        
        $this->render_points_overview($current_points, $current_tier, $tier_progress, $recent_history, $expiring_points);
    }
    
    /**
     * 修正 D-2: 點數記錄頁面內容
     */
    public function points_history_content() {
        // 檢查是否啟用點數系統
        if (!wc_points_rewards_is_enabled()) {
            echo '<div class="woocommerce-message">' . __('點數系統目前未啟用', 'wc-points-rewards') . '</div>';
            return;
        }
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            echo '<div class="woocommerce-message">' . __('請先登入', 'wc-points-rewards') . '</div>';
            return;
        }
        
        $database = WC_Points_Rewards_Database::instance();
        
        // 分頁處理
        $per_page = 20;
        $current_page = max(1, intval(get_query_var('paged', 1)));
        $offset = ($current_page - 1) * $per_page;
        
        $history = $database->get_user_points_history($user_id, $per_page, $offset);
        
        // 計算總頁數
        global $wpdb;
        $points_table = $wpdb->prefix . 'wc_points_rewards_points';
        
        $total_records = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $points_table 
            WHERE user_id = %d
        ", $user_id));
        
        $total_pages = ceil(intval($total_records ?? 0) / $per_page);
        
        $this->render_points_history($history ?: array(), $current_page, $total_pages);
    }
    
    /**
     * 修正 D-3: 會員等級頁面內容
     */
    public function member_tier_content() {
        // 檢查是否啟用點數系統
        if (!wc_points_rewards_is_enabled()) {
            echo '<div class="woocommerce-message">' . __('點數系統目前未啟用', 'wc-points-rewards') . '</div>';
            return;
        }
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            echo '<div class="woocommerce-message">' . __('請先登入', 'wc-points-rewards') . '</div>';
            return;
        }
        
        $database = WC_Points_Rewards_Database::instance();
        $tier_manager = WC_Points_Rewards_Member_Tier::instance();
        
        $current_tier = $database->get_user_current_tier($user_id);
        $all_tiers = $tier_manager->get_all_tiers();
        $tier_progress = $tier_manager->get_tier_progress($user_id);
        
        // 修正 Warning: 確保 $tier_progress 有必要的鍵
        if ($tier_progress && !isset($tier_progress['current_spending'])) {
            $tier_progress['current_spending'] = 0;
        }
        
        // 獲取年度消費統計 - 修正從訂單數據中獲取真實消費金額
        global $wpdb;
        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $selected_year = intval($_GET['filter_year'] ?? date('Y'));
        
        // 先獲取統計表中的數據
        $yearly_stats = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $stats_table 
            WHERE user_id = %d AND year = %d
        ", $user_id, $selected_year));
        
        // 同時從訂單表中獲取真實的消費金額
        $orders_table = $wpdb->prefix . 'posts';
        $order_meta_table = $wpdb->prefix . 'postmeta';
        
        $actual_spent = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(pm.meta_value) 
            FROM $orders_table p
            INNER JOIN $order_meta_table pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND pm.meta_key = '_order_total'
            AND p.post_author = %d
            AND YEAR(p.post_date) = %d
        ", $user_id, $selected_year));
        
        // 獲取總點數
        $points_table = $wpdb->prefix . 'wc_points_rewards_points';
        $total_points_earned = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(points) 
            FROM $points_table 
            WHERE user_id = %d 
            AND type = 'earned'
            AND YEAR(created_at) = %d
        ", $user_id, $selected_year));
        
        // 如果沒有統計數據，創建一個基本對象
        if (!$yearly_stats) {
            $yearly_stats = (object) array(
                'user_id' => $user_id,
                'year' => $selected_year,
                'total_spent' => floatval($actual_spent ?? 0),
                'total_points_earned' => floatval($total_points_earned ?? 0),
                'current_tier_id' => $current_tier ? $current_tier->id : null,
                'tier_start_date' => null,
                'tier_expiry_date' => null
            );
        } else {
            // 更新真實消費金額和點數
            $yearly_stats->total_spent = floatval($actual_spent ?? 0);
            $yearly_stats->total_points_earned = floatval($total_points_earned ?? 0);
        }
        
        $this->render_member_tier($current_tier, $all_tiers, $tier_progress, $yearly_stats);
    }
    
    /**
     * 修正 D-4: 編輯帳戶頁面新增欄位
     */
    public function add_edit_account_fields() {
        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return;
        }
        
        // 獲取註冊時間
        $registration_date = $user->user_registered;
        
        // 獲取生日（自訂欄位）
        $birthday = get_user_meta($user_id, 'birthday', true);
        $birthday_set = get_user_meta($user_id, 'birthday_set', true);
        
        // 獲取聯絡電話（來自帳單地址）
        $billing_phone = get_user_meta($user_id, 'billing_phone', true);
        
        ?>
        <fieldset class="wc-points-rewards-account-fields">
            <legend><?php _e('會員資訊', 'wc-points-rewards'); ?></legend>
            
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="registration_date"><?php _e('會員註冊時間', 'wc-points-rewards'); ?></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" 
                       name="registration_date" id="registration_date" 
                       value="<?php echo esc_attr(date('Y-m-d H:i:s', strtotime($registration_date))); ?>" 
                       readonly style="background-color: #f9f9f9;">
                <span class="description"><?php _e('您的會員註冊日期和時間', 'wc-points-rewards'); ?></span>
            </p>
            
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="birthday"><?php _e('出生 - 年/月/日', 'wc-points-rewards'); ?></label>
                <input type="date" class="woocommerce-Input woocommerce-Input--date input-text" 
                       name="birthday" id="birthday" 
                       value="<?php echo esc_attr($birthday); ?>"
                       <?php echo $birthday_set ? 'readonly style="background-color: #f9f9f9;"' : ''; ?>>
                <?php if ($birthday_set): ?>
                    <span class="description"><?php _e('生日資訊已設定，無法修改', 'wc-points-rewards'); ?></span>
                <?php else: ?>
                    <span class="description"><?php _e('設定生日可獲得生日禮點數（僅可設定一次）', 'wc-points-rewards'); ?></span>
                <?php endif; ?>
            </p>
            
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="billing_phone"><?php _e('聯絡電話', 'wc-points-rewards'); ?></label>
                <input type="tel" class="woocommerce-Input woocommerce-Input--tel input-text" 
                       name="billing_phone" id="billing_phone" 
                       value="<?php echo esc_attr($billing_phone); ?>">
                <span class="description"><?php _e('您的聯絡電話，可隨時修改', 'wc-points-rewards'); ?></span>
            </p>
        </fieldset>
        
        <style>
        .wc-points-rewards-account-fields {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
            background: #f8f9fa;
        }
        
        .wc-points-rewards-account-fields legend {
            font-weight: bold;
            padding: 0 10px;
            font-size: 16px;
        }
        
        .wc-points-rewards-account-fields .description {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }
        </style>
        <?php
    }
    
    /**
     * 儲存編輯帳戶欄位
     */
    public function save_edit_account_fields($user_id) {
        // 處理生日設定
        if (isset($_POST['birthday']) && !empty($_POST['birthday'])) {
            $birthday_set = get_user_meta($user_id, 'birthday_set', true);
            
            // 只有未設定過生日的用戶才能設定
            if (!$birthday_set) {
                $birthday = sanitize_text_field($_POST['birthday']);
                
                // 驗證日期格式
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
                    update_user_meta($user_id, 'birthday', $birthday);
                    update_user_meta($user_id, 'birthday_set', true);
                    
                    // 修正：不立即發放生日點數，而是等到生日月的第1天發放
                    $birthday_date = new DateTime($birthday);
                    $current_date = new DateTime();
                    
                    // 如果當前是生日月且今天是1號，則發放點數
                    if ($birthday_date->format('m') === $current_date->format('m') && $current_date->format('j') == '1') {
                        do_action('wc_points_rewards_birthday_set', $user_id);
                    }
                    
                    wc_add_notice(sprintf(
                        __('生日設定成功！生日點數將於生日月的第1天發放。', 'wc-points-rewards')
                    ), 'success');
                }
            }
        }
        
        // 處理聯絡電話更新
        if (isset($_POST['billing_phone'])) {
            $billing_phone = sanitize_text_field($_POST['billing_phone']);
            update_user_meta($user_id, 'billing_phone', $billing_phone);
            
            // 也同時更新到帳單地址資訊（確保一致性）
            if (!empty($billing_phone)) {
                wc_add_notice(__('聯絡電話已更新', 'wc-points-rewards'), 'success');
            }
        }
    }
    
    /**
     * 在帳戶儀表板顯示點數資訊
     */
    public function display_dashboard_points_info() {
        // 檢查是否啟用點數系統
        if (!wc_points_rewards_is_enabled()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $database = WC_Points_Rewards_Database::instance();
        
        $current_points = $database->get_user_points($user_id);
        $current_tier = $database->get_user_current_tier($user_id);
        
        if ($current_points > 0 || $current_tier) {
            echo '<div class="wc-points-rewards-dashboard-info">';
            
            if ($current_points > 0) {
                echo '<div class="points-balance-widget">';
                echo '<h3>' . __('我的點數', 'wc-points-rewards') . '</h3>';
                echo '<div class="points-value">' . wc_points_rewards_number_format($current_points) . ' ' . __('點', 'wc-points-rewards') . '</div>';
                echo '<a href="' . wc_points_rewards_get_account_endpoint_url('points-rewards') . '" class="button">' . __('查看詳情', 'wc-points-rewards') . '</a>';
                echo '</div>';
            }
            
            if ($current_tier) {
                echo '<div class="member-tier-widget">';
                echo '<h3>' . __('會員等級', 'wc-points-rewards') . '</h3>';
                echo '<div class="tier-name">' . esc_html($current_tier->name) . '</div>';
                if ($current_tier->bonus_percentage > 0) {
                    echo '<div class="tier-benefit">+' . wc_points_rewards_format_percentage($current_tier->bonus_percentage) . ' ' . __('點數回饋', 'wc-points-rewards') . '</div>';
                }
                echo '<a href="' . wc_points_rewards_get_account_endpoint_url('member-tier') . '" class="button">' . __('查看等級', 'wc-points-rewards') . '</a>';
                echo '</div>';
            }
            
            echo '</div>';
        }
    }
    
    // === 以下所有渲染方法保持完全不變 ===
    
    private function render_points_overview($current_points, $current_tier, $tier_progress, $recent_history, $expiring_points) {
        ?>
        <div class="wc-points-rewards-overview">
            <div class="points-summary">
                <div class="points-balance-card">
                    <h3><?php _e('點數餘額', 'wc-points-rewards'); ?></h3>
                    <div class="points-amount"><?php echo wc_points_rewards_number_format($current_points); ?> <?php echo wc_points_rewards_get_points_name(); ?></div>
                    <p class="points-value"><?php printf(__('等於 %s', 'wc-points-rewards'), wc_price($current_points * wc_points_rewards_get_points_value())); ?></p>
                </div>
                
                <?php if ($current_tier): ?>
                <div class="tier-info-card">
                    <h3><?php _e('會員等級', 'wc-points-rewards'); ?></h3>
                    <div class="tier-name"><?php echo esc_html($current_tier->name); ?></div>
                    <?php if ($current_tier->bonus_percentage > 0): ?>
                    <p class="tier-benefit"><?php printf(__('額外 +%s 點數回饋', 'wc-points-rewards'), wc_points_rewards_format_percentage($current_tier->bonus_percentage, true)); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($expiring_points > 0): ?>
            <div class="expiring-notice">
                <h4><?php _e('即將到期點數', 'wc-points-rewards'); ?></h4>
                <p><?php printf(__('您有 %s 將在30天內到期，請盡快使用！', 'wc-points-rewards'), wc_points_rewards_number_format($expiring_points) . ' ' . __('點', 'wc-points-rewards')); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- 升級進度區塊已移除 -->
            
            <?php if (!empty($recent_history)): ?>
            <div class="recent-history">
                <h4><?php _e('最近點數記錄', 'wc-points-rewards'); ?></h4>
                <table class="recent-history-table">
                    <thead>
                        <tr>
                            <th><?php _e('日期', 'wc-points-rewards'); ?></th>
                            <th><?php _e('點數變化', 'wc-points-rewards'); ?></th>
                            <th><?php _e('說明', 'wc-points-rewards'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_history as $record): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($record->created_at)); ?></td>
                            <td>
                                <span class="points-amount <?php echo floatval($record->points) > 0 ? 'positive' : 'negative'; ?>">
                                    <?php echo floatval($record->points) > 0 ? '+' : ''; ?><?php echo wc_points_rewards_number_format($record->points); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($record->description); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><a href="<?php echo wc_points_rewards_get_account_endpoint_url('points-history'); ?>"><?php _e('查看完整記錄', 'wc-points-rewards'); ?></a></p>
            </div>
            <?php endif; ?>
            
            <div class="quick-actions">
                <a href="<?php echo wc_get_page_permalink('shop'); ?>" class="button"><?php _e('開始購物', 'wc-points-rewards'); ?></a>
                <a href="<?php echo wc_points_rewards_get_account_endpoint_url('points-history'); ?>" class="button"><?php _e('查看記錄', 'wc-points-rewards'); ?></a>
                <a href="<?php echo wc_points_rewards_get_account_endpoint_url('member-tier'); ?>" class="button"><?php _e('會員等級', 'wc-points-rewards'); ?></a>
            </div>
        </div>
        
        <style>
        .wc-points-rewards-overview {
            max-width: 800px;
        }
        
        .points-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .points-balance-card,
        .tier-info-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .points-amount {
            font-size: 1.7em;
            font-weight: bold;
            color: #28a745;
            margin: 10px 0;
        }
        
        .tier-name {
            font-size: 1.5em;
            font-weight: bold;
            color: #007cba;
            margin: 10px 0;
        }
        
        .expiring-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .tier-progress {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #dee2e6;
            border-radius: 10px;
            overflow: hidden;
            margin: 15px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.5s ease;
        }
        
        .progress-details {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #6c757d;
        }
        
        .recent-history-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .recent-history-table th,
        .recent-history-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .recent-history-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .points-amount.positive { color: #28a745; font-weight: bold; }
        .points-amount.negative { color: #dc3545; font-weight: bold; }
        
        .quick-actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .quick-actions .button {
            margin: 0 10px 10px 0;
        }
        </style>
        <?php
    }
    
    private function render_points_history($history, $current_page, $total_pages) {
        ?>
        <div class="wc-points-history">
            <h3><?php _e('點數記錄', 'wc-points-rewards'); ?></h3>
            
            <?php if (!empty($history)): ?>
            <table class="wc-points-history-table">
                <thead>
                    <tr>
                        <th><?php _e('日期', 'wc-points-rewards'); ?></th>
                        <th><?php _e('類型', 'wc-points-rewards'); ?></th>
                        <th><?php _e('點數變化', 'wc-points-rewards'); ?></th>
                        <th><?php _e('說明', 'wc-points-rewards'); ?></th>
                        <th><?php _e('到期日', 'wc-points-rewards'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $record): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i', strtotime($record->created_at)); ?></td>
                        <td>
                            <span class="points-type-<?php echo esc_attr($record->type); ?>">
                                <?php
                                switch ($record->type) {
                                    case 'earned':
                                        _e('獲得', 'wc-points-rewards');
                                        break;
                                    case 'redeemed':
                                        _e('使用', 'wc-points-rewards');
                                        break;
                                    case 'expired':
                                        _e('過期', 'wc-points-rewards');
                                        break;
                                    case 'admin':
                                        _e('調整', 'wc-points-rewards');
                                        break;
                                }
                                ?>
                            </span>
                        </td>
                        <td>
                            <span class="points-amount <?php echo floatval($record->points) > 0 ? 'positive' : 'negative'; ?>">
                                <?php echo floatval($record->points) > 0 ? '+' : ''; ?><?php echo wc_points_rewards_number_format($record->points); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($record->description); ?></td>
                        <td>
                            <?php if (isset($record->expiry_date) && $record->expiry_date): ?>
                                <?php echo date('Y-m-d', strtotime($record->expiry_date)); ?>
                                <?php if (strtotime($record->expiry_date) < strtotime('+30 days')): ?>
                                    <span class="expiry-warning">⚠️</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="no-expiry">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
            <div class="wc-points-pagination">
                <?php
                $base_url = wc_points_rewards_get_account_endpoint_url('points-history');
                
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%', $base_url),
                    'format' => '',
                    'current' => max(1, $current_page),
                    'total' => $total_pages,
                    'prev_text' => __('« 上一頁', 'wc-points-rewards'),
                    'next_text' => __('下一頁 »', 'wc-points-rewards'),
                ));
                ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="no-records">
                <p><?php _e('暫無點數記錄', 'wc-points-rewards'); ?></p>
                <a href="<?php echo wc_get_page_permalink('shop'); ?>" class="button"><?php _e('開始購物賺取點數', 'wc-points-rewards'); ?></a>
            </div>
            <?php endif; ?>
        </div>
        
        <style>

        
        .wc-points-history-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .wc-points-history-table th,
        .wc-points-history-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .wc-points-history-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .points-type-earned { color: #28a745; }
        .points-type-redeemed { color: #dc3545; }
        .points-type-expired { color: #6c757d; }
        .points-type-admin { color: #007cba; }
        
        .points-amount.positive { color: #28a745; font-weight: bold; }
        .points-amount.negative { color: #dc3545; font-weight: bold; }
        
        .expiry-warning { color: #dc3545; }
        .no-expiry { color: #6c757d; }
        
        .no-records {
            text-align: center;
            padding: 40px 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .wc-points-pagination {
            text-align: center;
            margin-top: 20px;
        }
        </style>
        <?php
    }
    
    private function render_member_tier($current_tier, $all_tiers, $tier_progress, $yearly_stats) {
        ?>
        <div class="wc-member-tier">
            <h3><?php _e('會員等級', 'wc-points-rewards'); ?></h3>
            
            <div class="current-tier-info">
                <div class="tier-card current">
                    <?php if ($current_tier): ?>
                        <div class="tier-name-badge"><?php echo esc_html($current_tier->name); ?>
                            <span class="current-badge"><?php _e('目前等級', 'wc-points-rewards'); ?></span>
                        </div>
                        <div class="tier-requirements"><?php printf(__('消費滿 %s', 'wc-points-rewards'), wc_price($current_tier->min_amount)); ?></div>
                        <div class="tier-benefits-info">
                            <?php if ($current_tier->bonus_percentage > 0): ?>
                                <span class="benefit-item">+<?php echo esc_html(wc_points_rewards_format_percentage($current_tier->bonus_percentage)); ?> <?php _e('點數回饋', 'wc-points-rewards'); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="tier-name-badge"><?php _e('一般會員', 'wc-points-rewards'); ?>
                            <span class="current-badge"><?php _e('目前等級', 'wc-points-rewards'); ?></span>
                        </div>
                        <div class="tier-requirements"><?php _e('無特殊要求', 'wc-points-rewards'); ?></div>
                        <div class="tier-benefits-info">
                            <span class="benefit-item"><?php _e('標準回饋', 'wc-points-rewards'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($tier_progress && $tier_progress['next_tier']): ?>
            <div class="tier-progress">
                <h4><?php _e('升級進度', 'wc-points-rewards'); ?></h4>
                <div class="progress-info">
                    <p><?php printf(__('升級至 %s', 'wc-points-rewards'), '<strong>' . esc_html($tier_progress['next_tier']->name) . '</strong>'); ?></p>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo esc_attr($tier_progress['progress_percentage'] ?? 0); ?>%"></div>
                </div>
                <div class="progress-details">
                    <span><?php echo wc_price($tier_progress['current_spending'] ?? 0); ?></span>
                    <span><?php printf(__('還需 %s', 'wc-points-rewards'), wc_price($tier_progress['amount_to_next'] ?? 0)); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($yearly_stats): ?>
            <div class="yearly-stats">
                <h4><?php printf(__('%d年消費統計', 'wc-points-rewards'), date('Y')); ?></h4>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-label"><?php _e('年度消費金額', 'wc-points-rewards'); ?></div>
                        <div class="stat-value"><?php echo wc_price(floatval($yearly_stats->total_spent ?? 0)); ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label"><?php _e('獲得總點數', 'wc-points-rewards'); ?></div>
                        <div class="stat-value"><?php echo wc_points_rewards_number_format(floatval($yearly_stats->total_points_earned ?? 0)); ?> <?php echo wc_points_rewards_get_points_name(); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($all_tiers)): ?>
            <div class="all-tiers">
                <h4><?php _e('會員等級說明', 'wc-points-rewards'); ?></h4>
                <div class="tiers-grid">
                    <?php foreach ($all_tiers as $tier): ?>
                    <div class="tier-card <?php echo ($current_tier && $current_tier->id === $tier->id) ? 'current' : ''; ?>">
                        <div class="tier-name"><?php echo esc_html($tier->name); ?></div>
                        <div class="tier-requirement"><?php printf(__('消費滿 %s', 'wc-points-rewards'), wc_price($tier->min_amount)); ?></div>
                        <?php if ($tier->bonus_percentage > 0): ?>
                            <div class="tier-bonus">+<?php echo wc_points_rewards_format_percentage($tier->bonus_percentage); ?> <?php _e('點數回饋', 'wc-points-rewards'); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .wc-member-tier {
            max-width: 800px;
        }
        
        .current-tier-info {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .tier-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            display: inline-block;
            min-width: 200px;
        }
        
        .tier-name {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .tier-benefit {
            font-size: 1.1em;
        }
        
        .tier-progress {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #dee2e6;
            border-radius: 10px;
            overflow: hidden;
            margin: 15px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.5s ease;
        }
        
        .progress-details {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #6c757d;
        }
        
        .yearly-stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-label {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 1.3em;
            font-weight: bold;
            color: #495057;
        }
        
        .tiers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .tier-card {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .tier-card.current {
            border-color: #007cba;
            background: #f0f8ff;
        }
        
        .tier-card .tier-name {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .tier-requirement {
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .tier-bonus {
            color: #28a745;
            font-weight: bold;
        }
        </style>
        <?php
    }
    
    /**
     * 🚀 新增：修正帳戶導航選單中的 URL
     */
    public function fix_account_navigation_urls($navigation_html) {
        // 如果沒有 HTML 內容，直接返回
        if (empty($navigation_html)) {
            return $navigation_html;
        }
        
        // 使用正則表達式替換我們的端點 URL
        $endpoints = array('points-rewards', 'points-history', 'member-tier');
        
        foreach ($endpoints as $endpoint) {
            // 匹配路徑格式的 URL 並替換為查詢參數格式
            $pattern = '#href=["\']([^"\']*)/my-account/' . preg_quote($endpoint, '#') . '/([^"\']*)["\']#i';
            $replacement = 'href="$1/my-account/?'. $endpoint . '$2"';
            $navigation_html = preg_replace($pattern, $replacement, $navigation_html);
            
            // 處理更通用的情況
            $pattern = '#href=["\']([^"\']*/)' . preg_quote($endpoint, '#') . '/([^"\']*)["\']#i';
            $replacement = 'href="$1?' . $endpoint . '$2"';
            $navigation_html = preg_replace($pattern, $replacement, $navigation_html);
        }
        
        return $navigation_html;
    }
    
    /**
     * 🚀 新增：開始導航選單輸出緩衝
     */
    public function start_navigation_buffer() {
        ob_start();
    }
    
    /**
     * 🚀 新增：結束導航選單輸出緩衝並修正 URL
     */
    public function end_navigation_buffer() {
        $navigation_html = ob_get_clean();
        
        // 修正 URL
        $navigation_html = $this->fix_account_navigation_urls($navigation_html);
        
        echo $navigation_html;
    }
    
    /**
     * 🚀 新增：在頁腳修正導航連結（最終方案）
     */
    public function fix_navigation_urls_on_footer() {
        if (!is_account_page()) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // 修正所有我們的端點連結
            var endpoints = ['points-rewards', 'points-history', 'member-tier'];
            var accountUrl = '';
            
            // 嘗試找到我的帳戶頁面 URL
            $('.woocommerce-MyAccount-navigation a').first().each(function() {
                var href = $(this).attr('href');
                if (href) {
                    var urlParts = href.split('/');
                    // 重建基礎 URL
                    for (var i = 0; i < urlParts.length; i++) {
                        if (urlParts[i] === 'my-account' || urlParts[i].indexOf('page_id=') !== -1) {
                            accountUrl = urlParts.slice(0, i + 1).join('/') + '/';
                            break;
                        }
                    }
                }
            });
            
            if (!accountUrl) {
                // 後備方案：從當前 URL 提取
                accountUrl = window.location.origin + window.location.pathname.split('/my-account')[0] + '/my-account/';
            }
            
            endpoints.forEach(function(endpoint) {
                // 修正導航選單中的連結
                $('.woocommerce-MyAccount-navigation-link--' + endpoint + ' a, a[href*="/' + endpoint + '/"]').each(function() {
                    var $link = $(this);
                    var currentHref = $link.attr('href');
                    
                    if (currentHref && (currentHref.indexOf('/' + endpoint + '/') !== -1 || currentHref.indexOf('/' + endpoint) !== -1)) {
                        // 確保這是導航選單中的連結
                        if ($link.closest('.woocommerce-MyAccount-navigation').length > 0) {
                            var newHref = accountUrl + '?' + endpoint;
                            $link.attr('href', newHref);
                            console.log('Fixed navigation URL for ' + endpoint + ': ' + newHref);
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * 🚀 新增：過濾導航端點 URL 以使用查詢參數格式
     */
    public function filter_navigation_endpoint_urls($url, $endpoint, $value, $permalink) {
        // 只處理我們的端點
        if (in_array($endpoint, array('points-rewards', 'points-history', 'member-tier'))) {
            return $this->get_account_endpoint_url($endpoint);
        }
        return $url;
    }
    
    /**
     * 🚀 新增：過濾 WooCommerce 端點 URL（另一個 hook）
     */
    public function filter_wc_endpoint_urls($url, $endpoint, $value, $permalink) {
        // 只處理我們的端點
        if (in_array($endpoint, array('points-rewards', 'points-history', 'member-tier'))) {
            return $this->get_account_endpoint_url($endpoint);
        }
        return $url;
    }
    
    /**
     * 🚀 新增：使用 JavaScript 修正導航選單 URL（後備方案）
     */
    public function fix_navigation_urls_with_js() {
        if (!is_account_page()) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // 修正導航選單中的點數相關連結
            var endpoints = ['points-rewards', 'points-history', 'member-tier'];
            
            endpoints.forEach(function(endpoint) {
                // 尋找包含該端點的導航連結
                $('.woocommerce-MyAccount-navigation-link--' + endpoint + ' a').each(function() {
                    var currentHref = $(this).attr('href');
                    if (currentHref && currentHref.indexOf('/' + endpoint + '/') !== -1) {
                        // 替換為查詢參數格式
                        var newHref = currentHref.replace('/' + endpoint + '/', '/?' + endpoint);
                        $(this).attr('href', newHref);
                    }
                });
                
                // 也處理沒有完整類別名稱的情況
                $('a[href*="/' + endpoint + '/"]').each(function() {
                    var $link = $(this);
                    // 確認這是導航選單中的連結
                    if ($link.closest('.woocommerce-MyAccount-navigation').length > 0) {
                        var currentHref = $link.attr('href');
                        var newHref = currentHref.replace('/' + endpoint + '/', '/?' + endpoint);
                        $link.attr('href', newHref);
                    }
                });
            });
        });
        </script>
        <?php
    }
}
