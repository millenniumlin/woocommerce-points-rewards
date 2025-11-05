<?php
/**
 * 管理介面 - 授權碼列表
 * 
 * @package ML_License_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html__('授權碼', 'ml-license-manager'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=ml-license-add')); ?>" class="page-title-action">
        <?php echo esc_html__('新增', 'ml-license-manager'); ?>
    </a>
    <hr class="wp-header-end">
    
    <?php if (isset($_GET['message']) && $_GET['message'] === 'deleted'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('授權碼已刪除。', 'ml-license-manager'); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['message']) && $_GET['message'] === 'created'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('授權碼已創建。', 'ml-license-manager'); ?></p>
        </div>
    <?php endif; ?>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo esc_html__('授權碼', 'ml-license-manager'); ?></th>
                <th><?php echo esc_html__('狀態', 'ml-license-manager'); ?></th>
                <th><?php echo esc_html__('用戶', 'ml-license-manager'); ?></th>
                <th><?php echo esc_html__('啟用次數', 'ml-license-manager'); ?></th>
                <th><?php echo esc_html__('過期時間', 'ml-license-manager'); ?></th>
                <th><?php echo esc_html__('創建時間', 'ml-license-manager'); ?></th>
                <th><?php echo esc_html__('操作', 'ml-license-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($licenses)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">
                        <?php echo esc_html__('目前沒有授權碼。', 'ml-license-manager'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($licenses as $license): ?>
                    <tr>
                        <td>
                            <code><?php echo esc_html($license->license_key); ?></code>
                        </td>
                        <td>
                            <?php
                            $status_class = 'ml-status-' . $license->status;
                            $status_text = '';
                            switch ($license->status) {
                                case 'active':
                                    $status_text = __('啟用中', 'ml-license-manager');
                                    break;
                                case 'inactive':
                                    $status_text = __('未啟用', 'ml-license-manager');
                                    break;
                                case 'expired':
                                    $status_text = __('已過期', 'ml-license-manager');
                                    break;
                                case 'revoked':
                                    $status_text = __('已撤銷', 'ml-license-manager');
                                    break;
                            }
                            ?>
                            <span class="ml-status-badge <?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html($status_text); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            if ($license->user_id) {
                                $user = get_userdata($license->user_id);
                                if ($user) {
                                    echo esc_html($user->display_name);
                                } else {
                                    echo '—';
                                }
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td>
                            <?php echo esc_html($license->activation_count . ' / ' . $license->activation_limit); ?>
                        </td>
                        <td>
                            <?php
                            if ($license->expires_at) {
                                echo esc_html(date_i18n(get_option('date_format'), strtotime($license->expires_at)));
                            } else {
                                echo esc_html__('永久', 'ml-license-manager');
                            }
                            ?>
                        </td>
                        <td>
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($license->created_at))); ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=ml-license-keys&action=delete&id=' . $license->id), 'delete-license-' . $license->id)); ?>" 
                               class="button button-small"
                               onclick="return confirm('<?php echo esc_js(__('確定要刪除此授權碼嗎？', 'ml-license-manager')); ?>')">
                                <?php echo esc_html__('刪除', 'ml-license-manager'); ?>
                            </a>
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

.ml-status-inactive {
    background: #f8f9fa;
    color: #6c757d;
}

.ml-status-expired {
    background: #fff3cd;
    color: #856404;
}

.ml-status-revoked {
    background: #f8d7da;
    color: #721c24;
}
</style>
