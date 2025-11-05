<?php
/**
 * 管理介面 - 新增授權碼
 * 
 * @package ML_License_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('新增授權碼', 'ml-license-manager'); ?></h1>
    
    <?php if (isset($error)): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($error); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('ml_add_license'); ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="user_id"><?php echo esc_html__('用戶', 'ml-license-manager'); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_dropdown_users(array(
                            'name' => 'user_id',
                            'id' => 'user_id',
                            'show_option_none' => __('— 選擇用戶 —', 'ml-license-manager'),
                            'option_none_value' => '',
                        ));
                        ?>
                        <p class="description">
                            <?php echo esc_html__('選擇要分配此授權碼的用戶（可選）', 'ml-license-manager'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="product_id"><?php echo esc_html__('產品ID', 'ml-license-manager'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               name="product_id" 
                               id="product_id" 
                               class="regular-text" 
                               min="0"
                               value="">
                        <p class="description">
                            <?php echo esc_html__('關聯的產品ID（可選）', 'ml-license-manager'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="activation_limit"><?php echo esc_html__('啟用次數限制', 'ml-license-manager'); ?></label>
                    </th>
                    <td>
                        <?php
                        $settings = get_option('ml_license_manager_settings', array());
                        $default_limit = isset($settings['default_activation_limit']) ? $settings['default_activation_limit'] : 1;
                        ?>
                        <input type="number" 
                               name="activation_limit" 
                               id="activation_limit" 
                               class="small-text" 
                               min="1"
                               value="<?php echo esc_attr($default_limit); ?>"
                               required>
                        <p class="description">
                            <?php echo esc_html__('此授權碼可以啟用的次數', 'ml-license-manager'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="expires_at"><?php echo esc_html__('過期時間', 'ml-license-manager'); ?></label>
                    </th>
                    <td>
                        <input type="datetime-local" 
                               name="expires_at" 
                               id="expires_at" 
                               class="regular-text"
                               value="">
                        <p class="description">
                            <?php echo esc_html__('留空表示永久有效', 'ml-license-manager'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <button type="submit" name="ml_add_license" class="button button-primary">
                <?php echo esc_html__('生成授權碼', 'ml-license-manager'); ?>
            </button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=ml-license-keys')); ?>" class="button">
                <?php echo esc_html__('取消', 'ml-license-manager'); ?>
            </a>
        </p>
    </form>
</div>
