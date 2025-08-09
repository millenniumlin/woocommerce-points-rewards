<?php
/**
 * 管理後台 - 點數記錄列表
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('點數記錄管理', 'wc-points-rewards'); ?></h1>
    
    <hr class="wp-header-end">
    
    <!-- 篩選選項 -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" action="">
                <input type="hidden" name="page" value="wc-points-rewards-points">
                
                <select name="type">
                    <option value=""><?php _e('所有類型', 'wc-points-rewards'); ?></option>
                    <option value="earned" <?php selected($type_filter, 'earned'); ?>><?php _e('獲得點數', 'wc-points-rewards'); ?></option>
                    <option value="redeemed" <?php selected($type_filter, 'redeemed'); ?>><?php _e('使用點數', 'wc-points-rewards'); ?></option>
                    <option value="expired" <?php selected($type_filter, 'expired'); ?>><?php _e('過期點數', 'wc-points-rewards'); ?></option>
                    <option value="admin" <?php selected($type_filter, 'admin'); ?>><?php _e('管理員調整', 'wc-points-rewards'); ?></option>
                </select>
                
                <input type="text" name="search" placeholder="<?php _e('搜尋用戶或描述', 'wc-points-rewards'); ?>" value="<?php echo esc_attr($search); ?>">
                
                <input type="submit" class="button" value="<?php _e('篩選', 'wc-points-rewards'); ?>">
            </form>
        </div>
    </div>
    
    <?php if (!empty($records)): ?>
    <!-- 點數記錄表格 -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('用戶', 'wc-points-rewards'); ?></th>
                <th><?php _e('點數變化', 'wc-points-rewards'); ?></th>
                <th><?php _e('類型', 'wc-points-rewards'); ?></th>
                <th><?php _e('說明', 'wc-points-rewards'); ?></th>
                <th><?php _e('日期', 'wc-points-rewards'); ?></th>
                <th><?php _e('到期日', 'wc-points-rewards'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $record): ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($record->display_name ?: __('未知用戶', 'wc-points-rewards')); ?></strong><br>
                    <small><?php echo esc_html($record->user_email); ?></small>
                </td>
                <td>
                    <span class="points-amount <?php echo floatval($record->points) > 0 ? 'positive' : 'negative'; ?>">
                        <?php echo floatval($record->points) > 0 ? '+' : ''; ?><?php echo wc_points_rewards_number_format(floatval($record->points)); ?>
                    </span>
                </td>
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
                <td><?php echo esc_html($record->description); ?></td>
                <td><?php echo date('Y-m-d H:i', strtotime($record->created_at)); ?></td>
                <td>
                    <?php if ($record->expiry_date): ?>
                        <?php echo date('Y-m-d', strtotime($record->expiry_date)); ?>
                        <?php if (strtotime($record->expiry_date) < strtotime('+30 days')): ?>
                            <span style="color: #d63638;">⚠️</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color: #8c8f94;">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- 分頁 -->
    <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            $page_links = paginate_links(array(
                'base' => admin_url('admin.php?page=wc-points-rewards-points&%_%'),
                'format' => '&paged=%#%',
                'current' => $current_page,
                'total' => $total_pages,
                'prev_text' => __('« 上一頁', 'wc-points-rewards'),
                'next_text' => __('下一頁 »', 'wc-points-rewards'),
            ));
            echo $page_links;
            ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <div class="no-records-message">
        <p><?php _e('找不到符合條件的點數記錄', 'wc-points-rewards'); ?></p>
    </div>
    <?php endif; ?>
</div>

<style>
.points-amount.positive {
    color: #00a32a;
    font-weight: bold;
}

.points-amount.negative {
    color: #d63638;
    font-weight: bold;
}

.points-type-earned { color: #00a32a; }
.points-type-redeemed { color: #d63638; }
.points-type-expired { color: #8c8f94; }
.points-type-admin { color: #2271b1; }

.no-records-message {
    text-align: center;
    padding: 40px;
    background: white;
    border: 1px solid #c3c4c7;
    margin-top: 20px;
}
</style>