<?php
/**
 * License Product Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Millennium_License_Product {
    
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
        // 新增產品設定標籤
        add_filter('woocommerce_product_data_tabs', array($this, 'add_product_data_tab'));
        
        // 新增產品設定面板
        add_action('woocommerce_product_data_panels', array($this, 'add_product_data_panel'));
        
        // 儲存產品設定
        add_action('woocommerce_process_product_meta', array($this, 'save_product_data'));
        
        // 在產品頁面顯示授權資訊
        add_action('woocommerce_single_product_summary', array($this, 'display_license_info'), 25);
    }
    
    /**
     * 新增產品設定標籤
     */
    public function add_product_data_tab($tabs) {
        $tabs['millennium_license'] = array(
            'label' => __('授權設定', 'millennium-license'),
            'target' => 'millennium_license_data',
            'class' => array('show_if_simple', 'show_if_variable'),
        );
        
        return $tabs;
    }
    
    /**
     * 新增產品設定面板
     */
    public function add_product_data_panel() {
        global $post;
        
        $enable_license = get_post_meta($post->ID, '_millennium_license_enable', true);
        $max_activations = get_post_meta($post->ID, '_millennium_license_max_activations', true);
        $expiry_days = get_post_meta($post->ID, '_millennium_license_expiry_days', true);
        $license_quantity = get_post_meta($post->ID, '_millennium_license_quantity', true);
        
        ?>
        <div id="millennium_license_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                woocommerce_wp_checkbox(array(
                    'id' => '_millennium_license_enable',
                    'label' => __('啟用授權功能', 'millennium-license'),
                    'description' => __('為此產品啟用授權碼生成', 'millennium-license'),
                    'value' => $enable_license === 'yes' ? 'yes' : 'no',
                ));
                
                woocommerce_wp_text_input(array(
                    'id' => '_millennium_license_max_activations',
                    'label' => __('最大啟用次數', 'millennium-license'),
                    'description' => __('每個授權碼可啟用的次數', 'millennium-license'),
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => '1',
                        'min' => '1',
                    ),
                    'value' => $max_activations ? $max_activations : '1',
                ));
                
                woocommerce_wp_text_input(array(
                    'id' => '_millennium_license_expiry_days',
                    'label' => __('有效期限（天）', 'millennium-license'),
                    'description' => __('授權碼的有效天數，留空表示永久', 'millennium-license'),
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => '1',
                        'min' => '0',
                    ),
                    'value' => $expiry_days,
                ));
                
                woocommerce_wp_text_input(array(
                    'id' => '_millennium_license_quantity',
                    'label' => __('授權碼數量', 'millennium-license'),
                    'description' => __('每次購買生成的授權碼數量', 'millennium-license'),
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => '1',
                        'min' => '1',
                    ),
                    'value' => $license_quantity ? $license_quantity : '1',
                ));
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * 儲存產品設定
     */
    public function save_product_data($post_id) {
        $enable_license = isset($_POST['_millennium_license_enable']) ? 'yes' : 'no';
        update_post_meta($post_id, '_millennium_license_enable', $enable_license);
        
        if (isset($_POST['_millennium_license_max_activations'])) {
            update_post_meta($post_id, '_millennium_license_max_activations', intval($_POST['_millennium_license_max_activations']));
        }
        
        if (isset($_POST['_millennium_license_expiry_days'])) {
            update_post_meta($post_id, '_millennium_license_expiry_days', intval($_POST['_millennium_license_expiry_days']));
        }
        
        if (isset($_POST['_millennium_license_quantity'])) {
            update_post_meta($post_id, '_millennium_license_quantity', intval($_POST['_millennium_license_quantity']));
        }
    }
    
    /**
     * 在產品頁面顯示授權資訊
     */
    public function display_license_info() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $enable_license = get_post_meta($product->get_id(), '_millennium_license_enable', true);
        
        if ($enable_license !== 'yes') {
            return;
        }
        
        $max_activations = get_post_meta($product->get_id(), '_millennium_license_max_activations', true);
        $expiry_days = get_post_meta($product->get_id(), '_millennium_license_expiry_days', true);
        $license_quantity = get_post_meta($product->get_id(), '_millennium_license_quantity', true);
        
        ?>
        <div class="millennium-license-info">
            <h3><?php _e('授權資訊', 'millennium-license'); ?></h3>
            <ul>
                <?php if ($license_quantity && $license_quantity > 1) : ?>
                    <li><?php printf(__('包含 %d 個授權碼', 'millennium-license'), $license_quantity); ?></li>
                <?php else : ?>
                    <li><?php _e('包含 1 個授權碼', 'millennium-license'); ?></li>
                <?php endif; ?>
                
                <?php if ($max_activations) : ?>
                    <li><?php printf(__('每個授權碼可啟用 %d 次', 'millennium-license'), $max_activations); ?></li>
                <?php endif; ?>
                
                <?php if ($expiry_days) : ?>
                    <li><?php printf(__('有效期限：%d 天', 'millennium-license'), $expiry_days); ?></li>
                <?php else : ?>
                    <li><?php _e('有效期限：永久', 'millennium-license'); ?></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }
    
    /**
     * 檢查產品是否啟用授權功能
     */
    public static function is_license_product($product_id) {
        return get_post_meta($product_id, '_millennium_license_enable', true) === 'yes';
    }
    
    /**
     * 獲取產品的授權設定
     */
    public static function get_license_settings($product_id) {
        return array(
            'enabled' => get_post_meta($product_id, '_millennium_license_enable', true) === 'yes',
            'max_activations' => get_post_meta($product_id, '_millennium_license_max_activations', true),
            'expiry_days' => get_post_meta($product_id, '_millennium_license_expiry_days', true),
            'quantity' => get_post_meta($product_id, '_millennium_license_quantity', true),
        );
    }
}
