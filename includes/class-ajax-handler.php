<?php
/**
 * AJAX 處理器
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX 處理器類別
 */
class WC_Points_Rewards_Ajax_Handler {
    
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
        add_action('wp_ajax_wc_points_rewards_process_historical_orders', array($this, 'process_historical_orders'));
    }
    
    /**
     * 處理歷史訂單點數補發
     */
    public function process_historical_orders() {
        // 檢查權限
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('權限不足', 'wc-points-rewards'));
        }
        
        // 檢查 nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_points_rewards_historical_orders')) {
            wp_send_json_error(__('安全驗證失敗', 'wc-points-rewards'));
        }
        
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';
        
        if (!$start_date || !$end_date) {
            wp_send_json_error(__('請提供有效的日期範圍', 'wc-points-rewards'));
        }
        
        // 驗證日期格式
        if (!strtotime($start_date) || !strtotime($end_date)) {
            wp_send_json_error(__('日期格式不正確', 'wc-points-rewards'));
        }
        
        // 獲取指定日期範圍內的已完成訂單
        $orders = wc_get_orders(array(
            'status' => 'completed',
            'date_created' => $start_date . '...' . $end_date,
            'limit' => -1,
        ));
        
        $processed_count = 0;
        $skipped_count = 0;
        $total_points_awarded = 0;
        $eligible_orders = array();
        
        // 檢查是否有 Points Calculator 類別
        if (!class_exists('WC_Points_Rewards_Points_Calculator') || !class_exists('WC_Points_Rewards_Database')) {
            wp_send_json_error(__('點數系統類別未正確載入', 'wc-points-rewards'));
        }
        
        foreach ($orders as $order) {
            // 檢查是否已經處理過
            if (get_post_meta($order->get_id(), '_points_awarded', true)) {
                $skipped_count++;
                continue;
            }
            
            $user_id = $order->get_user_id();
            if (!$user_id) {
                continue; // 跳過訪客訂單
            }
            
            // 計算點數
            $calculator = WC_Points_Rewards_Points_Calculator::instance();
            $points = $calculator->calculate_points_for_amount($order->get_total());
            
            // 獲取用戶等級加成
            if (method_exists($calculator, 'get_user_tier_bonus')) {
                $tier_bonus = $calculator->get_user_tier_bonus($user_id);
                $bonus_points = $points * ($tier_bonus / 100);
                $points += $bonus_points;
            }
            
            if ($points > 0) {
                $eligible_orders[] = array(
                    'order_id' => $order->get_id(),
                    'order_number' => $order->get_order_number(),
                    'user_id' => $user_id,
                    'user_name' => get_user_meta($user_id, 'first_name', true) . ' ' . get_user_meta($user_id, 'last_name', true),
                    'user_email' => get_userdata($user_id)->user_email,
                    'order_total' => $order->get_total(),
                    'points' => $points,
                    'order_date' => $order->get_date_created()->format('Y-m-d H:i:s')
                );
                
                if (!$dry_run) {
                    // 實際發放點數
                    $database = WC_Points_Rewards_Database::instance();
                    
                    // 計算過期時間
                    $settings = get_option('wc_points_rewards_settings', array());
                    $expiry_months = isset($settings['points_expiry_months']) ? intval($settings['points_expiry_months']) : 12;
                    $expiry_date = null;
                    if ($expiry_months > 0) {
                        $expiry_date = date('Y-m-d H:i:s', strtotime("+{$expiry_months} months"));
                    }
                    
                    $success = $database->add_points(
                        $user_id,
                        $points,
                        'earned',
                        sprintf(__('訂單 #%s 補發點數', 'wc-points-rewards'), $order->get_order_number()),
                        $order->get_id(),
                        $expiry_date
                    );
                    
                    if ($success) {
                        // 標記訂單已處理
                        update_post_meta($order->get_id(), '_points_awarded', $points);
                        
                        // 更新年度消費統計
                        if (method_exists($database, 'update_user_yearly_stats')) {
                            $database->update_user_yearly_stats($user_id, $order->get_total());
                        }
                        
                        $processed_count++;
                        $total_points_awarded += $points;
                    }
                } else {
                    // 測試模式：只統計
                    $processed_count++;
                    $total_points_awarded += $points;
                }
            }
        }
        
        if ($dry_run) {
            $message = sprintf(
                __('測試完成！找到 %d 筆符合條件的訂單，跳過 %d 筆已處理訂單。<br>預計將發放總點數：%s', 'wc-points-rewards'),
                $processed_count,
                $skipped_count,
                wc_points_rewards_number_format($total_points_awarded)
            );
            
            // 如果訂單不多，也可以顯示詳細列表
            if (count($eligible_orders) <= 20) {
                $message .= '<br><br><strong>' . __('符合條件的訂單：', 'wc-points-rewards') . '</strong><br>';
                $message .= '<table class="wp-list-table widefat" style="margin-top: 10px;"><thead><tr>';
                $message .= '<th>' . __('訂單', 'wc-points-rewards') . '</th>';
                $message .= '<th>' . __('用戶', 'wc-points-rewards') . '</th>';
                $message .= '<th>' . __('金額', 'wc-points-rewards') . '</th>';
                $message .= '<th>' . __('預計點數', 'wc-points-rewards') . '</th>';
                $message .= '</tr></thead><tbody>';
                
                foreach ($eligible_orders as $order_data) {
                    $message .= '<tr>';
                    $message .= '<td>#' . esc_html($order_data['order_number']) . '</td>';
                    $message .= '<td>' . esc_html($order_data['user_name']) . '<br><small>' . esc_html($order_data['user_email']) . '</small></td>';
                    $message .= '<td>' . wc_price($order_data['order_total']) . '</td>';
                    $message .= '<td>' . wc_points_rewards_number_format($order_data['points']) . '</td>';
                    $message .= '</tr>';
                }
                $message .= '</tbody></table>';
            }
        } else {
            $message = sprintf(
                __('處理完成！共處理 %d 筆訂單，跳過 %d 筆已處理訂單，總共發放 %s 點數。', 'wc-points-rewards'),
                $processed_count,
                $skipped_count,
                wc_points_rewards_number_format($total_points_awarded)
            );
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'processed_count' => $processed_count,
            'skipped_count' => $skipped_count,
            'total_points' => $total_points_awarded,
            'eligible_orders' => $eligible_orders
        ));
    }
}