<?php
/**
 * 報表類別
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 報表類別
 */
class WC_Points_Rewards_Reports {
    
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
        // 不需要特別的 hooks
    }
    
    /**
     * 獲取點數統計概覽
     */
    public function get_points_overview($start_date = null, $end_date = null) {
        global $wpdb;
        
        if (!$start_date) {
            $start_date = date('Y-m-01'); // 本月第一天
        }
        if (!$end_date) {
            $end_date = date('Y-m-t'); // 本月最後一天
        }
        
        $points_table = $wpdb->prefix . 'wc_points_rewards_points';
        
        // 總發放點數
        $total_earned = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(points) 
            FROM $points_table 
            WHERE type = 'earned' 
            AND created_at BETWEEN %s AND %s
        ", $start_date, $end_date . ' 23:59:59'));
        
        // 總使用點數
        $total_redeemed = wc_points_rewards_abs($wpdb->get_var($wpdb->prepare("
            SELECT SUM(points) 
            FROM $points_table 
            WHERE type = 'redeemed' 
            AND created_at BETWEEN %s AND %s
        ", $start_date, $end_date . ' 23:59:59')));
        
        // 過期點數
        $total_expired = wc_points_rewards_abs($wpdb->get_var($wpdb->prepare("
            SELECT SUM(points) 
            FROM $points_table 
            WHERE type = 'expired' 
            AND created_at BETWEEN %s AND %s
        ", $start_date, $end_date . ' 23:59:59')));
        
        // 目前有效點數總額
        $total_active = $wpdb->get_var("
            SELECT SUM(points) 
            FROM $points_table 
            WHERE type = 'earned' 
            AND (expiry_date IS NULL OR expiry_date > NOW())
        ");
        
        return array(
            'total_earned' => floatval($total_earned),
            'total_redeemed' => floatval($total_redeemed),
            'total_expired' => floatval($total_expired),
            'total_active' => floatval($total_active)
        );
    }
    
    /**
     * 獲取會員等級分布
     */
    public function get_tier_distribution() {
        global $wpdb;
        
        $tiers_table = $wpdb->prefix . 'wc_points_rewards_tiers';
        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        
        $distribution = $wpdb->get_results("
            SELECT 
                t.name as tier_name,
                COUNT(s.user_id) as user_count,
                t.tier_order
            FROM $tiers_table t
            LEFT JOIN $stats_table s ON t.id = s.current_tier_id 
                AND s.year = YEAR(NOW())
                AND (s.tier_expiry_date IS NULL OR s.tier_expiry_date > NOW())
            GROUP BY t.id, t.name, t.tier_order
            ORDER BY t.tier_order ASC
        ");
        
        return $distribution;
    }
    
    /**
     * 獲取點數獲得趨勢
     */
    public function get_points_earning_trend($days = 30) {
        global $wpdb;
        
        $points_table = $wpdb->prefix . 'wc_points_rewards_points';
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $trend_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(created_at) as date,
                SUM(CASE WHEN type = 'earned' THEN points ELSE 0 END) as earned,
                ABS(SUM(CASE WHEN type = 'redeemed' THEN points ELSE 0 END)) as redeemed
            FROM $points_table 
            WHERE created_at >= %s
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", $start_date));
        
        return $trend_data;
    }
    
    /**
     * 獲取最活躍用戶
     */
    public function get_top_users($limit = 10, $period = 'month') {
        global $wpdb;
        
        $points_table = $wpdb->prefix . 'wc_points_rewards_points';
        
        switch ($period) {
            case 'week':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'year':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
            default:
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
        
        $top_users = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.user_id,
                u.display_name,
                u.user_email,
                SUM(CASE WHEN p.type = 'earned' THEN p.points ELSE 0 END) as total_earned,
                ABS(SUM(CASE WHEN p.type = 'redeemed' THEN p.points ELSE 0 END)) as total_redeemed
            FROM $points_table p
            LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
            WHERE 1=1 $date_condition
            GROUP BY p.user_id, u.display_name, u.user_email
            ORDER BY total_earned DESC
            LIMIT %d
        ", $limit));
        
        return $top_users;
    }
    
    /**
     * 獲取點數使用分析
     */
    public function get_redemption_analysis($period = 'month') {
        global $wpdb;
        
        $points_table = $wpdb->prefix . 'wc_points_rewards_points';
        
        switch ($period) {
            case 'week':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'year':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
            default:
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
        
        // 使用點數的統計
        $redemption_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(*) as total_redemptions,
                ABS(SUM(points)) as total_points_redeemed,
                ABS(AVG(points)) as avg_redemption_amount
            FROM $points_table 
            WHERE type = 'redeemed' $date_condition
        "));
        
        return $redemption_stats;
    }
    
    /**
     * 獲取會員等級升級記錄
     */
    public function get_tier_upgrade_history($limit = 50) {
        global $wpdb;
        
        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $tiers_table = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        $upgrades = $wpdb->get_results($wpdb->prepare("
            SELECT 
                s.user_id,
                u.display_name,
                t.name as tier_name,
                s.tier_start_date,
                s.total_spent
            FROM $stats_table s
            LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
            LEFT JOIN $tiers_table t ON s.current_tier_id = t.id
            WHERE s.tier_start_date IS NOT NULL
            ORDER BY s.tier_start_date DESC
            LIMIT %d
        ", $limit));
        
        return $upgrades;
    }
    
    /**
     * 獲取點數即將到期的統計
     */
    public function get_expiring_points_stats() {
        global $wpdb;
        
        $points_table = $wpdb->prefix . 'wc_points_rewards_points';
        
        // 30天內到期的點數
        $expiring_30_days = $wpdb->get_var("
            SELECT SUM(points) 
            FROM $points_table 
            WHERE type = 'earned' 
            AND expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
        ");
        
        // 7天內到期的點數
        $expiring_7_days = $wpdb->get_var("
            SELECT SUM(points) 
            FROM $points_table 
            WHERE type = 'earned' 
            AND expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        ");
        
        // 已過期但未標記的點數
        $expired_unmarked = $wpdb->get_var("
            SELECT SUM(points) 
            FROM $points_table 
            WHERE type = 'earned' 
            AND expiry_date IS NOT NULL 
            AND expiry_date < NOW()
        ");
        
        return array(
            'expiring_30_days' => floatval($expiring_30_days),
            'expiring_7_days' => floatval($expiring_7_days),
            'expired_unmarked' => floatval($expired_unmarked)
        );
    }
    
    /**
     * 獲取收入影響分析
     */
    public function get_revenue_impact($period = 'month') {
        global $wpdb;
        
        switch ($period) {
            case 'week':
                $date_condition = "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'year':
                $date_condition = "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
            default:
                $date_condition = "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
        
        // 計算點數折抵對收入的影響
        $impact = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(p.ID) as orders_with_points,
                SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total_points_discount,
                AVG(CAST(pm.meta_value AS DECIMAL(10,2))) as avg_points_discount
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND pm.meta_key = '_points_discount_amount'
            AND pm.meta_value > 0
            $date_condition
        "));
        
        return $impact;
    }
    
    /**
     * 匯出報表為 CSV
     */
    public function export_csv($report_type, $data) {
        $filename = 'points_rewards_' . $report_type . '_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        switch ($report_type) {
            case 'points_history':
                fputcsv($output, array('用戶', '點數', '類型', '描述', '日期'));
                foreach ($data as $record) {
                    fputcsv($output, array(
                        $record->display_name,
                        $record->points,
                        $record->type,
                        $record->description,
                        $record->created_at
                    ));
                }
                break;
                
            case 'tier_distribution':
                fputcsv($output, array('會員等級', '用戶數量'));
                foreach ($data as $tier) {
                    fputcsv($output, array(
                        $tier->tier_name,
                        $tier->user_count
                    ));
                }
                break;
                
            case 'top_users':
                fputcsv($output, array('用戶名稱', '郵箱', '獲得點數', '使用點數'));
                foreach ($data as $user) {
                    fputcsv($output, array(
                        $user->display_name,
                        $user->user_email,
                        $user->total_earned,
                        $user->total_redeemed
                    ));
                }
                break;
        }
        
        fclose($output);
        exit;
    }
}