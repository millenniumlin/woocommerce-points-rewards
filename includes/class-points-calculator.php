<?php
/**
 * 點數計算類別
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 點數計算類別
 */
class WC_Points_Rewards_Points_Calculator {
    
    /**
     * 單例實例
     */
    private static $instance = null;
    
    /**
     * 設定選項
     */
    private $settings;
    
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
        // 修正：從個別選項載入設定
        $this->settings = array(
            'enable_points_system' => get_option('wc_points_rewards_enable_points_system', 'yes'),
            'points_per_amount' => get_option('wc_points_rewards_points_per_amount', '1'),
            'points_value' => get_option('wc_points_rewards_points_value', '1'),
            'points_expiry_months' => get_option('wc_points_rewards_points_expiry_months', '12'),
            'registration_points' => get_option('wc_points_rewards_registration_points', '100'),
            'birthday_points' => get_option('wc_points_rewards_birthday_points', '100'),
            'enable_cart_redemption' => get_option('wc_points_rewards_enable_cart_redemption', 'yes'),
            'min_cart_total' => get_option('wc_points_rewards_min_cart_total', '0'),
            'max_discount_percent' => get_option('wc_points_rewards_max_discount_percent', '100'),
            'enable_tiers' => get_option('wc_points_rewards_enable_tiers', 'yes'),
            'enable_notifications' => get_option('wc_points_rewards_enable_notifications', 'yes'),
        );
        $this->init_hooks();
    }
    
    /**
     * 初始化 hooks
     */
    private function init_hooks() {
        // 訂單完成時計算點數
        add_action('woocommerce_order_status_completed', array($this, 'calculate_order_points'));
        
        // 用戶註冊時贈送點數
        add_action('user_register', array($this, 'award_registration_points'));
        
        // 生日贈送點數（需要自定義觸發）
        add_action('wc_points_rewards_birthday_bonus', array($this, 'award_birthday_points'));
        
        // 每日檢查生日點數
        add_action('wc_points_rewards_daily_birthday_check', array($this, 'check_birthday_points'));
        
        // 生日設定時也檢查是否當天生日
        add_action('wc_points_rewards_birthday_set', array($this, 'check_immediate_birthday_bonus'));
        
        // 購物車中顯示可獲得的點數 - 移動到 shipping 之後，subtotal 下方
        add_action('woocommerce_cart_totals_after_shipping', array($this, 'display_cart_points_info'));
        
        // 產品頁面顯示可獲得的點數
        add_action('woocommerce_single_product_summary', array($this, 'display_product_points_info'), 25);
    }
    
    /**
     * 計算訂單點數
     */
    public function calculate_order_points($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }
        
        // 檢查是否已經計算過點數
        if (get_post_meta($order_id, '_points_awarded', true)) {
            return;
        }
        
        $order_total = $order->get_total();
        $used_points_amount = get_post_meta($order_id, '_points_discount_amount', true);
        
        // 計算可獲得點數的金額（扣除點數折抵的部分）
        $points_eligible_amount = $order_total - floatval($used_points_amount);
        
        // 基礎點數計算
        $base_points = $this->calculate_points_for_amount($points_eligible_amount);
        
        // 獲取用戶會員等級加成
        $tier_bonus = $this->get_user_tier_bonus($user_id);
        $bonus_points = $base_points * ($tier_bonus / 100);
        
        $total_points = $base_points + $bonus_points;
        
        // 記錄點數
        if ($total_points > 0) {
            $database = WC_Points_Rewards_Database::instance();
            
            // 計算過期時間
            $expiry_months = isset($this->settings['points_expiry_months']) ? intval($this->settings['points_expiry_months']) : 12;
            $expiry_date = date('Y-m-d H:i:s', strtotime("+{$expiry_months} months"));
            
            $description = sprintf(
                __('訂單 #%s 獲得點數（基礎: %s, 等級加成: %s%%）', 'wc-points-rewards'),
                $order->get_order_number(),
                $base_points,
                $tier_bonus
            );
            
            $database->add_points(
                $user_id,
                $total_points,
                'earned',
                $description,
                $order_id,
                $expiry_date
            );
            
            // 標記已發放點數
            update_post_meta($order_id, '_points_awarded', $total_points);
            
            // 更新年度消費統計
            $database->update_user_yearly_stats($user_id, $order_total);
            
            // 發送通知
            $this->send_points_notification($user_id, $total_points, $order);
        }
    }
    
    /**
     * 根據金額計算基礎點數
     */
    /**
     * 計算指定金額可獲得的點數 - 改進精度處理
     */
    public function calculate_points_for_amount($amount) {
        $amount = floatval($amount);
        if ($amount <= 0) {
            return 0;
        }
        
        $points_per_amount = isset($this->settings['points_per_amount']) ? floatval($this->settings['points_per_amount']) : 1;
        $decimal_places = wc_get_price_decimals(); // 使用 WooCommerce 小數位數設定
        
        if ($points_per_amount <= 0) {
            return 0;
        }
        
        // 使用更精確的計算方式，避免浮點數精度問題
        // 將金額轉換為分（或最小單位），進行整數運算，再轉回
        $scale = pow(10, $decimal_places);
        $amount_scaled = intval(round($amount * $scale));
        $points_per_amount_scaled = intval(round($points_per_amount * $scale));
        
        // 計算基礎點數：每消費 $points_per_amount 元獲得 1 點
        $points = $amount_scaled / $points_per_amount_scaled;
        
        // 確保結果在合理範圍內
        $points = min($points, 999999999.99);
        
        return round($points, $decimal_places);
    }
    
    /**
     * 獲取用戶會員等級加成百分比
     */
    public function get_user_tier_bonus($user_id) {
        $database = WC_Points_Rewards_Database::instance();
        $tier = $database->get_user_current_tier($user_id);
        
        return $tier ? floatval($tier->bonus_percentage) : 0;
    }
    
    /**
     * 註冊贈送點數
     */
    public function award_registration_points($user_id) {
        // 檢查註冊點數是否設定且大於0
        $points = isset($this->settings['registration_points']) ? floatval($this->settings['registration_points']) : 0;
        
        if ($points > 0) {
            $database = WC_Points_Rewards_Database::instance();
            
            // 計算過期時間
            $expiry_months = isset($this->settings['points_expiry_months']) ? intval($this->settings['points_expiry_months']) : 12;
            $expiry_date = null;
            if ($expiry_months > 0) {
                $expiry_date = date('Y-m-d H:i:s', strtotime("+{$expiry_months} months"));
            }
            
            $database->add_points(
                $user_id,
                $points,
                'earned',
                __('註冊贈送點數', 'wc-points-rewards'),
                null,
                $expiry_date
            );
        }
    }
    
    /**
     * 生日贈送點數
     * 修正：改為在生日月的第1天發放，並防止重複發放
     */
    public function award_birthday_points($user_id) {
        // 檢查生日點數是否設定且大於0
        $points = isset($this->settings['birthday_points']) ? floatval($this->settings['birthday_points']) : 0;
        
        if ($points > 0) {
            $database = WC_Points_Rewards_Database::instance();
            
            // 檢查當月是否已經發放過生日點數（以月和年為基準）
            $current_year = date('Y');
            $current_month = date('m');
            global $wpdb;
            $points_table = $wpdb->prefix . 'wc_points_rewards_points';
            
            $existing_birthday_points = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) 
                FROM `{$points_table}` 
                WHERE user_id = %d 
                AND type = %s 
                AND description = %s 
                AND YEAR(created_at) = %d 
                AND MONTH(created_at) = %d
            ", $user_id, 'earned', __('生日贈送點數', 'wc-points-rewards'), $current_year, $current_month));
            
            if (!$existing_birthday_points) {
                // 計算過期時間
                $expiry_months = isset($this->settings['points_expiry_months']) ? intval($this->settings['points_expiry_months']) : 12;
                $expiry_date = null;
                if ($expiry_months > 0) {
                    $expiry_date = date('Y-m-d H:i:s', strtotime("+{$expiry_months} months"));
                }
                
                $database->add_points(
                    $user_id,
                    $points,
                    'earned',
                    __('生日贈送點數', 'wc-points-rewards'),
                    null,
                    $expiry_date
                );
            }
        }
    }
    
    /**
     * 檢查並發放生日點數 - 每日執行的 cron job
     * 修正：處理外掛安裝後的生日點數發放邏輯
     */
    public function check_birthday_points() {
        // 檢查是否啟用生日點數功能
        $enable_birthday_points = isset($this->settings['enable_birthday_points']) ? $this->settings['enable_birthday_points'] : 'yes';
        if ($enable_birthday_points !== 'yes') {
            return;
        }
        
        $birthday_points = isset($this->settings['birthday_points']) ? floatval($this->settings['birthday_points']) : 0;
        
        if ($birthday_points <= 0) {
            return;
        }
        
        global $wpdb;
        $current_month = date('n'); // 1-12
        $current_year = date('Y');
        
        // 查找當月生日的用戶（只查詢有設定生日且已確認的用戶）
        $birthday_users = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT u1.user_id 
            FROM {$wpdb->usermeta} u1
            INNER JOIN {$wpdb->usermeta} u2 ON u1.user_id = u2.user_id
            WHERE u1.meta_key = 'birthday' 
            AND u1.meta_value != ''
            AND u1.meta_value IS NOT NULL
            AND u2.meta_key = 'birthday_set'
            AND u2.meta_value = '1'
            AND MONTH(STR_TO_DATE(u1.meta_value, '%%Y-%%m-%%d')) = %d
        ", $current_month));
        
        // 對每個生日用戶檢查是否已發放當年當月的生日點數
        foreach ($birthday_users as $user) {
            $this->award_birthday_points($user->user_id);
        }
    }
    
    /**
     * 檢查用戶設定生日時是否應立即發放生日點數
     * 修正：當用戶設定生日時，如果是當前生日月，立即檢查是否應發放點數
     */
    public function check_immediate_birthday_bonus($user_id) {
        $birthday = get_user_meta($user_id, 'birthday', true);
        
        if ($birthday) {
            $birthday_date = new DateTime($birthday);
            $today = new DateTime();
            
            // 檢查是否為生日月（只比較月份）
            if ($birthday_date->format('m') === $today->format('m')) {
                // 當用戶設定生日時，如果正好是生日月，立即發放點數
                $this->award_birthday_points($user_id);
            }
        }
    }
    
    /**
     * 在購物車顯示可獲得的點數
     */
    public function display_cart_points_info() {
        if (!is_user_logged_in()) {
            return;
        }
        
        // 檢查是否啟用點數系統
        if (!wc_points_rewards_is_enabled()) {
            return;
        }
        
        $cart_total = WC()->cart->get_subtotal();
        $points = $this->calculate_points_for_amount($cart_total);
        
        if ($points > 0) {
            $user_id = get_current_user_id();
            $tier_bonus = $this->get_user_tier_bonus($user_id);
            $bonus_points = $points * ($tier_bonus / 100);
            $total_points = $points + $bonus_points;
            
            echo '<tr class="points-info">';
            echo '<th>' . __('可獲得點數', 'wc-points-rewards') . '</th>';
            echo '<td>';
            echo sprintf(__('%s', 'wc-points-rewards'), wc_points_rewards_number_format($total_points));
            if ($tier_bonus > 0) {
                echo '<small> (' . sprintf(__('基礎 %s + 等級加成 %s%%', 'wc-points-rewards'), wc_points_rewards_number_format($points), $tier_bonus) . ')</small>';
            }
            echo '</td>';
            echo '</tr>';
        }
    }
    
    /**
     * 在產品頁面顯示可獲得的點數（已停用 - 不再使用）
     */
    public function display_product_points_info() {
        // 功能已完全移除 - 不再顯示任何產品點數資訊
        return;
    }
    
    /**
     * 發送點數獲得通知
     */
    private function send_points_notification($user_id, $points, $order) {
        // 這裡可以發送郵件或其他通知
        do_action('wc_points_rewards_points_earned_notification', $user_id, $points, $order);
    }
    
    /**
     * 計算點數折抵金額 - 改進精度處理
     */
    public function calculate_discount_amount($points) {
        $points = floatval($points);
        if ($points <= 0) {
            return 0;
        }
        
        // 使用設定中的點數價值：1點 = 多少元
        $point_value = isset($this->settings['points_value']) ? floatval($this->settings['points_value']) : 1;
        
        if ($point_value <= 0) {
            return 0;
        }
        
        // 使用更精確的計算，避免浮點數精度問題
        $decimal_places = wc_get_price_decimals();
        $scale = pow(10, $decimal_places);
        
        $points_scaled = intval(round($points * $scale));
        $point_value_scaled = intval(round($point_value * $scale));
        
        $discount_amount = ($points_scaled * $point_value_scaled) / ($scale * $scale);
        
        // 確保結果在合理範圍內
        $discount_amount = min($discount_amount, 999999999.99);
        
        return round($discount_amount, $decimal_places);
    }
    
    /**
     * 檢查點數是否可以使用 - 改進驗證邏輯
     */
    public function can_use_points($cart_total, $points_to_use) {
        $cart_total = floatval($cart_total);
        $points_to_use = floatval($points_to_use);
        
        // 基本驗證
        if ($cart_total <= 0 || $points_to_use <= 0) {
            return false;
        }
        
        // 檢查點數是否在合理範圍內
        if ($points_to_use > 999999999.99) {
            return false;
        }
        
        $min_cart_total = isset($this->settings['min_cart_total']) ? floatval($this->settings['min_cart_total']) : 0;
        $max_discount_percent = isset($this->settings['max_discount_percent']) ? floatval($this->settings['max_discount_percent']) : 100;
        
        // 確保百分比在合理範圍內
        $max_discount_percent = max(0, min($max_discount_percent, 100));
        
        // 檢查購物車金額是否達到最低要求
        if ($cart_total < $min_cart_total) {
            return false;
        }
        
        // 檢查折抵金額是否超過最大百分比
        $discount_amount = $this->calculate_discount_amount($points_to_use);
        $max_discount_amount = ($cart_total * $max_discount_percent) / 100;
        
        return $discount_amount <= $max_discount_amount;
    }
    
    /**
     * 強制檢查點數使用 - 用於管理員覆蓋或特殊情況
     */
    public function can_force_use_points($cart_total, $points_to_use, $user_id = 0) {
        // 如果是管理員且設置允許覆蓋，則允許使用
        if ($user_id && current_user_can('manage_woocommerce')) {
            $allow_admin_override = isset($this->settings['allow_admin_override']) && $this->settings['allow_admin_override'] === 'yes';
            if ($allow_admin_override) {
                return true;
            }
        }
        
        // 否則使用正常檢查
        return $this->can_use_points($cart_total, $points_to_use);
    }
}