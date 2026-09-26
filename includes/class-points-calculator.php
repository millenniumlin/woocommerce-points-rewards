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
        $this->settings = array(
            'enable_points_system'  => get_option('wc_points_rewards_enable_points_system', 'yes'),
            'points_per_amount'     => get_option('wc_points_rewards_points_per_amount', '1'),
            'points_value'          => get_option('wc_points_rewards_points_value', '1'),
            'points_expiry_months'  => get_option('wc_points_rewards_points_expiry_months', '12'),
            'registration_points'   => get_option('wc_points_rewards_registration_points', '100'),
            'birthday_points'       => get_option('wc_points_rewards_birthday_points', '100'),
            'enable_cart_redemption' => get_option('wc_points_rewards_enable_cart_redemption', 'yes'),
            'min_cart_total'        => get_option('wc_points_rewards_min_cart_total', '0'),
            'max_discount_percent'  => get_option('wc_points_rewards_max_discount_percent', '100'),
            'enable_tiers'          => get_option('wc_points_rewards_enable_tiers', 'yes'),
            'enable_notifications'  => get_option('wc_points_rewards_enable_notifications', 'yes'),
        );
        $this->init_hooks();
    }

    /**
     * 初始化 hooks
     */
    private function init_hooks() {
        add_action('woocommerce_order_status_completed', array($this, 'calculate_order_points'));
        add_action('woocommerce_order_status_refunded', array($this, 'handle_order_refund_or_cancellation'));
        add_action('woocommerce_order_status_cancelled', array($this, 'handle_order_refund_or_cancellation'));
        add_action('woocommerce_order_status_failed', array($this, 'handle_order_refund_or_cancellation'));
        add_action('user_register', array($this, 'award_registration_points'));
        add_action('wc_points_rewards_birthday_bonus', array($this, 'award_birthday_points'));
        add_action('wc_points_rewards_daily_birthday_check', array($this, 'check_birthday_points'));
        add_action('wc_points_rewards_birthday_set', array($this, 'check_immediate_birthday_bonus'));
        add_action('woocommerce_cart_totals_after_shipping', array($this, 'display_cart_points_info'));
        add_action('woocommerce_single_product_summary', array($this, 'display_product_points_info'), 25);
    }

    /**
     * 計算訂單點數
     *
     * [修正 L5-HPOS] 改用 HPOS 安全的 $order->get_meta() / $order->update_meta_data() API
     * 取代 get_post_meta() / update_post_meta()，確保 HPOS 模式下資料正確讀寫
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

        // [修正 L5-HPOS] 使用 HPOS-safe $order->get_meta()
        if ($order->get_meta('_points_awarded')) {
            return;
        }

        $order_total          = $order->get_total();
        $used_points_amount   = floatval($order->get_meta('_points_discount_amount'));
        $points_eligible_amount = $order_total - $used_points_amount;

        $base_points   = $this->calculate_points_for_amount($points_eligible_amount);
        $tier_bonus    = $this->get_user_tier_bonus($user_id);
        $bonus_points  = $base_points * ($tier_bonus / 100);
        $total_points  = $base_points + $bonus_points;

        if ($total_points > 0) {
            $database    = WC_Points_Rewards_Database::instance();
            $expiry_date = wc_points_rewards_calculate_points_expiry_date();

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

            // [修正 L5-HPOS] 使用 HPOS-safe update_meta_data + save
            $order->update_meta_data('_points_awarded', $total_points);
            $order->save();

            $actual_paid_amount = $order_total - $used_points_amount;
            $database->update_user_yearly_stats($user_id, $actual_paid_amount);

            $this->send_points_notification($user_id, $total_points, $order);
        }
    }

    /**
     * 處理訂單退款或取消：退回已扣除點數，收回已發放點數，並扣除統計金額
     */
    public function handle_order_refund_or_cancellation($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }

        $database = WC_Points_Rewards_Database::instance();
        $changed = false;

        // 1. 退還客人使用的點數 (取消/退款)
        $used_points = floatval($order->get_meta('_points_used'));
        $refunded_used = $order->get_meta('_points_used_refunded');
        if ($used_points > 0 && !$refunded_used) {
            $database->add_points(
                $user_id,
                $used_points,
                'admin',
                sprintf(__('訂單 #%s 退款/取消，退回使用的點數', 'wc-points-rewards'), $order->get_order_number()),
                $order_id
            );
            $order->update_meta_data('_points_used_refunded', 'yes');
            $changed = true;
        }

        // 2. 扣回因為此訂單獲得的點數 (取消/退款)
        $awarded_points = floatval($order->get_meta('_points_awarded'));
        $reversed_awarded = $order->get_meta('_points_awarded_reversed');
        if ($awarded_points > 0 && !$reversed_awarded) {
            $database->deduct_points_with_lock(
                $user_id,
                $awarded_points,
                'admin',
                sprintf(__('訂單 #%s 退款/取消，收回發放的點數', 'wc-points-rewards'), $order->get_order_number()),
                $order_id
            );
            $order->update_meta_data('_points_awarded_reversed', 'yes');
            $changed = true;
            
            // 同時扣除會員年度消費統計 (因為訂單被取消了)
            $order_total = $order->get_total();
            $used_points_amount = floatval($order->get_meta('_points_discount_amount'));
            $actual_paid_amount = $order_total - $used_points_amount;
            
            // 傳入負值來扣除
            $database->update_user_yearly_stats($user_id, -$actual_paid_amount);
        }

        if ($changed) {
            $order->save();
        }
    }

    /**
     * 計算指定金額可獲得的點數 - 改進精度處理
     */
    public function calculate_points_for_amount($amount) {
        $amount = floatval($amount);
        if ($amount <= 0) {
            return 0;
        }

        $points_per_amount = isset($this->settings['points_per_amount']) ? floatval($this->settings['points_per_amount']) : 1;
        $decimal_places    = wc_get_price_decimals();

        if ($points_per_amount <= 0) {
            return 0;
        }

        $scale                    = pow(10, $decimal_places);
        $amount_scaled            = intval(round($amount * $scale));
        $points_per_amount_scaled = intval(round($points_per_amount * $scale));

        $points = $amount_scaled / $points_per_amount_scaled;
        $points = min($points, 99999999.99);

        return round($points, $decimal_places);
    }

    /**
     * 獲取用戶會員等級加成百分比
     */
    public function get_user_tier_bonus($user_id) {
        $database = WC_Points_Rewards_Database::instance();
        $tier     = $database->get_user_current_tier($user_id);

        return $tier ? floatval($tier->bonus_percentage) : 0;
    }

    /**
     * 註冊贈送點數
     */
    public function award_registration_points($user_id) {
        $points = isset($this->settings['registration_points']) ? floatval($this->settings['registration_points']) : 0;

        if ($points > 0) {
            $database    = WC_Points_Rewards_Database::instance();
            $expiry_date = wc_points_rewards_calculate_points_expiry_date();

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
     *
     * [修正 LOGIC-1] 原本用可翻譯字串 __('生日贈送點數') 來做重複發放防護。
     * 問題：若語系切換，舊記錄的 description 與查詢條件不符，導致重複發放。
     * 修正：改用 type = 'birthday' 做去重判斷，不依賴可翻譯字串。
     */
    public function award_birthday_points($user_id) {
        $points = isset($this->settings['birthday_points']) ? floatval($this->settings['birthday_points']) : 0;

        if ($points <= 0) {
            return;
        }

        $database = WC_Points_Rewards_Database::instance();

        // [修正 LOGIC-1] 以 type = 'birthday' + 年月查詢，取代 description 字串比對
        $current_year  = intval(wc_points_rewards_get_site_datetime()->format('Y'));
        $current_month = intval(wc_points_rewards_get_site_datetime()->format('m'));

        global $wpdb;
        $points_table = $wpdb->prefix . 'wc_points_rewards_points';

        $already_awarded = (bool) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM `{$points_table}`
            WHERE user_id = %d
            AND type = %s
            AND YEAR(created_at) = %d
            AND MONTH(created_at) = %d
        ", $user_id, 'birthday', $current_year, $current_month));

        if (!$already_awarded) {
            $expiry_date = wc_points_rewards_calculate_points_expiry_date();

            $database->add_points(
                $user_id,
                $points,
                'birthday',  // [修正 LOGIC-1] 使用固定 type 而非 'earned' + 翻譯字串
                __('生日贈送點數', 'wc-points-rewards'),
                null,
                $expiry_date
            );

            $notifications = WC_Points_Rewards_Notifications::instance();
            $notifications->send_birthday_points_notification($user_id, $points);

            do_action('wc_points_rewards_birthday_points_awarded', $user_id, $points);
        }
    }

    /**
     * 檢查並發放生日點數 - 每日執行的 cron job
     */
    public function check_birthday_points() {
        $enable_birthday_points = isset($this->settings['enable_birthday_points']) ? $this->settings['enable_birthday_points'] : 'yes';
        if ($enable_birthday_points !== 'yes') {
            return;
        }

        $birthday_points = isset($this->settings['birthday_points']) ? floatval($this->settings['birthday_points']) : 0;

        if ($birthday_points <= 0) {
            return;
        }

        global $wpdb;
        $current_month = intval(wc_points_rewards_get_site_datetime()->format('n'));

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

        foreach ($birthday_users as $user) {
            $this->award_birthday_points($user->user_id);
        }
    }

    /**
     * 當用戶設定生日時，若恰好在生日月份，立即發放生日點數
     *
     * [修正 L9-check_immediate] 使用 DateTime::createFromFormat 取代 new DateTime
     * 避免格式不對時拋出例外（例如 '9999-99-99'）
     */
    public function check_immediate_birthday_bonus($user_id) {
        $birthday = get_user_meta($user_id, 'birthday', true);

        if (empty($birthday)) {
            return;
        }

        // [修正 L9] 使用 createFromFormat + false 檢查，不會拋出例外
        $birthday_date = DateTime::createFromFormat('Y-m-d', $birthday);
        if (!$birthday_date) {
            return; // 日期格式無效，不處理
        }

        $today = wc_points_rewards_get_site_datetime();

        if ($birthday_date->format('m') === $today->format('m')) {
            $this->award_birthday_points($user_id);
        }
    }

    /**
     * 在購物車顯示可獲得的點數
     */
    public function display_cart_points_info() {
        if (!is_user_logged_in()) {
            return;
        }

        if (!wc_points_rewards_is_enabled()) {
            return;
        }

        $cart_total  = WC()->cart->get_subtotal();
        $points      = $this->calculate_points_for_amount($cart_total);

        if ($points > 0) {
            $user_id      = get_current_user_id();
            $tier_bonus   = $this->get_user_tier_bonus($user_id);
            $bonus_points = $points * ($tier_bonus / 100);
            $total_points = $points + $bonus_points;

            echo '<tr class="points-info">';
            echo '<th>' . esc_html__('可獲得點數', 'wc-points-rewards') . '</th>';
            echo '<td>';
            echo esc_html(wc_points_rewards_number_format($total_points));
            if ($tier_bonus > 0) {
                echo '<small> (' . sprintf(
                    esc_html__('基礎 %s + 等級加成 %s%%', 'wc-points-rewards'),
                    esc_html(wc_points_rewards_number_format($points)),
                    esc_html($tier_bonus)
                ) . ')</small>';
            }
            echo '</td>';
            echo '</tr>';
        }
    }

    /**
     * 在產品頁面顯示可獲得的點數（已停用）
     */
    public function display_product_points_info() {
        // 功能已完全移除 - 不再顯示任何產品點數資訊
        return;
    }

    /**
     * 發送點數獲得通知
     */
    private function send_points_notification($user_id, $points, $order) {
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

        $point_value = wc_points_rewards_get_points_value();

        if ($point_value <= 0) {
            return 0;
        }

        $decimal_places   = wc_get_price_decimals();
        $scale            = pow(10, $decimal_places);

        $points_scaled      = intval(round($points * $scale));
        $point_value_scaled = intval(round($point_value * $scale));

        $discount_amount = ($points_scaled * $point_value_scaled) / ($scale * $scale);
        $discount_amount = min($discount_amount, 99999999.99);

        return round($discount_amount, $decimal_places);
    }

    /**
     * 檢查點數是否可以使用 - 改進驗證邏輯
     */
    public function can_use_points($cart_total, $points_to_use) {
        $cart_total    = floatval($cart_total);
        $points_to_use = floatval($points_to_use);

        if ($cart_total <= 0 || $points_to_use <= 0) {
            return false;
        }

        if ($points_to_use > 99999999.99) {
            return false;
        }

        $min_cart_total     = isset($this->settings['min_cart_total']) ? floatval($this->settings['min_cart_total']) : 0;
        $max_discount_percent = isset($this->settings['max_discount_percent']) ? floatval($this->settings['max_discount_percent']) : 100;
        $max_discount_percent = max(0, min($max_discount_percent, 100));

        if ($cart_total < $min_cart_total) {
            return false;
        }

        $discount_amount    = $this->calculate_discount_amount($points_to_use);
        $max_discount_amount = ($cart_total * $max_discount_percent) / 100;

        return $discount_amount <= $max_discount_amount;
    }

    /**
     * 強制檢查點數使用 - 用於管理員覆蓋或特殊情況
     */
    public function can_force_use_points($cart_total, $points_to_use, $user_id = 0) {
        if ($user_id && current_user_can('manage_woocommerce')) {
            $allow_admin_override = isset($this->settings['allow_admin_override']) && $this->settings['allow_admin_override'] === 'yes';
            if ($allow_admin_override) {
                return true;
            }
        }

        return $this->can_use_points($cart_total, $points_to_use);
    }
}
