<?php
/**
 * License Order Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Millennium_License_Order {
    
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
        // 訂單完成時生成授權碼
        add_action('woocommerce_order_status_completed', array($this, 'generate_licenses_on_order_complete'));
        
        // 在訂單詳情頁面顯示授權碼
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_licenses_in_order'));
        
        // 在我的帳戶頁面顯示授權碼
        add_action('woocommerce_account_dashboard', array($this, 'display_licenses_in_account'));
        
        // 新增我的帳戶端點
        add_action('init', array($this, 'add_account_endpoint'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_account_menu_item'));
        add_action('woocommerce_account_licenses_endpoint', array($this, 'render_account_licenses_page'));
        
        // 在訂單郵件中包含授權碼
        add_action('woocommerce_email_order_meta', array($this, 'add_licenses_to_order_email'), 10, 4);
    }
    
    /**
     * 訂單完成時生成授權碼
     */
    public function generate_licenses_on_order_complete($order_id) {
        // 使用 HPOS 相容方式獲取訂單
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // 檢查是否已經生成過授權碼
        $existing_licenses = $order->get_meta('_millennium_license_keys');
        if ($existing_licenses) {
            return;
        }
        
        $manager = Millennium_License_Manager_Core::instance();
        $license_keys = array();
        
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            
            // 檢查產品是否啟用授權功能
            if (!Millennium_License_Product::is_license_product($product_id)) {
                continue;
            }
            
            $settings = Millennium_License_Product::get_license_settings($product_id);
            $quantity = $item->get_quantity();
            $licenses_per_item = $settings['quantity'] ? intval($settings['quantity']) : 1;
            $total_licenses = $quantity * $licenses_per_item;
            
            // 計算過期時間
            $expires_at = null;
            if ($settings['expiry_days']) {
                $expires_at = date('Y-m-d H:i:s', strtotime("+{$settings['expiry_days']} days"));
            }
            
            // 生成授權碼
            for ($i = 0; $i < $total_licenses; $i++) {
                $license = $manager->create_license(array(
                    'product_id' => $product_id,
                    'order_id' => $order_id,
                    'user_id' => $order->get_user_id(),
                    'max_activations' => $settings['max_activations'] ? intval($settings['max_activations']) : 1,
                    'expires_at' => $expires_at,
                ));
                
                if ($license) {
                    $license_keys[] = array(
                        'product_id' => $product_id,
                        'product_name' => $item->get_name(),
                        'license_key' => $license->license_key,
                        'license_id' => $license->id,
                    );
                }
            }
        }
        
        // 儲存授權碼到訂單 meta
        if (!empty($license_keys)) {
            $order->update_meta_data('_millennium_license_keys', $license_keys);
            $order->save();
            
            // 發送授權碼郵件
            $this->send_license_email($order, $license_keys);
        }
    }
    
    /**
     * 在訂單詳情頁面顯示授權碼
     */
    public function display_licenses_in_order($order) {
        $license_keys = $order->get_meta('_millennium_license_keys');
        
        if (empty($license_keys)) {
            return;
        }
        
        ?>
        <section class="millennium-licenses-section">
            <h2><?php _e('您的授權碼', 'millennium-license'); ?></h2>
            <table class="millennium-licenses-table">
                <thead>
                    <tr>
                        <th><?php _e('產品', 'millennium-license'); ?></th>
                        <th><?php _e('授權碼', 'millennium-license'); ?></th>
                        <th><?php _e('狀態', 'millennium-license'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($license_keys as $license_data) : 
                        $manager = Millennium_License_Manager_Core::instance();
                        $license = $manager->get_license($license_data['license_id']);
                    ?>
                        <tr>
                            <td><?php echo esc_html($license_data['product_name']); ?></td>
                            <td><code class="license-key"><?php echo esc_html($license_data['license_key']); ?></code></td>
                            <td>
                                <?php 
                                if ($license) {
                                    echo esc_html($this->get_license_status_label($license->status));
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php
    }
    
    /**
     * 在我的帳戶儀表板顯示授權碼摘要
     */
    public function display_licenses_in_account() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        
        $manager = Millennium_License_Manager_Core::instance();
        $licenses = $manager->get_licenses(array(
            'user_id' => $user_id,
            'status' => 'active',
            'limit' => 5,
        ));
        
        if (empty($licenses)) {
            return;
        }
        
        ?>
        <div class="millennium-licenses-dashboard">
            <h2><?php _e('我的授權碼', 'millennium-license'); ?></h2>
            <p>
                <?php 
                printf(
                    __('您有 %d 個啟用的授權碼。', 'millennium-license'),
                    count($licenses)
                );
                ?>
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('licenses')); ?>">
                    <?php _e('查看全部', 'millennium-license'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * 新增我的帳戶端點
     */
    public function add_account_endpoint() {
        add_rewrite_endpoint('licenses', EP_ROOT | EP_PAGES);
    }
    
    /**
     * 新增我的帳戶選單項目
     */
    public function add_account_menu_item($items) {
        $new_items = array();
        
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            
            // 在訂單之後插入授權碼選單
            if ($key === 'orders') {
                $new_items['licenses'] = __('授權碼', 'millennium-license');
            }
        }
        
        return $new_items;
    }
    
    /**
     * 顯示我的帳戶授權碼頁面
     */
    public function render_account_licenses_page() {
        $user_id = get_current_user_id();
        $manager = Millennium_License_Manager_Core::instance();
        
        $licenses = $manager->get_licenses(array(
            'user_id' => $user_id,
            'limit' => 50,
        ));
        
        include MILLENNIUM_LICENSE_PLUGIN_DIR . 'templates/myaccount/licenses.php';
    }
    
    /**
     * 在訂單郵件中包含授權碼
     */
    public function add_licenses_to_order_email($order, $sent_to_admin, $plain_text, $email) {
        if ($sent_to_admin) {
            return;
        }
        
        $license_keys = $order->get_meta('_millennium_license_keys');
        
        if (empty($license_keys)) {
            return;
        }
        
        if ($plain_text) {
            echo "\n" . __('您的授權碼：', 'millennium-license') . "\n";
            foreach ($license_keys as $license_data) {
                echo $license_data['product_name'] . ': ' . $license_data['license_key'] . "\n";
            }
        } else {
            ?>
            <h2><?php _e('您的授權碼', 'millennium-license'); ?></h2>
            <table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee;">
                <thead>
                    <tr>
                        <th style="text-align: left; border: 1px solid #eee;"><?php _e('產品', 'millennium-license'); ?></th>
                        <th style="text-align: left; border: 1px solid #eee;"><?php _e('授權碼', 'millennium-license'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($license_keys as $license_data) : ?>
                        <tr>
                            <td style="text-align: left; border: 1px solid #eee;"><?php echo esc_html($license_data['product_name']); ?></td>
                            <td style="text-align: left; border: 1px solid #eee;"><code><?php echo esc_html($license_data['license_key']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        }
    }
    
    /**
     * 發送授權碼郵件
     */
    private function send_license_email($order, $license_keys) {
        $settings = get_option('millennium_license_settings', array());
        
        if (!isset($settings['enable_email_notifications']) || $settings['enable_email_notifications'] !== 'yes') {
            return;
        }
        
        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        $subject = sprintf(__('您的授權碼 - 訂單 #%s', 'millennium-license'), $order->get_order_number());
        
        ob_start();
        $license = (object) array(
            'order' => $order,
            'license_keys' => $license_keys,
        );
        include MILLENNIUM_LICENSE_PLUGIN_DIR . 'templates/emails/license-key-email.php';
        $message = ob_get_clean();
        
        $result = wp_mail($user->user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
        
        // 記錄郵件發送失敗
        if (!$result) {
            error_log(sprintf('Millennium License: Failed to send license email for order #%s to %s', $order->get_order_number(), $user->user_email));
        }
    }
    
    /**
     * 獲取授權狀態標籤
     */
    private function get_license_status_label($status) {
        $labels = array(
            'active' => __('啟用', 'millennium-license'),
            'inactive' => __('停用', 'millennium-license'),
            'expired' => __('已過期', 'millennium-license'),
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
}
