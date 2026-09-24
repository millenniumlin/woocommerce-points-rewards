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

    <?php if ('success' === $manual_grant_notice && $manual_grant_message) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($manual_grant_message); ?></p></div>
    <?php elseif ('error' === $manual_grant_notice && $manual_grant_message) : ?>
        <div class="notice notice-error"><p><?php echo esc_html($manual_grant_message); ?></p></div>
    <?php endif; ?>

    <div class="postbox manual-grant-box">
        <div class="postbox-header">
            <h2 class="hndle"><?php _e('手動補發點數', 'wc-points-rewards'); ?></h2>
        </div>
        <div class="inside">
            <?php if ($manual_grant_is_authorized) : ?>
                <div class="manual-grant-usage-grid">
                    <div class="manual-grant-stat">
                        <strong><?php _e('您的今日補發額度', 'wc-points-rewards'); ?></strong>
                        <div><?php echo esc_html(wc_points_rewards_format_points_with_value($manual_grant_usage['admin_used'])); ?> / <?php echo esc_html(wc_points_rewards_format_points_with_value($manual_grant_settings['per_admin_daily_max'])); ?></div>
                        <div class="description"><?php printf(esc_html__('剩餘：%s', 'wc-points-rewards'), wc_points_rewards_format_points_with_value($manual_grant_usage['admin_remaining'])); ?></div>
                    </div>
                    <div class="manual-grant-stat">
                        <strong><?php _e('全站今日補發額度', 'wc-points-rewards'); ?></strong>
                        <div><?php echo esc_html(wc_points_rewards_format_points_with_value($manual_grant_usage['site_used'])); ?> / <?php echo esc_html(wc_points_rewards_format_points_with_value($manual_grant_settings['site_daily_max'])); ?></div>
                        <div class="description"><?php printf(esc_html__('剩餘：%s', 'wc-points-rewards'), wc_points_rewards_format_points_with_value($manual_grant_usage['site_remaining'])); ?></div>
                    </div>
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="manual-grant-form">
                    <?php wp_nonce_field('wc_points_rewards_manual_grant_points'); ?>
                    <input type="hidden" name="action" value="wc_points_rewards_manual_grant_points">

                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="grant_user_id"><?php _e('目標會員', 'wc-points-rewards'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="grant_user_id" name="grant_user_id" min="1" step="1" required value="<?php echo esc_attr($manual_grant_form_values['user_id']); ?>">
                                    <p class="description"><?php _e('請輸入 WordPress 使用者 ID。可先到「使用者」頁面依帳號或 Email 搜尋後取得 ID。', 'wc-points-rewards'); ?></p>
                                    <?php if ($manual_grant_target_user) : ?>
                                        <p>
                                            <strong><?php echo esc_html($manual_grant_target_user->display_name); ?></strong>
                                            <span>&lt;<?php echo esc_html($manual_grant_target_user->user_email); ?>&gt;</span><br>
                                            <span class="description"><?php printf(esc_html__('目前點數餘額：%s', 'wc-points-rewards'), wc_points_rewards_format_points_with_value($manual_grant_target_points)); ?></span>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="grant_points"><?php _e('補發點數', 'wc-points-rewards'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="grant_points" name="grant_points" min="0.01" step="0.01" required value="<?php echo esc_attr($manual_grant_form_values['points']); ?>">
                                    <p class="description">
                                        <?php
                                        printf(
                                            esc_html__('單筆上限：%s。功能目前%s。', 'wc-points-rewards'),
                                            wc_points_rewards_format_points_with_value($manual_grant_settings['per_grant_max']),
                                            'yes' === $manual_grant_settings['enabled'] ? __('已啟用', 'wc-points-rewards') : __('已停用', 'wc-points-rewards')
                                        );
                                        ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="grant_reason"><?php _e('補發原因', 'wc-points-rewards'); ?></label>
                                </th>
                                <td>
                                    <textarea id="grant_reason" name="grant_reason" rows="4" class="large-text" maxlength="500" required><?php echo esc_textarea($manual_grant_form_values['reason']); ?></textarea>
                                    <p class="description"><?php _e('原因為必填，會與操作者資訊一起寫入點數帳本作為稽核紀錄。', 'wc-points-rewards'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button(__('確認補發點數', 'wc-points-rewards'), 'primary', '', false); ?>
                </form>
            <?php else : ?>
                <p><?php _e('只有網站管理員或 Multisite Super Admin 可以手動補發點數。', 'wc-points-rewards'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
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

.manual-grant-usage-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.manual-grant-stat {
    background: #f6f7f7;
    border: 1px solid #dcdcde;
    padding: 12px 16px;
}

.manual-grant-form input[type="number"] {
    min-width: 220px;
}
</style>