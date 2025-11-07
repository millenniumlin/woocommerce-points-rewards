<?php
/**
 * Admin Settings Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('millennium_license_settings', array());
$api_key = Millennium_License_API_Auth::get_api_key();

?>
<div class="wrap">
    <h1><?php _e('授權設定', 'millennium-license'); ?></h1>
    
    <?php if (isset($_GET['message']) && $_GET['message'] === 'saved') : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('設定已儲存。', 'millennium-license'); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('millennium-license-settings'); ?>
        
        <h2><?php _e('一般設定', 'millennium-license'); ?></h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="license_key_format"><?php _e('授權碼格式', 'millennium-license'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="license_key_format" id="license_key_format" 
                               value="<?php echo esc_attr(isset($settings['license_key_format']) ? $settings['license_key_format'] : 'XXXX-XXXX-XXXX-XXXX'); ?>" 
                               class="regular-text">
                        <p class="description">
                            <?php _e('使用 X 代表隨機字元，例如：XXXX-XXXX-XXXX-XXXX', 'millennium-license'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="default_expiry_days"><?php _e('預設有效期限（天）', 'millennium-license'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="default_expiry_days" id="default_expiry_days" 
                               value="<?php echo esc_attr(isset($settings['default_expiry_days']) ? $settings['default_expiry_days'] : 365); ?>" 
                               class="small-text" min="0">
                        <p class="description"><?php _e('新授權碼的預設有效天數', 'millennium-license'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="max_activations"><?php _e('預設最大啟用次數', 'millennium-license'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="max_activations" id="max_activations" 
                               value="<?php echo esc_attr(isset($settings['max_activations']) ? $settings['max_activations'] : 1); ?>" 
                               class="small-text" min="1">
                        <p class="description"><?php _e('新授權碼的預設啟用次數', 'millennium-license'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <h2><?php _e('API 設定', 'millennium-license'); ?></h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="enable_api"><?php _e('啟用 API', 'millennium-license'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_api" id="enable_api" value="yes" 
                                   <?php checked(isset($settings['enable_api']) && $settings['enable_api'] === 'yes'); ?>>
                            <?php _e('啟用 REST API 端點', 'millennium-license'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php _e('API 密鑰', 'millennium-license'); ?></label>
                    </th>
                    <td>
                        <code class="api-key"><?php echo esc_html($api_key); ?></code>
                        <p class="description">
                            <?php _e('使用此密鑰進行 API 認證。將密鑰放在請求標頭 X-API-Key 中。', 'millennium-license'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php _e('API 端點', 'millennium-license'); ?></label>
                    </th>
                    <td>
                        <ul>
                            <li><code><?php echo esc_url(rest_url('millennium-license/v1/validate')); ?></code> - <?php _e('驗證授權碼', 'millennium-license'); ?></li>
                            <li><code><?php echo esc_url(rest_url('millennium-license/v1/activate')); ?></code> - <?php _e('啟用授權碼', 'millennium-license'); ?></li>
                            <li><code><?php echo esc_url(rest_url('millennium-license/v1/deactivate')); ?></code> - <?php _e('停用授權碼', 'millennium-license'); ?></li>
                            <li><code><?php echo esc_url(rest_url('millennium-license/v1/check')); ?></code> - <?php _e('檢查授權狀態', 'millennium-license'); ?></li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <h2><?php _e('通知設定', 'millennium-license'); ?></h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="enable_email_notifications"><?php _e('郵件通知', 'millennium-license'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_email_notifications" id="enable_email_notifications" value="yes" 
                                   <?php checked(isset($settings['enable_email_notifications']) && $settings['enable_email_notifications'] === 'yes'); ?>>
                            <?php _e('發送授權碼郵件通知', 'millennium-license'); ?>
                        </label>
                        <p class="description">
                            <?php _e('當訂單完成時，自動發送授權碼給客戶', 'millennium-license'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <input type="submit" name="save_settings" class="button button-primary" value="<?php esc_attr_e('儲存設定', 'millennium-license'); ?>">
        </p>
    </form>
</div>
