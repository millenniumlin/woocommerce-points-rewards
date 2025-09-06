<?php
/**
 * 會員等級管理類別
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 會員等級管理類別
 */
class WC_Points_Rewards_Member_Tier {
    
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
        // 每日檢查會員等級過期
        add_action('wc_points_rewards_daily_cleanup', array($this, 'check_tier_expiry'));
        
        // 會員等級升級時的通知
        add_action('wc_points_rewards_tier_upgraded', array($this, 'send_tier_upgrade_notification'), 10, 2);
    }
    
    /**
     * 獲取所有會員等級
     */
    public function get_all_tiers() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        $tiers = $wpdb->get_results("
            SELECT * FROM $table_name 
            ORDER BY tier_order ASC
        ");
        
        return $tiers;
    }
    
    /**
     * 獲取單個會員等級
     */
    public function get_tier($tier_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        $tier = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_name WHERE id = %d
        ", $tier_id));
        
        return $tier;
    }
    
    /**
     * 創建或更新會員等級
     */
    public function save_tier($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        $tier_data = array(
            'name' => sanitize_text_field($data['name']),
            'min_amount' => floatval($data['min_amount']),
            'bonus_percentage' => floatval($data['bonus_percentage']),
            'tier_order' => intval($data['tier_order'])
        );
        
        if (isset($data['id']) && $data['id']) {
            // 更新現有等級
            $result = $wpdb->update(
                $table_name,
                $tier_data,
                array('id' => intval($data['id']))
            );
        } else {
            // 創建新等級
            $result = $wpdb->insert($table_name, $tier_data);
        }
        
        return $result !== false;
    }
    
    /**
     * 刪除會員等級
     */
    public function delete_tier($tier_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        // 檢查是否有用戶使用此等級
        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $users_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $stats_table 
            WHERE current_tier_id = %d
        ", $tier_id));
        
        if ($users_count > 0) {
            return new WP_Error('tier_in_use', __('無法刪除：仍有會員使用此等級', 'wc-points-rewards'));
        }
        
        $result = $wpdb->delete($table_name, array('id' => $tier_id));
        
        return $result !== false;
    }
    
    /**
     * 根據消費金額獲取符合的等級
     */
    public function get_tier_by_amount($amount) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        $tier = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE min_amount <= %f 
            ORDER BY min_amount DESC 
            LIMIT 1
        ", $amount));
        
        return $tier;
    }
    
    /**
     * 獲取用戶下一個等級
     */
    public function get_next_tier($user_id) {
        global $wpdb;
        
        $database = WC_Points_Rewards_Database::instance();
        $current_tier = $database->get_user_current_tier($user_id);
        
        if (!$current_tier) {
            return null;
        }
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        $next_tier = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE min_amount > %f 
            ORDER BY min_amount ASC 
            LIMIT 1
        ", $current_tier->min_amount));
        
        return $next_tier;
    }
    
    /**
     * 獲取最高等級
     */
    public function get_highest_tier() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        $highest_tier = $wpdb->get_row("
            SELECT * FROM $table_name 
            ORDER BY min_amount DESC 
            LIMIT 1
        ");
        
        return $highest_tier;
    }
    
    /**
     * 獲取指定等級的前一個等級
     */
    public function get_previous_tier($current_tier_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        // 先獲取當前等級的min_amount
        $current_tier = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE id = %d
        ", $current_tier_id));
        
        if (!$current_tier) {
            return null;
        }
        
        // 獲取比當前等級min_amount小的最大等級
        $previous_tier = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE min_amount < %f 
            ORDER BY min_amount DESC 
            LIMIT 1
        ", $current_tier->min_amount));
        
        return $previous_tier;
    }
    
    /**
     * 檢查用戶是否在最高等級
     */
    public function is_user_at_highest_tier($user_id) {
        $database = WC_Points_Rewards_Database::instance();
        $current_tier = $database->get_user_current_tier($user_id);
        $highest_tier = $this->get_highest_tier();
        
        if (!$current_tier || !$highest_tier) {
            return false;
        }
        
        return $current_tier->id === $highest_tier->id;
    }
    
    /**
     * 獲取用戶距離下一等級還需的消費金額
     */
    public function get_amount_to_next_tier($user_id) {
        $next_tier = $this->get_next_tier($user_id);
        if (!$next_tier) {
            return 0; // 已是最高等級
        }
        
        // 獲取用戶當年消費總額
        global $wpdb;
        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $current_year = date('Y');
        
        $total_spent = $wpdb->get_var($wpdb->prepare("
            SELECT total_spent FROM $stats_table 
            WHERE user_id = %d AND year = %d
        ", $user_id, $current_year));
        
        $total_spent = $total_spent ? floatval($total_spent) : 0;
        
        return max(0, $next_tier->min_amount - $total_spent);
    }
    
    /**
     * 檢查會員等級過期
     */
    public function check_tier_expiry() {
        global $wpdb;
        
        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $tiers_table = $wpdb->prefix . 'wc_points_rewards_tiers';
        
        // 獲取過期的會員
        $expired_members = $wpdb->get_results("
            SELECT * FROM $stats_table 
            WHERE tier_expiry_date IS NOT NULL 
            AND tier_expiry_date <= NOW()
            AND current_tier_id IS NOT NULL
        ");
        
        foreach ($expired_members as $member) {
            $current_year = date('Y');
            $current_spent = $wpdb->get_var($wpdb->prepare("
                SELECT total_spent FROM $stats_table 
                WHERE user_id = %d AND year = %d
            ", $member->user_id, $current_year));
            
            $current_spent = $current_spent ? floatval($current_spent) : 0;
            
            // 獲取最高等級
            $highest_tier = $wpdb->get_row("
                SELECT * FROM $tiers_table 
                ORDER BY min_amount DESC 
                LIMIT 1
            ");
            
            // 獲取當前會員等級
            $current_member_tier = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM $tiers_table 
                WHERE id = %d
            ", $member->current_tier_id));
            
            $new_tier = null;
            
            // 檢查是否為最高等級會員
            if ($highest_tier && $current_member_tier && $current_member_tier->id == $highest_tier->id) {
                // 最高等級會員：只有當消費達到最低要求時才能維持等級
                if ($current_spent >= $highest_tier->min_amount) {
                    $new_tier = $highest_tier;
                } else {
                    // 降至符合消費的等級
                    $new_tier = $wpdb->get_row($wpdb->prepare("
                        SELECT * FROM $tiers_table 
                        WHERE min_amount <= %f 
                        ORDER BY min_amount DESC 
                        LIMIT 1
                    ", $current_spent));
                }
            } else {
                // 非最高等級會員：檢查是否有消費但未升級
                if ($current_spent > 0) {
                    // 獲取下一等級
                    $next_tier = $wpdb->get_row($wpdb->prepare("
                        SELECT * FROM $tiers_table 
                        WHERE min_amount > %f 
                        ORDER BY min_amount ASC 
                        LIMIT 1
                    ", $current_member_tier->min_amount));
                    
                    // 如果有消費但未達到下一等級，自動降一級
                    if ($next_tier && $current_spent < $next_tier->min_amount) {
                        $previous_tier = $this->get_previous_tier($member->current_tier_id);
                        if ($previous_tier) {
                            $new_tier = $previous_tier;
                        } else {
                            // 沒有更低等級，重新計算符合的等級
                            $new_tier = $wpdb->get_row($wpdb->prepare("
                                SELECT * FROM $tiers_table 
                                WHERE min_amount <= %f 
                                ORDER BY min_amount DESC 
                                LIMIT 1
                            ", $current_spent));
                        }
                    } else {
                        // 重新計算符合的等級
                        $new_tier = $wpdb->get_row($wpdb->prepare("
                            SELECT * FROM $tiers_table 
                            WHERE min_amount <= %f 
                            ORDER BY min_amount DESC 
                            LIMIT 1
                        ", $current_spent));
                    }
                } else {
                    // 沒有消費，降至最低等級
                    $new_tier = $wpdb->get_row("
                        SELECT * FROM $tiers_table 
                        ORDER BY min_amount ASC 
                        LIMIT 1
                    ");
                }
            }
            
            if ($new_tier) {
                // 更新會員等級
                $wpdb->update(
                    $stats_table,
                    array(
                        'current_tier_id' => $new_tier->id,
                        'tier_start_date' => current_time('mysql'),
                        'tier_expiry_date' => date('Y-m-d H:i:s', strtotime('+1 year'))
                    ),
                    array('user_id' => $member->user_id, 'year' => $member->year)
                );
                
                // 發送等級變更通知
                do_action('wc_points_rewards_tier_changed', $member->user_id, $new_tier, $member->current_tier_id);
            }
        }
    }
    
    /**
     * 發送等級升級通知
     */
    public function send_tier_upgrade_notification($user_id, $new_tier) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        // 準備郵件內容
        $subject = sprintf(__('恭喜！您已升級為 %s', 'wc-points-rewards'), $new_tier->name);
        
        $message = sprintf(
            __('親愛的 %s，<br><br>恭喜您已升級為 %s！<br>您現在可以享受 %s%% 的額外點數回饋。<br><br>感謝您的支持！', 'wc-points-rewards'),
            $user->display_name,
            $new_tier->name,
            $new_tier->bonus_percentage
        );
        
        // 發送郵件
        wp_mail($user->user_email, $subject, $message);
        
        // 觸發自定義動作
        do_action('wc_points_rewards_tier_upgrade_notification_sent', $user_id, $new_tier);
    }
    
    /**
     * 獲取會員等級進度
     */
    public function get_tier_progress($user_id) {
        global $wpdb;
        
        $database = WC_Points_Rewards_Database::instance();
        $current_tier = $database->get_user_current_tier($user_id);
        $next_tier = $this->get_next_tier($user_id);
        
        // 獲取當年消費總額
        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $current_year = date('Y');
        
        $total_spent = $wpdb->get_var($wpdb->prepare("
            SELECT total_spent FROM $stats_table 
            WHERE user_id = %d AND year = %d
        ", $user_id, $current_year));
        
        $total_spent = $total_spent ? floatval($total_spent) : 0;
        
        $progress = array(
            'current_tier' => $current_tier,
            'next_tier' => $next_tier,
            'total_spent' => $total_spent,
            'amount_to_next' => $this->get_amount_to_next_tier($user_id),
            'progress_percentage' => 0
        );
        
        if ($next_tier && $current_tier) {
            $range = $next_tier->min_amount - $current_tier->min_amount;
            $current_progress = $total_spent - $current_tier->min_amount;
            $progress['progress_percentage'] = $range > 0 ? min(100, ($current_progress / $range) * 100) : 100;
        }
        
        return $progress;
    }
    
    /**
     * 手動設定用戶等級（管理員功能）
     */
    public function set_user_tier($user_id, $tier_id) {
        global $wpdb;
        
        $tier = $this->get_tier($tier_id);
        if (!$tier) {
            return false;
        }
        
        $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
        $current_year = date('Y');
        
        // 檢查是否已有記錄
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $stats_table 
            WHERE user_id = %d AND year = %d
        ", $user_id, $current_year));
        
        $data = array(
            'current_tier_id' => $tier_id,
            'tier_start_date' => current_time('mysql'),
            'tier_expiry_date' => date('Y-m-d H:i:s', strtotime('+1 year'))
        );
        
        if ($existing) {
            $result = $wpdb->update(
                $stats_table,
                $data,
                array('user_id' => $user_id, 'year' => $current_year)
            );
        } else {
            $data['user_id'] = $user_id;
            $data['year'] = $current_year;
            $data['total_spent'] = 0;
            $result = $wpdb->insert($stats_table, $data);
        }
        
        if ($result !== false) {
            do_action('wc_points_rewards_tier_manually_set', $user_id, $tier);
        }
        
        return $result !== false;
    }
}