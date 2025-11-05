<?php
/**
 * 管理介面 - 設定頁面
 * 
 * @package ML_License_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('授權管理設定', 'ml-license-manager'); ?></h1>
    
    <?php if (isset($_GET['message']) && $_GET['message'] === 'saved'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('設定已儲存。', 'ml-license-manager'); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('ml_save_settings'); ?>
        
        <h2 class="title"><?php echo esc_html__('基本設定', 'ml-license-manager'); ?></h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="enable_license_system">
                            <?php echo esc_html__('啟用授權系統', 'ml-license-manager'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="enable_license_system" 
                                   id="enable_license_system" 
                                   value="yes"
                                   <?php checked(isset($settings['enable_license_system']) ? $settings['enable_license_system'] : 'yes', 'yes'); ?>>
                            <?php echo esc_html__('啟用授權管理系統', 'ml-license-manager'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="default_activation_limit">
                            <?php echo esc_html__('預設啟用次數限制', 'ml-license-manager'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               name="default_activation_limit" 
                               id="default_activation_limit" 
                               class="small-text" 
                               min="1"
                               value="<?php echo esc_attr(isset($settings['default_activation_limit']) ? $settings['default_activation_limit'] : 1); ?>">
                        <p class="description">
                            <?php echo esc_html__('新授權碼的預設啟用次數限制', 'ml-license-manager'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="default_expiry_days">
                            <?php echo esc_html__('預設有效天數', 'ml-license-manager'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               name="default_expiry_days" 
                               id="default_expiry_days" 
                               class="small-text" 
                               min="0"
                               value="<?php echo esc_attr(isset($settings['default_expiry_days']) ? $settings['default_expiry_days'] : 365); ?>">
                        <p class="description">
                            <?php echo esc_html__('新授權碼的預設有效天數（0表示永久有效）', 'ml-license-manager'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <h2 class="title"><?php echo esc_html__('授權碼生成設定', 'ml-license-manager'); ?></h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="license_key_length">
                            <?php echo esc_html__('授權碼長度', 'ml-license-manager'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               name="license_key_length" 
                               id="license_key_length" 
                               class="small-text" 
                               min="16"
                               max="64"
                               value="<?php echo esc_attr(isset($settings['license_key_length']) ? $settings['license_key_length'] : 32); ?>">
                        <p class="description">
                            <?php echo esc_html__('授權碼的字符長度（16-64）', 'ml-license-manager'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="license_key_format">
                            <?php echo esc_html__('授權碼格式', 'ml-license-manager'); ?>
                        </label>
                    </th>
                    <td>
                        <select name="license_key_format" id="license_key_format">
                            <option value="alphanumeric" <?php selected(isset($settings['license_key_format']) ? $settings['license_key_format'] : 'alphanumeric', 'alphanumeric'); ?>>
                                <?php echo esc_html__('英數字（易讀）', 'ml-license-manager'); ?>
                            </option>
                            <option value="hex" <?php selected(isset($settings['license_key_format']) ? $settings['license_key_format'] : '', 'hex'); ?>>
                                <?php echo esc_html__('十六進位', 'ml-license-manager'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php echo esc_html__('選擇授權碼的生成格式', 'ml-license-manager'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <h2 class="title"><?php echo esc_html__('API 設定', 'ml-license-manager'); ?></h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="enable_api">
                            <?php echo esc_html__('啟用 REST API', 'ml-license-manager'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="enable_api" 
                                   id="enable_api" 
                                   value="yes"
                                   <?php checked(isset($settings['enable_api']) ? $settings['enable_api'] : 'yes', 'yes'); ?>>
                            <?php echo esc_html__('啟用 REST API 端點', 'ml-license-manager'); ?>
                        </label>
                        <p class="description">
                            <?php echo esc_html__('允許通過 REST API 驗證和管理授權碼', 'ml-license-manager'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="require_api_authentication">
                            <?php echo esc_html__('要求 API 驗證', 'ml-license-manager'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="require_api_authentication" 
                                   id="require_api_authentication" 
                                   value="yes"
                                   <?php checked(isset($settings['require_api_authentication']) ? $settings['require_api_authentication'] : 'yes', 'yes'); ?>>
                            <?php echo esc_html__('要求 API 請求進行身份驗證', 'ml-license-manager'); ?>
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <button type="submit" name="ml_save_settings" class="button button-primary">
                <?php echo esc_html__('儲存設定', 'ml-license-manager'); ?>
            </button>
        </p>
    </form>
    
    <div class="card">
        <h2><?php echo esc_html__('API 端點', 'ml-license-manager'); ?></h2>
        <p><?php echo esc_html__('以下是可用的 REST API 端點：', 'ml-license-manager'); ?></p>
        <ul>
            <li><code>POST <?php echo esc_html(rest_url('ml-license/v1/validate')); ?></code> - <?php echo esc_html__('驗證授權碼', 'ml-license-manager'); ?></li>
            <li><code>POST <?php echo esc_html(rest_url('ml-license/v1/activate')); ?></code> - <?php echo esc_html__('啟用授權碼', 'ml-license-manager'); ?></li>
            <li><code>POST <?php echo esc_html(rest_url('ml-license/v1/deactivate')); ?></code> - <?php echo esc_html__('停用授權碼', 'ml-license-manager'); ?></li>
            <li><code>POST <?php echo esc_html(rest_url('ml-license/v1/check')); ?></code> - <?php echo esc_html__('檢查授權碼狀態', 'ml-license-manager'); ?></li>
        </ul>
    </div>
</div>
