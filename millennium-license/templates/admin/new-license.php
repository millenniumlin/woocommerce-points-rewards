<?php
/**
 * Admin New License Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// 獲取所有產品
$products = wc_get_products(array(
    'limit' => -1,
    'status' => 'publish',
));

// 獲取設定
$settings = get_option('millennium_license_settings', array());
$default_max_activations = isset($settings['max_activations']) ? $settings['max_activations'] : 1;
$default_expiry_days = isset($settings['default_expiry_days']) ? $settings['default_expiry_days'] : 365;

?>
<div class="wrap">
    <h1><?php _e('新增授權碼', 'millennium-license'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('create-license'); ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="product_id"><?php _e('產品', 'millennium-license'); ?></label>
                    </th>
                    <td>
                        <select name="product_id" id="product_id" class="regular-text">
                            <option value=""><?php _e('選擇產品（選填）', 'millennium-license'); ?></option>
                            <?php foreach ($products as $product) : ?>
                                <option value="<?php echo esc_attr($product->get_id()); ?>">
                                    <?php echo esc_html($product->get_name()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('選擇此授權碼關聯的產品', 'millennium-license'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="user_id"><?php _e('用戶', 'millennium-license'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="user_id" id="user_id" class="regular-text" min="0">
                        <p class="description"><?php _e('輸入用戶 ID（選填）', 'millennium-license'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="max_activations"><?php _e('最大啟用次數', 'millennium-license'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="max_activations" id="max_activations" value="<?php echo esc_attr($default_max_activations); ?>" class="small-text" min="1" required>
                        <p class="description"><?php _e('此授權碼可以啟用的次數', 'millennium-license'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="expires_at"><?php _e('到期時間', 'millennium-license'); ?></label>
                    </th>
                    <td>
                        <input type="datetime-local" name="expires_at" id="expires_at" class="regular-text">
                        <p class="description">
                            <?php 
                            printf(
                                __('預設：%d 天後到期，留空表示永久', 'millennium-license'),
                                $default_expiry_days
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <input type="submit" name="create_license" class="button button-primary" value="<?php esc_attr_e('建立授權碼', 'millennium-license'); ?>">
            <a href="<?php echo admin_url('admin.php?page=millennium-license'); ?>" class="button">
                <?php _e('取消', 'millennium-license'); ?>
            </a>
        </p>
    </form>
</div>
