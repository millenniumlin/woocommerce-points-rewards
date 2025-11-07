<?php
/**
 * My Account Licenses Template
 */

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="millennium-licenses-page">
    <h2><?php _e('我的授權碼', 'millennium-license'); ?></h2>
    
    <?php if (!empty($licenses)) : ?>
        <table class="woocommerce-table woocommerce-table--order-details shop_table order_details millennium-licenses-table">
            <thead>
                <tr>
                    <th><?php _e('授權碼', 'millennium-license'); ?></th>
                    <th><?php _e('產品', 'millennium-license'); ?></th>
                    <th><?php _e('狀態', 'millennium-license'); ?></th>
                    <th><?php _e('啟用次數', 'millennium-license'); ?></th>
                    <th><?php _e('到期時間', 'millennium-license'); ?></th>
                    <th><?php _e('操作', 'millennium-license'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($licenses as $license) : ?>
                    <tr>
                        <td data-title="<?php esc_attr_e('授權碼', 'millennium-license'); ?>">
                            <code class="license-key"><?php echo esc_html($license->license_key); ?></code>
                        </td>
                        <td data-title="<?php esc_attr_e('產品', 'millennium-license'); ?>">
                            <?php 
                            if ($license->product_id) {
                                $product = wc_get_product($license->product_id);
                                if ($product) {
                                    echo '<a href="' . esc_url(get_permalink($license->product_id)) . '">' . esc_html($product->get_name()) . '</a>';
                                } else {
                                    echo '—';
                                }
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td data-title="<?php esc_attr_e('狀態', 'millennium-license'); ?>">
                            <?php
                            $status_labels = array(
                                'active' => '<span class="license-status license-status-active">' . __('啟用', 'millennium-license') . '</span>',
                                'inactive' => '<span class="license-status license-status-inactive">' . __('停用', 'millennium-license') . '</span>',
                                'expired' => '<span class="license-status license-status-expired">' . __('已過期', 'millennium-license') . '</span>',
                            );
                            echo isset($status_labels[$license->status]) ? $status_labels[$license->status] : esc_html($license->status);
                            ?>
                        </td>
                        <td data-title="<?php esc_attr_e('啟用次數', 'millennium-license'); ?>">
                            <?php printf('%d / %d', $license->activation_count, $license->max_activations); ?>
                        </td>
                        <td data-title="<?php esc_attr_e('到期時間', 'millennium-license'); ?>">
                            <?php
                            if ($license->expires_at) {
                                $expires_timestamp = strtotime($license->expires_at);
                                $current_timestamp = current_time('timestamp');
                                
                                if ($expires_timestamp > $current_timestamp) {
                                    $time_diff = human_time_diff($expires_timestamp, $current_timestamp);
                                    printf(__('%s 後到期', 'millennium-license'), $time_diff);
                                } else {
                                    $time_diff = human_time_diff($current_timestamp, $expires_timestamp);
                                    printf(__('%s 前已過期', 'millennium-license'), $time_diff);
                                }
                            } else {
                                _e('永久', 'millennium-license');
                            }
                            ?>
                        </td>
                        <td data-title="<?php esc_attr_e('操作', 'millennium-license'); ?>">
                            <?php if ($license->order_id) : 
                                $order = wc_get_order($license->order_id);
                                if ($order) :
                            ?>
                                <a href="<?php echo esc_url($order->get_view_order_url()); ?>" class="button">
                                    <?php _e('查看訂單', 'millennium-license'); ?>
                                </a>
                            <?php 
                                endif;
                            endif; 
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
            <?php _e('您目前沒有任何授權碼。', 'millennium-license'); ?>
        </div>
    <?php endif; ?>
</div>

<style>
.millennium-licenses-table {
    margin: 20px 0;
}

.license-key {
    background-color: #f8f9fa;
    padding: 5px 10px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 14px;
}

.license-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.license-status-active {
    background-color: #d4edda;
    color: #155724;
}

.license-status-inactive {
    background-color: #f8d7da;
    color: #721c24;
}

.license-status-expired {
    background-color: #e2e3e5;
    color: #383d41;
}

@media screen and (max-width: 768px) {
    .millennium-licenses-table thead {
        display: none;
    }
    
    .millennium-licenses-table tr {
        display: block;
        margin-bottom: 20px;
        border: 1px solid #ddd;
        padding: 10px;
    }
    
    .millennium-licenses-table td {
        display: block;
        text-align: right;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }
    
    .millennium-licenses-table td:before {
        content: attr(data-title);
        float: left;
        font-weight: bold;
    }
    
    .millennium-licenses-table td:last-child {
        border-bottom: 0;
    }
}
</style>
