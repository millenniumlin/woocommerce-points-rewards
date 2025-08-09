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

<style>
.points-earned {
    color: #46b450;
    font-weight: bold;
}

.processing-status {
    padding: 10px;
    margin: 10px 0;
    border-left: 4px solid #0073aa;
    background: #f7fcfe;
}

.processing-status.success {
    border-left-color: #46b450;
    background: #f7fff7;
}

.processing-status.error {
    border-left-color: #dc3232;
    background: #fef7f7;
}

#processing-spinner {
    float: none;
    margin-left: 10px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#historical-orders-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $('#process-orders-btn');
        var $spinner = $('#processing-spinner');
        var $results = $('#processing-results');
        var $resultsContent = $('#results-content');
        
        // 驗證日期
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        
        if (!startDate || !endDate) {
            alert('<?php _e('請選擇開始和結束日期', 'wc-points-rewards'); ?>');
            return;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            alert('<?php _e('開始日期不能晚於結束日期', 'wc-points-rewards'); ?>');
            return;
        }
        
        // 確認處理
        var isDryRun = $('#dry_run').is(':checked');
        var confirmMessage = isDryRun 
            ? '<?php _e('確定要執行測試模式嗎？', 'wc-points-rewards'); ?>'
            : '<?php _e('確定要開始處理歷史訂單嗎？此操作將會實際發放點數。', 'wc-points-rewards'); ?>';
            
        if (!confirm(confirmMessage)) {
            return;
        }
        
        // 開始處理
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $results.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_points_rewards_process_historical_orders',
                nonce: $('#nonce').val(),
                start_date: startDate,
                end_date: endDate,
                dry_run: isDryRun ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    $resultsContent.html('<div class="processing-status success">' + response.data.message + '</div>');
                    $results.show();
                    
                    // 如果不是測試模式，重新整理頁面以更新統計
                    if (!isDryRun) {
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    }
                } else {
                    $resultsContent.html('<div class="processing-status error">' + (response.data || '<?php _e('處理失敗', 'wc-points-rewards'); ?>') + '</div>');
                    $results.show();
                }
            },
            error: function(xhr, status, error) {
                $resultsContent.html('<div class="processing-status error"><?php _e('請求失敗：', 'wc-points-rewards'); ?>' + error + '</div>');
                $results.show();
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // 設定預設日期範圍（最近30天）
    var today = new Date();
    var thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);
    
    $('#end_date').val(today.toISOString().split('T')[0]);
    $('#start_date').val(thirtyDaysAgo.toISOString().split('T')[0]);
});
</script>