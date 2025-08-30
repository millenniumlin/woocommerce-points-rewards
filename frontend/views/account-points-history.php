<?php
/**
 * 我的帳戶 - 點數記錄模板
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wc-points-rewards-history">
    <?php if (!empty($history)): ?>
    <!-- 點數記錄表格 -->
    <div class="points-history-table-wrapper">
        <table class="wc-points-history-table">
            <thead>
                <tr>
                    <th class="date-column"><?php _e('日期', 'wc-points-rewards'); ?></th>
                    <th class="description-column"><?php _e('說明', 'wc-points-rewards'); ?></th>
                    <th class="points-column"><?php _e('點數變化', 'wc-points-rewards'); ?></th>
                    <th class="expiry-column"><?php _e('到期日', 'wc-points-rewards'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $record): ?>
                <tr class="history-row history-<?php echo esc_attr($record->type); ?>">
                    <td class="date-cell">
                        <time datetime="<?php echo esc_attr($record->created_at); ?>">
                            <?php echo date('Y-m-d H:i', strtotime($record->created_at)); ?>
                        </time>
                    </td>
                    <td class="description-cell">
                        <span class="description-text"><?php echo esc_html($record->description); ?></span>
                        <span class="type-badge type-<?php echo esc_attr($record->type); ?>">
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
                    <td class="points-cell">
                        <span class="points-amount <?php echo $record->points > 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $record->points > 0 ? '+' : ''; ?><?php echo wc_points_rewards_number_format($record->points); ?>
                        </span>
                    </td>
                    <td class="expiry-cell">
                        <?php if ($record->expiry_date && $record->type === 'earned'): ?>
                            <time datetime="<?php echo esc_attr($record->expiry_date); ?>">
                                <?php echo date('Y-m-d', strtotime($record->expiry_date)); ?>
                            </time>
                            <?php if (strtotime($record->expiry_date) < strtotime('+30 days')): ?>
                                <span class="expiry-warning">⚠️</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="no-expiry">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- 分頁 -->
    <?php if ($total_pages > 1): ?>
    <div class="points-history-pagination">
        <?php
        $base_url = wc_points_rewards_get_account_endpoint_url('points-history');
        
        echo paginate_links(array(
            'base' => $base_url . '%_%',
            'format' => '?paged=%#%',
            'current' => $current_page,
            'total' => $total_pages,
            'prev_text' => __('« 上一頁', 'wc-points-rewards'),
            'next_text' => __('下一頁 »', 'wc-points-rewards'),
        ));
        ?>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <!-- 無記錄時的顯示 -->
    <div class="no-history-message">
        <div class="no-history-icon">📝</div>
        <h3><?php _e('暫無點數記錄', 'wc-points-rewards'); ?></h3>
        <p><?php _e('開始購物即可獲得點數！', 'wc-points-rewards'); ?></p>
        <a href="<?php echo wc_get_page_permalink('shop'); ?>" class="button button-primary">
            <?php _e('立即購物', 'wc-points-rewards'); ?>
        </a>
    </div>
    <?php endif; ?>
</div>