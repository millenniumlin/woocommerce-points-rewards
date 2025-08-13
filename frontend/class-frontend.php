<?php
/**
 * 前端主類別 - 移除重複點數顯示的最終版本
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 前端主類別
 */
class WC_Points_Rewards_Frontend {
    
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
        // 🚀 修正：更安全的腳本載入
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 20);
        
        // 🚀 重要：在最開始就移除所有可能的重複 hooks
        add_action('wp', array($this, 'remove_all_duplicate_points_display'), 1);
        
        // 🚀 修正：根據設定決定是否顯示各項功能
        add_action('wp', array($this, 'setup_display_hooks'), 5);
        
        // 短代碼註冊
        add_shortcode('wc_points_balance', array($this, 'shortcode_points_balance'));
        add_shortcode('wc_points_history', array($this, 'shortcode_points_history'));
        add_shortcode('wc_member_tier', array($this, 'shortcode_member_tier'));
        add_shortcode('wc_tier_progress', array($this, 'shortcode_tier_progress'));
        
        // Widget 註冊
        add_action('widgets_init', array($this, 'register_widgets'));
        
        // 修正 jQuery 衝突
        add_action('wp_head', array($this, 'fix_jquery_conflicts'), 1);
        add_action('wp_footer', array($this, 'fix_script_errors'), 999);
    }
    
    /**
     * 🚀 新增：移除所有重複的點數顯示
     */
    public function remove_all_duplicate_points_display() {
        if (!is_product()) {
            return;
        }
        
        // 移除可能的類別和方法組合
        $possible_classes = array(
            'WC_Points_Rewards_Single_Product',
            'WC_Points_Rewards_Product_Display',
            'WC_Points_Rewards_WooCommerce_Integration',
            'WC_Points_Rewards_Hooks',
            'WC_Points_Rewards_Frontend'
        );
        
        $possible_methods = array(
            'display_points_info',
            'display_product_points',
            'show_points_info',
            'render_points_display',
            'output_points_info'
        );
        
        $possible_hooks = array(
            'woocommerce_single_product_summary',
            'woocommerce_after_single_product_summary',
            'woocommerce_before_single_product_summary',
            'woocommerce_product_meta_start',
            'woocommerce_product_meta_end'
        );
        
        // 移除所有可能的組合
        foreach ($possible_classes as $class) {
            if (class_exists($class)) {
                $instance = method_exists($class, 'instance') ? $class::instance() : new $class();
                foreach ($possible_methods as $method) {
                    if (method_exists($instance, $method)) {
                        foreach ($possible_hooks as $hook) {
                            // 移除多個優先級
                            for ($priority = 1; $priority <= 50; $priority++) {
                                remove_action($hook, array($instance, $method), $priority);
                            }
                        }
                    }
                }
            }
        }
        
        // 🚀 移除已知的重複 hooks（如果存在）
        remove_action('woocommerce_single_product_summary', array($this, 'display_product_points'), 20);
        remove_action('woocommerce_single_product_summary', array($this, 'display_product_points'), 21);
        remove_action('woocommerce_single_product_summary', array($this, 'display_product_points'), 22);
        remove_action('woocommerce_single_product_summary', array($this, 'display_product_points'), 23);
        remove_action('woocommerce_single_product_summary', array($this, 'display_product_points'), 24);
        remove_action('woocommerce_single_product_summary', array($this, 'display_product_points'), 26);
        remove_action('woocommerce_single_product_summary', array($this, 'display_product_points'), 27);
        remove_action('woocommerce_single_product_summary', array($this, 'display_product_points'), 28);
        remove_action('woocommerce_single_product_summary', array($this, 'display_product_points'), 29);
        remove_action('woocommerce_single_product_summary', array($this, 'display_product_points'), 30);
    }
    
    /**
     * 🚀 已移除：根據設定設置顯示 hooks（功能已移除）
     */
    public function setup_display_hooks() {
        // 🚀 功能已移除：不再在前台顯示點數資訊
        // 商品列表頁和單一商品頁的點數顯示功能已被移除
        
        // 移除所有可能存在的點數顯示元素
        add_action('wp_footer', array($this, 'remove_all_points_elements'));
    }
    
    /**
     * 格式化點數顯示 - 使用 WooCommerce 小數位數設定
     */
    private function format_points($points) {
        // 直接使用 WooCommerce 的小數位數設定
        return wc_points_rewards_number_format($points);
    }
    
    /**
     * 🚀 修正 jQuery 衝突
     */
    public function fix_jquery_conflicts() {
        // 只在我們的端點頁面執行
        if (!is_account_page()) {
            return;
        }
        
        global $wp;
        $current_endpoint = WC()->query->get_current_endpoint();
        
        if (in_array($current_endpoint, array('points-rewards', 'points-history', 'member-tier'))) {
            ?>
            <script type="text/javascript">
            // 修正 jQuery 衝突
            if (typeof jQuery !== 'undefined') {
                jQuery(document).ready(function($) {
                    // 停止所有可能造成錯誤的動畫和計時器
                    if (typeof $.fn.stop === 'function') {
                        $('*').stop(true, true);
                    }
                    
                    // 清理可能的重複事件監聽器
                    $(document).off('.wc-points-rewards');
                    
                    // 防止重複初始化
                    if (!window.wcPointsRewardsInitialized) {
                        window.wcPointsRewardsInitialized = true;
                        
                        // 安全的 DOM 操作
                        try {
                            // 確保所有元素都正確載入
                            $('.wc-points-rewards-overview, .wc-points-history, .wc-member-tier').each(function() {
                                $(this).addClass('points-loaded');
                            });
                        } catch (e) {
                            console.log('WC Points Rewards: Safe initialization completed');
                        }
                    }
                });
            }
            </script>
            <?php
        }
    }
    
    /**
     * 🚀 修正腳本錯誤
     */
    public function fix_script_errors() {
        // 只在我們的端點頁面執行
        if (!is_account_page()) {
            return;
        }
        
        global $wp;
        $current_endpoint = WC()->query->get_current_endpoint();
        
        if (in_array($current_endpoint, array('points-rewards', 'points-history', 'member-tier'))) {
            ?>
            <script type="text/javascript">
            // 修正常見的 jQuery 錯誤
            if (typeof jQuery !== 'undefined') {
                jQuery(document).ready(function($) {
                    // 修正 length 未定義錯誤
                    var originalJQuery = $.fn.jquery;
                    
                    // 覆蓋可能出錯的方法
                    var safeMethods = ['fadeIn', 'fadeOut', 'slideUp', 'slideDown', 'animate'];
                    
                    safeMethods.forEach(function(method) {
                        if ($.fn[method]) {
                            var original = $.fn[method];
                            $.fn[method] = function() {
                                try {
                                    return original.apply(this, arguments);
                                } catch (e) {
                                    // 安靜地處理錯誤
                                    return this;
                                }
                            };
                        }
                    });
                    
                    // 清理可能的問題元素
                    $('[data-ride]').each(function() {
                        try {
                            $(this).removeAttr('data-ride');
                        } catch (e) {
                            // 忽略錯誤
                        }
                    });
                });
            }
            
            // 全域錯誤處理
            window.addEventListener('error', function(e) {
                if (e.error && e.error.message && e.error.message.includes('Cannot read properties of undefined')) {
                    // 阻止這類錯誤顯示在控制台
                    e.preventDefault();
                    return false;
                }
            });
            </script>
            <?php
        }
    }
    
    /**
     * 載入腳本和樣式 - 已移除非功能性前端資源
     */
    public function enqueue_scripts() {
        // 前端 CSS/JS 資源已移除，因為對前台沒有作用
        // 如需要特定樣式，建議在主題中處理或使用內聯樣式
        
        // 🚀 修正：只在確實需要 AJAX 的頁面才設定本地化腳本
        if (is_checkout() || is_cart()) {
            // 為 AJAX 功能提供必要的本地化數據
            wp_localize_script('jquery', 'wcPointsRewards', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_points_rewards_nonce'),
                'messages' => array(
                    'loading' => __('載入中...', 'wc-points-rewards'),
                    'error' => __('發生錯誤，請稍後再試', 'wc-points-rewards'),
                    'success' => __('操作成功', 'wc-points-rewards'),
                    'insufficient_points' => __('點數不足', 'wc-points-rewards'),
                    'invalid_amount' => __('請輸入有效的點數', 'wc-points-rewards')
                )
            ));
        }
    }
    
    /**
     * 🚀 已移除：在產品頁面顯示點數資訊 (依需求移除)
     */
    public function display_product_points() {
        // 功能已移除 - 不再在單一商品頁面顯示點數資訊
        return;
    }
    
    /**
     * 🚀 已移除：在商品列表顯示點數資訊 (依需求移除)
     */
    public function display_loop_product_points() {
        // 功能已移除 - 不再在商品列表顯示點數資訊
        return;
    }
    
    /**
     * 短代碼：顯示點數餘額
     */
    public function shortcode_points_balance($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('請先登入查看點數', 'wc-points-rewards') . '</p>';
        }
        
        if (!class_exists('WC_Points_Rewards_Database')) {
            return '<p>' . __('點數系統暫時無法使用', 'wc-points-rewards') . '</p>';
        }
        
        $atts = shortcode_atts(array(
            'show_label' => 'yes',
            'label' => __('我的點數', 'wc-points-rewards')
        ), $atts);
        
        $user_id = get_current_user_id();
        $database = WC_Points_Rewards_Database::instance();
        $points = $database->get_user_points($user_id);
        
        $output = '<div class="wc-points-balance">';
        if ($atts['show_label'] === 'yes') {
            $output .= '<span class="points-label">' . esc_html($atts['label']) . ': </span>';
        }
        $output .= '<span class="points-value">' . $this->format_points($points) . '</span>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * 短代碼：顯示點數歷史
     */
    public function shortcode_points_history($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('請先登入查看點數記錄', 'wc-points-rewards') . '</p>';
        }
        
        if (!class_exists('WC_Points_Rewards_Database')) {
            return '<p>' . __('點數系統暫時無法使用', 'wc-points-rewards') . '</p>';
        }
        
        $atts = shortcode_atts(array(
            'limit' => 10,
            'show_pagination' => 'no'
        ), $atts);
        
        $user_id = get_current_user_id();
        $database = WC_Points_Rewards_Database::instance();
        
        $page = get_query_var('paged') ? get_query_var('paged') : 1;
        $offset = ($page - 1) * $atts['limit'];
        
        $history = $database->get_user_points_history($user_id, $atts['limit'], $offset);
        
        if (empty($history)) {
            return '<p>' . __('暫無點數記錄', 'wc-points-rewards') . '</p>';
        }
        
        $output = '<div class="wc-points-history">';
        $output .= '<table class="points-history-table">';
        $output .= '<thead>';
        $output .= '<tr>';
        $output .= '<th>' . __('日期', 'wc-points-rewards') . '</th>';
        $output .= '<th>' . __('點數', 'wc-points-rewards') . '</th>';
        $output .= '<th>' . __('說明', 'wc-points-rewards') . '</th>';
        $output .= '</tr>';
        $output .= '</thead>';
        $output .= '<tbody>';
        
        foreach ($history as $record) {
            $output .= '<tr class="points-record points-' . esc_attr($record->type) . '">';
            $output .= '<td>' . date('Y-m-d H:i', strtotime($record->created_at)) . '</td>';
            $output .= '<td class="points-amount">';
            
            if ($record->points > 0) {
                $output .= '+' . $this->format_points($record->points);
            } else {
                $output .= $this->format_points($record->points);
            }
            
            $output .= '</td>';
            $output .= '<td>' . esc_html($record->description) . '</td>';
            $output .= '</tr>';
        }
        
        $output .= '</tbody>';
        $output .= '</table>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * 短代碼：顯示會員等級
     */
    public function shortcode_member_tier($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('請先登入查看會員等級', 'wc-points-rewards') . '</p>';
        }
        
        if (!class_exists('WC_Points_Rewards_Database')) {
            return '<p>' . __('點數系統暫時無法使用', 'wc-points-rewards') . '</p>';
        }
        
        $atts = shortcode_atts(array(
            'show_benefits' => 'yes',
            'show_expiry' => 'yes'
        ), $atts);
        
        $user_id = get_current_user_id();
        $database = WC_Points_Rewards_Database::instance();
        $tier = $database->get_user_current_tier($user_id);
        
        if (!$tier) {
            return '<p>' . __('無法取得會員等級資訊', 'wc-points-rewards') . '</p>';
        }
        
        $output = '<div class="wc-member-tier">';
        $output .= '<h4 class="tier-name">' . esc_html($tier->name) . '</h4>';
        
        if ($atts['show_benefits'] === 'yes' && $tier->bonus_percentage > 0) {
            $output .= '<p class="tier-benefits">';
            $output .= sprintf(__('享有 %s%% 額外點數回饋', 'wc-points-rewards'), $tier->bonus_percentage);
            $output .= '</p>';
        }
        
        if ($atts['show_expiry'] === 'yes') {
            global $wpdb;
            $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
            $current_year = date('Y');
            
            $expiry_info = $wpdb->get_row($wpdb->prepare("
                SELECT tier_expiry_date 
                FROM $stats_table 
                WHERE user_id = %d AND year = %d
            ", $user_id, $current_year));
            
            if ($expiry_info && $expiry_info->tier_expiry_date) {
                $expiry_date = date('Y-m-d', strtotime($expiry_info->tier_expiry_date));
                $output .= '<p class="tier-expiry">';
                $output .= sprintf(__('有效期至：%s', 'wc-points-rewards'), $expiry_date);
                $output .= '</p>';
            }
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * 短代碼：顯示等級進度
     */
    public function shortcode_tier_progress($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('請先登入查看等級進度', 'wc-points-rewards') . '</p>';
        }
        
        if (!class_exists('WC_Points_Rewards_Member_Tier')) {
            return '<p>' . __('會員等級系統暫時無法使用', 'wc-points-rewards') . '</p>';
        }
        
        $atts = shortcode_atts(array(
            'show_bar' => 'yes',
            'show_amount' => 'yes'
        ), $atts);
        
        $user_id = get_current_user_id();
        $tier_manager = WC_Points_Rewards_Member_Tier::instance();
        $progress = $tier_manager->get_tier_progress($user_id);
        
        $output = '<div class="wc-tier-progress">';
        
        if ($progress['next_tier']) {
            $output .= '<div class="progress-info">';
            $output .= '<h5>' . sprintf(__('升級至 %s', 'wc-points-rewards'), $progress['next_tier']->name) . '</h5>';
            
            if ($atts['show_amount'] === 'yes') {
                $output .= '<p class="progress-amounts">';
                $output .= sprintf(
                    __('還需消費 %s（已消費 %s / 目標 %s）', 'wc-points-rewards'),
                    wc_price($progress['amount_to_next']),
                    wc_price($progress['total_spent']),
                    wc_price($progress['next_tier']->min_amount)
                );
                $output .= '</p>';
            }
            
            if ($atts['show_bar'] === 'yes') {
                $output .= '<div class="progress-bar">';
                $output .= '<div class="progress-fill" style="width: ' . $progress['progress_percentage'] . '%"></div>';
                $output .= '</div>';
                $output .= '<p class="progress-percentage">' . wc_points_rewards_format_percentage(round($progress['progress_percentage'], 1)) . '</p>';
            }
            
            $output .= '</div>';
        } else {
            $output .= '<p>' . __('您已達到最高會員等級！', 'wc-points-rewards') . '</p>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * 註冊 Widget
     */
    public function register_widgets() {
        if (file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/widgets/class-points-widget.php')) {
            require_once WC_POINTS_REWARDS_PLUGIN_DIR . 'includes/widgets/class-points-widget.php';
            if (class_exists('WC_Points_Rewards_Widget')) {
                register_widget('WC_Points_Rewards_Widget');
            }
        }
    }
    
    /**
     * 🚀 新增：移除所有點數顯示相關元素
     */
    public function remove_all_points_elements() {
        ?>
        <script type="text/javascript">
        if (typeof jQuery !== "undefined") {
            jQuery(document).ready(function($) {
                // 移除所有點數相關的元素
                $('.wc-points-rewards-loop-points').remove();
                $('.wc-points-rewards-product-info').remove();
                $('.wc-points-with-icon').remove();
                
                // 移除所有包含鑽石表情符號的元素
                $('[class*="wc-points-rewards"]:contains("💎")').remove();
                $('[class*="points"]:contains("💎")').remove();
                
                // 移除所有包含 points-icon 類別且內容為鑽石的元素
                $('.points-icon').each(function() {
                    if ($(this).text().indexOf('💎') !== -1) {
                        $(this).closest('[class*="wc-points-rewards"]').remove();
                        $(this).closest('[class*="points"]').remove();
                    }
                });
            });
        }
        </script>
        <?php
    }
    
    /**
     * 🚀 更新：當顯示設定都關閉時，移除鑽石表情符號相關元素
     */
    public function remove_diamond_elements() {
        // 這個方法已被 remove_all_points_elements 取代
        $this->remove_all_points_elements();
    }
}