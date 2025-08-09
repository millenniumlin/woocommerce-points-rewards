<?php
/**
 * 歷史訂單點數補發管理頁面
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

// 獲取統計資料
global $wpdb;

// 獲取總訂單數和已處理訂單數
$total_completed_orders = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->posts} 
    WHERE post_type = 'shop_order' 
    AND post_status = 'wc-completed'
");

$processed_orders = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'shop_order' 
    AND p.post_status = 'wc-completed'
    AND pm.meta_key = '_points_awarded'
    AND pm.meta_value > 0
");

$unprocessed_orders = $total_completed_orders - $processed_orders;
?>

<div class="wrap">
    <h1><?php _e('歷史訂單點數補發', 'wc-points-rewards'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('此功能可以為指定日期範圍內的已完成訂單補發點數。系統會自動跳過已經處理過的訂單，避免重複發放。', 'wc-points-rewards'); ?></p>
    </div>
    
    <!-- 統計資訊 -->
    <div class="postbox">
        <div class="postbox-header">
            <h2 class="hndle"><?php _e('訂單統計', 'wc-points-rewards'); ?></h2>
        </div>
        <div class="inside">
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong><?php _e('總完成訂單數', 'wc-points-rewards'); ?></strong></td>
                        <td><?php echo number_format($total_completed_orders); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('已處理訂單數', 'wc-points-rewards'); ?></strong></td>
                        <td><?php echo number_format($processed_orders); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('未處理訂單數', 'wc-points-rewards'); ?></strong></td>
                        <td><?php echo number_format($unprocessed_orders); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- 處理表單 -->
    <div class="postbox">
        <div class="postbox-header">
            <h2 class="hndle"><?php _e('批量處理歷史訂單', 'wc-points-rewards'); ?></h2>
        </div>
        <div class="inside">
            <form id="historical-orders-form">
                <?php wp_nonce_field('wc_points_rewards_historical_orders', 'nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="start_date"><?php _e('開始日期', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="date" id="start_date" name="start_date" required>
                                <p class="description"><?php _e('選擇要處理的訂單開始日期', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="end_date"><?php _e('結束日期', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="date" id="end_date" name="end_date" required>
                                <p class="description"><?php _e('選擇要處理的訂單結束日期', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="dry_run"><?php _e('測試模式', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="dry_run" name="dry_run" value="1">
                                <label for="dry_run"><?php _e('啟用測試模式（只顯示將要處理的訂單數量，不實際發放點數）', 'wc-points-rewards'); ?></label>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary" id="process-orders-btn">
                        <?php _e('開始處理', 'wc-points-rewards'); ?>
                    </button>
                    <span class="spinner" id="processing-spinner"></span>
                </p>
            </form>
        </div>
    </div>
    
    <!-- 處理結果 -->
    <div id="processing-results" class="postbox" style="display: none;">
        <div class="postbox-header">
            <h2 class="hndle"><?php _e('處理結果', 'wc-points-rewards'); ?></h2>
        </div>
        <div class="inside">
            <div id="results-content"></div>
        </div>
    </div>
    
    <!-- 最近處理記錄 -->
    <div class="postbox">
        <div class="postbox-header">
            <h2 class="hndle"><?php _e('最近處理記錄', 'wc-points-rewards'); ?></h2>
        </div>
        <div class="inside">
            <?php
            // 獲取最近的點數記錄
            $points_table = $wpdb->prefix . 'wc_points_rewards_points';
            $recent_backfills = $wpdb->get_results($wpdb->prepare("
                SELECT p.*, u.display_name, u.user_email
                FROM $points_table p
                LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
                WHERE p.description LIKE %s
                ORDER BY p.created_at DESC
                LIMIT 20
            ", '%補發點數%'));
            
            if ($recent_backfills): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('日期', 'wc-points-rewards'); ?></th>
                            <th><?php _e('用戶', 'wc-points-rewards'); ?></th>
                            <th><?php _e('點數', 'wc-points-rewards'); ?></th>
                            <th><?php _e('描述', 'wc-points-rewards'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_backfills as $record): ?>
                            <tr>
                                <td><?php echo esc_html(mysql2date('Y-m-d H:i:s', $record->created_at)); ?></td>
                                <td>
                                    <?php echo esc_html($record->display_name); ?>
                                    <br><small><?php echo esc_html($record->user_email); ?></small>
                                </td>
                                <td>
                                    <span class="points-earned">
                                        +<?php echo wc_points_rewards_number_format($record->points); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($record->description); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('尚無處理記錄', 'wc-points-rewards'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

