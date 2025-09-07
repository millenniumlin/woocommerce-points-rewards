<?php
/**
 * 設定頁面類別
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 設定頁面類別
 */
class WC_Points_Rewards_Settings {
    
    /**
     * 單例實例
     */
    private static $instance = null;
    
    /**
     * 獲取單例實例
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 建構函式
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * 初始化 hooks
     */
    private function init_hooks() {
        // 添加管理頁面
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // 註冊設定
        add_action('admin_init', array($this, 'register_settings'));
        
        // 處理設定儲存
        add_action('admin_post_save_points_rewards_settings', array($this, 'save_settings'));
    }
    
    /**
     * 添加管理選單
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('點數獎勵設定', 'wc-points-rewards'),
            __('點數獎勵', 'wc-points-rewards'),
            'manage_woocommerce',
            'wc-points-rewards-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * 註冊設定
     */
    public function register_settings() {
        // 一般設定
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_enable_points_system');
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_points_per_amount');
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_points_name');
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_points_value');
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_points_expiry_months');
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_registration_points');
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_birthday_points');
        
        // 購物車點數使用限制設定
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_enable_cart_redemption');
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_min_cart_total');
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_max_discount_percent');
        
        // 會員等級設定
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_enable_tiers');
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_tier_period');
        
        // 通知設定
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_enable_notifications');
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_expiry_notification_days');
        register_setting('wc_points_rewards_settings', 'wc_points_rewards_enable_birthday_notification');
    }
    
    /**
     * 渲染設定頁面
     */
    public function render_settings_page() {
        // 獲取當前設定
        $settings = $this->get_current_settings();
        
        ?>
        <div class="wrap">
            <h1><?php _e('點數獎勵系統設定', 'wc-points-rewards'); ?></h1>
            
            <form method="post" action="admin-post.php">
                <?php wp_nonce_field('save_points_rewards_settings', 'points_rewards_nonce'); ?>
                <input type="hidden" name="action" value="save_points_rewards_settings">
                
                <!-- 一般設定 -->
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="enable_points_system"><?php _e('啟用點數系統', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="enable_points_system" name="wc_points_rewards_enable_points_system" value="yes" 
                                       <?php checked($settings['enable_points_system'], 'yes'); ?>>
                                <p class="description"><?php _e('啟用整個點數獎勵系統', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="points_per_amount"><?php _e('點數比例', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="points_per_amount" name="wc_points_rewards_points_per_amount" 
                                       value="<?php echo esc_attr($settings['points_per_amount']); ?>" min="1" step="1">
                                <p class="description"><?php _e('每消費多少金額可獲得1點', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="points_name"><?php _e('點數名稱', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="points_name" name="wc_points_rewards_points_name" 
                                       value="<?php echo esc_attr($settings['points_name']); ?>">
                                <p class="description"><?php _e('點數的顯示名稱', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="points_value"><?php _e('點數價值', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="points_value" name="wc_points_rewards_points_value" 
                                       value="<?php echo esc_attr($settings['points_value']); ?>" min="0.001" step="0.001">
                                <p class="description"><?php _e('1點等於多少元（用於折抵）', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="points_expiry_months"><?php _e('點數有效期', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="points_expiry_months" name="wc_points_rewards_points_expiry_months" 
                                       value="<?php echo esc_attr($settings['points_expiry_months']); ?>" min="0">
                                <p class="description"><?php _e('點數幾個月後過期（0表示永不過期）', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="registration_points"><?php _e('註冊贈送點數', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="registration_points" name="wc_points_rewards_registration_points" 
                                       value="<?php echo esc_attr($settings['registration_points']); ?>" min="0">
                                <p class="description"><?php _e('新用戶註冊時贈送的點數', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="birthday_points"><?php _e('生日贈送點數', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="birthday_points" name="wc_points_rewards_birthday_points" 
                                       value="<?php echo esc_attr($settings['birthday_points']); ?>" min="0">
                                <p class="description"><?php _e('用戶設定生日時贈送的點數', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h2><?php _e('購物車點數使用限制', 'wc-points-rewards'); ?></h2>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="enable_cart_redemption"><?php _e('啟用購物車點數折抵', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="enable_cart_redemption" name="wc_points_rewards_enable_cart_redemption" value="yes" 
                                       <?php checked($settings['enable_cart_redemption'], 'yes'); ?>>
                                <p class="description"><?php _e('允許用戶在購物車和結帳頁面使用點數折抵', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="min_cart_total"><?php _e('最低購物車金額', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="min_cart_total" name="wc_points_rewards_min_cart_total" 
                                       value="<?php echo esc_attr($settings['min_cart_total']); ?>" min="0" step="0.01">
                                <p class="description"><?php _e('購物車小計金額超過多少後才能開始使用點數（0表示無限制）', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="max_discount_percent"><?php _e('最大折抵比例', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="max_discount_percent" name="wc_points_rewards_max_discount_percent" 
                                       value="<?php echo esc_attr($settings['max_discount_percent']); ?>" min="1" max="100" step="1">
                                <p class="description"><?php _e('單筆消費訂單最多可以使用多少%比例的購物車小計金額來使用點數', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h2><?php _e('會員等級設定', 'wc-points-rewards'); ?></h2>
                
                <!-- 會員等級設定 -->
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="enable_tiers"><?php _e('啟用會員等級', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="enable_tiers" name="wc_points_rewards_enable_tiers" value="yes" 
                                       <?php checked($settings['enable_tiers'], 'yes'); ?>>
                                <p class="description"><?php _e('啟用會員等級系統', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="tier_period"><?php _e('等級計算週期', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <select id="tier_period" name="wc_points_rewards_tier_period">
                                    <option value="yearly" <?php selected($settings['tier_period'], 'yearly'); ?>><?php _e('年度', 'wc-points-rewards'); ?></option>
                                    <option value="lifetime" <?php selected($settings['tier_period'], 'lifetime'); ?>><?php _e('累計', 'wc-points-rewards'); ?></option>
                                </select>
                                <p class="description"><?php _e('會員等級的計算週期', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h2><?php _e('通知設定', 'wc-points-rewards'); ?></h2>
                
                <!-- 通知設定 -->
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="enable_notifications"><?php _e('啟用通知', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="enable_notifications" name="wc_points_rewards_enable_notifications" value="yes" 
                                       <?php checked($settings['enable_notifications'], 'yes'); ?>>
                                <p class="description"><?php _e('啟用點數相關的通知功能', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="expiry_notification_days"><?php _e('點數到期提醒', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="expiry_notification_days" name="wc_points_rewards_expiry_notification_days" 
                                       value="<?php echo esc_attr($settings['expiry_notification_days']); ?>" min="1" max="365">
                                <p class="description"><?php _e('在點數到期前幾天發送提醒', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="enable_birthday_notification"><?php _e('生日點數通知', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="enable_birthday_notification" name="wc_points_rewards_enable_birthday_notification" value="yes" 
                                       <?php checked($settings['enable_birthday_notification'], 'yes'); ?>>
                                <p class="description"><?php _e('發放生日點數時自動發送通知郵件', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php submit_button(__('儲存設定', 'wc-points-rewards')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * 獲取當前設定
     */
    private function get_current_settings() {
        return array(
            'enable_points_system' => get_option('wc_points_rewards_enable_points_system', 'yes'),
            'points_per_amount' => get_option('wc_points_rewards_points_per_amount', '1'),
            'points_name' => get_option('wc_points_rewards_points_name', '點'),
            'points_value' => get_option('wc_points_rewards_points_value', '1'),
            'points_expiry_months' => get_option('wc_points_rewards_points_expiry_months', '12'),
            'registration_points' => get_option('wc_points_rewards_registration_points', '100'),
            'birthday_points' => get_option('wc_points_rewards_birthday_points', '200'),
            
            // 購物車點數使用限制設定
            'enable_cart_redemption' => get_option('wc_points_rewards_enable_cart_redemption', 'yes'),
            'min_cart_total' => get_option('wc_points_rewards_min_cart_total', '0'),
            'max_discount_percent' => get_option('wc_points_rewards_max_discount_percent', '50'),
            
            'enable_tiers' => get_option('wc_points_rewards_enable_tiers', 'yes'),
            'tier_period' => get_option('wc_points_rewards_tier_period', 'yearly'),
            'enable_notifications' => get_option('wc_points_rewards_enable_notifications', 'yes'),
            'expiry_notification_days' => get_option('wc_points_rewards_expiry_notification_days', '30'),
            'enable_birthday_notification' => get_option('wc_points_rewards_enable_birthday_notification', 'yes'),
        );
    }
    
    /**
     * 儲存設定 - 改進輸入驗證和清理
     */
    public function save_settings() {
        // 驗證 nonce
        if (!wp_verify_nonce($_POST['points_rewards_nonce'], 'save_points_rewards_settings')) {
            wp_die(__('安全驗證失敗', 'wc-points-rewards'));
        }
        
        // 檢查權限
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('您沒有權限執行此操作', 'wc-points-rewards'));
        }
        
        // 定義設定驗證規則
        $settings_validation = array(
            'enable_points_system' => array('type' => 'checkbox', 'default' => 'yes'),
            'points_per_amount' => array('type' => 'positive_number', 'min' => 0.01, 'max' => 10000, 'default' => 1),
            'points_name' => array('type' => 'text', 'max_length' => 50, 'default' => '點'),
            'points_value' => array('type' => 'positive_number', 'min' => 0.01, 'max' => 1000, 'default' => 1),
            'points_expiry_months' => array('type' => 'integer', 'min' => 0, 'max' => 120, 'default' => 12),
            'registration_points' => array('type' => 'non_negative_number', 'min' => 0, 'max' => 999999, 'default' => 100),
            'birthday_points' => array('type' => 'non_negative_number', 'min' => 0, 'max' => 999999, 'default' => 100),
            'enable_cart_redemption' => array('type' => 'checkbox', 'default' => 'yes'),
            'min_cart_total' => array('type' => 'non_negative_number', 'min' => 0, 'max' => 999999, 'default' => 0),
            'max_discount_percent' => array('type' => 'non_negative_number', 'min' => 0, 'max' => 100, 'default' => 50),
            'enable_tiers' => array('type' => 'checkbox', 'default' => 'yes'),
            'tier_period' => array('type' => 'text', 'default' => 'yearly'),
            'enable_notifications' => array('type' => 'checkbox', 'default' => 'yes'),
            'expiry_notification_days' => array('type' => 'integer', 'min' => 1, 'max' => 365, 'default' => 30),
            'enable_birthday_notification' => array('type' => 'checkbox', 'default' => 'yes')
        );
        
        $errors = array();
        
        foreach ($settings_validation as $setting => $rules) {
            $post_key = 'wc_points_rewards_' . $setting;
            $raw_value = $_POST[$post_key] ?? '';
            
            switch ($rules['type']) {
                case 'checkbox':
                    $value = ($raw_value === 'yes') ? 'yes' : 'no';
                    break;
                    
                case 'text':
                    $value = sanitize_text_field($raw_value);
                    if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                        $value = substr($value, 0, $rules['max_length']);
                    }
                    break;
                    
                case 'integer':
                    $value = intval($raw_value);
                    if (isset($rules['min']) && $value < $rules['min']) {
                        $value = $rules['min'];
                        $errors[] = sprintf(__('%s 設定值過小，已調整為最小值 %d', 'wc-points-rewards'), $setting, $rules['min']);
                    }
                    if (isset($rules['max']) && $value > $rules['max']) {
                        $value = $rules['max'];
                        $errors[] = sprintf(__('%s 設定值過大，已調整為最大值 %d', 'wc-points-rewards'), $setting, $rules['max']);
                    }
                    break;
                    
                case 'positive_number':
                case 'non_negative_number':
                    $value = floatval($raw_value);
                    $min_value = ($rules['type'] === 'positive_number') ? max(0.01, $rules['min'] ?? 0.01) : ($rules['min'] ?? 0);
                    
                    if ($value < $min_value) {
                        $value = $min_value;
                        $errors[] = sprintf(__('%s 設定值過小，已調整為最小值 %s', 'wc-points-rewards'), $setting, $min_value);
                    }
                    if (isset($rules['max']) && $value > $rules['max']) {
                        $value = $rules['max'];
                        $errors[] = sprintf(__('%s 設定值過大，已調整為最大值 %s', 'wc-points-rewards'), $setting, $rules['max']);
                    }
                    break;
                    
                default:
                    $value = sanitize_text_field($raw_value);
            }
            
            // 如果值為空，使用預設值
            if (empty($value) && isset($rules['default'])) {
                $value = $rules['default'];
            }
            
            update_option('wc_points_rewards_' . $setting, $value);
        }
        
        // 記錄安全事件
        if (class_exists('WC_Points_Rewards_Security')) {
            $security = WC_Points_Rewards_Security::instance();
            $security->log_security_event('settings_updated', '管理員更新點數系統設定', get_current_user_id());
        }
        
        // 重定向回設定頁面，並顯示任何錯誤訊息
        $redirect_url = admin_url('admin.php?page=wc-points-rewards-settings&updated=1');
        if (!empty($errors)) {
            $redirect_url .= '&errors=' . urlencode(implode('|', $errors));
        }
        wp_redirect($redirect_url);
        exit;
    }
}