<?php
/**
 * 管理介面 - 啟用記錄列表
 * 
 * @package ML_License_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('啟用記錄', 'ml-license-manager'); ?></h1>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo esc_html__('授權碼', 'ml-license-manager'); ?></th>
                <th><?php echo esc_html__('實例名稱', 'ml-license-manager'); ?></th>
                <th><?php echo esc_html__('實例ID', 'ml-license-manager'); ?></th>
                <th><?php echo esc_html__('IP地址', 'ml-license-manager'); ?></th>
                <th><?php echo esc_html__('狀態', 'ml-license-manager'); ?></th>
                <th><?php echo esc_html__('啟用時間', 'ml-license-manager'); ?></th>
                <th><?php echo esc_html__('最後檢查', 'ml-license-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($activations)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">
                        <?php echo esc_html__('目前沒有啟用記錄。', 'ml-license-manager'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($activations as $activation): ?>
                    <tr>
                        <td>
                            <code><?php echo esc_html($activation->license_key); ?></code>
                        </td>
                        <td>
                            <?php echo $activation->instance_name ? esc_html($activation->instance_name) : '—'; ?>
                        </td>
                        <td>
                            <?php echo $activation->instance_id ? esc_html($activation->instance_id) : '—'; ?>
                        </td>
                        <td>
                            <?php echo $activation->ip_address ? esc_html($activation->ip_address) : '—'; ?>
                        </td>
                        <td>
                            <?php
                            $status_class = 'ml-status-' . $activation->status;
                            $status_text = $activation->status === 'active' ? 
                                __('啟用中', 'ml-license-manager') : 
                                __('已停用', 'ml-license-manager');
                            ?>
                            <span class="ml-status-badge <?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html($status_text); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($activation->activated_at))); ?>
                        </td>
                        <td>
                            <?php
                            if ($activation->last_checked_at) {
                                echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($activation->last_checked_at)));
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($total_pages > 1): ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $page
                ));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.ml-status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.ml-status-active {
    background: #d4edda;
    color: #155724;
}

.ml-status-deactivated {
    background: #f8d7da;
    color: #721c24;
}
</style>
