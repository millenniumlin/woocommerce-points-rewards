<?php
/**
 * 通知系統類別
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 通知系統類別
 */
class WC_Points_Rewards_Notifications {
    
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
            'enable_notifications' => get_option('wc_points_rewards_enable_notifications', 'yes'),
            'expiry_notification_days' => get_option('wc_points_rewards_expiry_notification_days', '7'),
        );
        $this->init_hooks();
    }
    
    /**
     * 初始化 hooks
     */
    private function init_hooks() {
        // 每日檢查通知
        add_action('wc_points_rewards_notification_check', array($this, 'check_notifications'));
        
        // 點數到期通知
        add_action('wc_points_rewards_points_expiry_warning', array($this, 'send_points_expiry_notification'), 10, 2);
        
        // 會員等級到期通知
        add_action('wc_points_rewards_tier_expiry_warning', array($this, 'send_tier_expiry_notification'), 10, 2);
    }
    
    /**
     * 檢查所有通知
     */
    public function check_notifications() {
        if (!isset($this->settings['enable_notifications']) || $this->settings['enable_notifications'] !== 'yes') {
            return;
        }
        
        $this->check_points_expiry_notifications();
        $this->check_tier_expiry_notifications();
    }
    
    /**
     * 檢查點數到期通知
     */
    private function check_points_expiry_notifications() {
        global $wpdb;
        
        $notification_days = isset($this->settings['notification_days']) ? intval($this->settings['notification_days']) : 30;
        $points_table = $wpdb->prefix . 'wc_points_rewards_points';
        
        // 獲取即將到期的點數
        $expiring_points = $wpdb->get_results($wpdb->prepare("
            SELECT user_id, SUM(points) as total_points, expiry_date
            FROM $points_table 
            WHERE type = 'earned' 
            AND expiry_date IS NOT NULL 
            AND expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL %d DAY)
            GROUP BY user_id, DATE(expiry_date)
            HAVING total_points > 0
        ", $notification_days));
        
        foreach ($expiring_points as $record) {
            // 檢查是否已經發送過通知
            $notification_key = 'points_expiry_' . $record->user_id . '_' . date('Y-m-d', strtotime($record->expiry_date));
            
            if (!get_transient($notification_key)) {
                $this->send_points_expiry_notification($record->user_id, $record);
                
                // 設定 24 小時內不重複發送
                set_transient($notification_key, true, DAY_IN_SECONDS);
            }
        }
    }
    
    /**
     * 檢查會員等級到期通知
     */
    private function check_tier_expiry_notifications() {
        global $wpdb;
        
        $notification_days = isset($this->settings['notification_days']) ? intval($this->settings['notification_days']) : 30;
        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $tiers_table = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        // 獲取即將到期的會員等級
        $expiring_tiers = $wpdb->get_results($wpdb->prepare("
            SELECT s.user_id, s.tier_expiry_date, t.name as tier_name
            FROM $stats_table s
            INNER JOIN $tiers_table t ON s.current_tier_id = t.id
            WHERE s.tier_expiry_date IS NOT NULL 
            AND s.tier_expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL %d DAY)
        ", $notification_days));
        
        foreach ($expiring_tiers as $record) {
            // 檢查是否已經發送過通知
            $notification_key = 'tier_expiry_' . $record->user_id . '_' . date('Y-m-d', strtotime($record->tier_expiry_date));
            
            if (!get_transient($notification_key)) {
                $this->send_tier_expiry_notification($record->user_id, $record);
                
                // 設定 24 小時內不重複發送
                set_transient($notification_key, true, DAY_IN_SECONDS);
            }
        }
    }
    
    /**
     * 發送點數到期通知
     */
    public function send_points_expiry_notification($user_id, $points_data) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $expiry_date = date('Y-m-d', strtotime($points_data->expiry_date));
        
        $subject = __('點數即將到期提醒', 'wc-points-rewards');
        
        $message = sprintf(
            __('親愛的 %s，<br><br>您有 %s 點即將於 %s 到期。<br>請盡快使用，避免點數失效。<br><br>立即購物：%s', 'wc-points-rewards'),
            $user->display_name,
            wc_points_rewards_number_format($points_data->total_points),
            $expiry_date,
            wc_get_page_permalink('shop')
        );
        
        // 發送郵件
        $this->send_email($user->user_email, $subject, $message);
        
        // 觸發自定義動作
        do_action('wc_points_rewards_points_expiry_notification_sent', $user_id, $points_data);
    }
    
    /**
     * 發送會員等級到期通知
     */
    public function send_tier_expiry_notification($user_id, $tier_data) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $expiry_date = date('Y-m-d', strtotime($tier_data->tier_expiry_date));
        
        $subject = sprintf(__('%s 等級即將到期', 'wc-points-rewards'), $tier_data->tier_name);
        
        $message = sprintf(
            __('親愛的 %s，<br><br>您的 %s 等級將於 %s 到期。<br>請繼續購物以維持會員等級資格。<br><br>立即購物：%s', 'wc-points-rewards'),
            $user->display_name,
            $tier_data->tier_name,
            $expiry_date,
            wc_get_page_permalink('shop')
        );
        
        // 發送郵件
        $this->send_email($user->user_email, $subject, $message);
        
        // 觸發自定義動作
        do_action('wc_points_rewards_tier_expiry_notification_sent', $user_id, $tier_data);
    }
    
    /**
     * 發送郵件的統一方法
     */
    private function send_email($to, $subject, $message) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
        );
        
        // 使用 WooCommerce 郵件模板
        if (class_exists('WC_Email')) {
            $mailer = WC()->mailer();
            $wrapped_message = $mailer->wrap_message($subject, $message);
            wp_mail($to, $subject, $wrapped_message, $headers);
        } else {
            wp_mail($to, $subject, $message, $headers);
        }
    }
    
    /**
     * 發送點數獲得通知
     */
    public function send_points_earned_notification($user_id, $points, $order = null) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $subject = __('您獲得了新的點數！', 'wc-points-rewards');
        
        if ($order) {
            $message = sprintf(
                __('親愛的 %s，<br><br>感謝您的購買！您的訂單 #%s 已獲得 %s 點。<br><br>查看我的帳戶：%s', 'wc-points-rewards'),
                $user->display_name,
                $order->get_order_number(),
                wc_points_rewards_number_format($points),
                wc_get_page_permalink('myaccount')
            );
        } else {
            $message = sprintf(
                __('親愛的 %s，<br><br>您獲得了 %s 點！<br><br>查看我的帳戶：%s', 'wc-points-rewards'),
                $user->display_name,
                wc_points_rewards_number_format($points),
                wc_get_page_permalink('myaccount')
            );
        }
        
        // 發送郵件
        $this->send_email($user->user_email, $subject, $message);
    }
    
    /**
     * 發送歡迎郵件（註冊贈送點數）
     */
    public function send_welcome_points_notification($user_id, $points) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $subject = __('歡迎加入！您獲得了註冊贈送點數', 'wc-points-rewards');
        
        $message = sprintf(
            __('親愛的 %s，<br><br>歡迎加入我們！<br>作為新會員，您獲得了 %s 點作為歡迎禮。<br><br>立即開始購物：%s', 'wc-points-rewards'),
            $user->display_name,
            wc_points_rewards_number_format($points),
            wc_get_page_permalink('shop')
        );
        
        // 發送郵件
        $this->send_email($user->user_email, $subject, $message);
    }
    
    /**
     * 手動發送通知（管理員功能）
     */
    public function send_custom_notification($user_ids, $subject, $message) {
        if (!is_array($user_ids)) {
            $user_ids = array($user_ids);
        }
        
        foreach ($user_ids as $user_id) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                $this->send_email($user->user_email, $subject, $message);
            }
        }
        
        return count($user_ids);
    }
}