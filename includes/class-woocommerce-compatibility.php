<?php
/**
 * WooCommerce 相容性類別
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce 相容性管理類別
 */
class WC_Points_Rewards_WooCommerce_Compatibility {
    
    /**
     * 單例實例
     */
    private static $instance = null;
    
    /**
     * WooCommerce 版本
     */
    private $wc_version;
    
    /**
     * 相容性標記
     */
    private $compatibility_flags = array();
    
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
        $this->wc_version = $this->get_woocommerce_version();
        $this->init_compatibility_flags();
    }
    
    /**
     * 取得 WooCommerce 版本
     */
    private function get_woocommerce_version() {
        if (defined('WC_VERSION')) {
            return WC_VERSION;
        }
        
        if (defined('WOOCOMMERCE_VERSION')) {
            return WOOCOMMERCE_VERSION;
        }
        
        // 從外掛資料取得版本
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php');
        if (!empty($plugin_data['Version'])) {
            return $plugin_data['Version'];
        }
        
        return '3.0.0'; // 預設版本
    }
    
    /**
     * 初始化相容性標記
     */
    private function init_compatibility_flags() {
        $this->compatibility_flags = array(
            'has_blocks' => version_compare($this->wc_version, '5.5.0', '>='),
            'has_hpos' => version_compare($this->wc_version, '7.1.0', '>='),
            'has_new_cart_api' => version_compare($this->wc_version, '4.0.0', '>='),
            'has_session_handler' => version_compare($this->wc_version, '3.6.0', '>='),
            'supports_rest_api' => version_compare($this->wc_version, '3.5.0', '>='),
            'has_cart_fragments' => version_compare($this->wc_version, '2.6.0', '>='),
            'has_checkout_blocks' => version_compare($this->wc_version, '8.0.0', '>='),
        );
    }
    
    /**
     * 檢查特定功能是否支援
     */
    public function supports($feature) {
        return isset($this->compatibility_flags[$feature]) ? $this->compatibility_flags[$feature] : false;
    }
    
    /**
     * 取得 WooCommerce 版本
     */
    public function get_version() {
        return $this->wc_version;
    }
    
    /**
     * 檢查是否為最新版本
     */
    public function is_latest_version() {
        return version_compare($this->wc_version, '8.0.0', '>=');
    }
    
    /**
     * 檢查是否為舊版本
     */
    public function is_legacy_version() {
        return version_compare($this->wc_version, '4.0.0', '<');
    }
    
    /**
     * 取得相容的購物車 hooks
     */
    public function get_cart_hooks() {
        $hooks = array();
        
        // 主要的購物車 hook
        $hooks['cart_totals'] = 'woocommerce_cart_totals_after_order_total';
        
        // 根據版本添加額外的 hooks
        if ($this->supports('has_blocks')) {
            $hooks['cart_blocks'] = 'woocommerce_blocks_cart_after_order_total';
        }
        
        if (version_compare($this->wc_version, '3.0.0', '>=')) {
            $hooks['cart_collaterals'] = 'woocommerce_cart_collaterals';
        }
        
        return $hooks;
    }
    
    /**
     * 取得相容的結帳 hooks
     */
    public function get_checkout_hooks() {
        $hooks = array();
        
        // 主要的結帳 hook
        $hooks['review_order'] = 'woocommerce_review_order_after_order_total';
        
        // 根據版本添加額外的 hooks
        if ($this->supports('has_checkout_blocks')) {
            $hooks['checkout_blocks'] = 'woocommerce_blocks_checkout_after_order_total';
        }
        
        if (version_compare($this->wc_version, '3.0.0', '>=')) {
            $hooks['checkout_order_review'] = 'woocommerce_checkout_order_review';
        }
        
        return $hooks;
    }
    
    /**
     * 取得購物車實例的安全方法
     */
    public function get_cart() {
        if (!WC()->cart) {
            return null;
        }
        
        return WC()->cart;
    }
    
    /**
     * 取得工作階段的安全方法
     */
    public function get_session() {
        if (!WC()->session) {
            return null;
        }
        
        return WC()->session;
    }
    
    /**
     * 取得購物車小計的相容方法
     */
    public function get_cart_subtotal() {
        $cart = $this->get_cart();
        if (!$cart) {
            return 0;
        }
        
        // 新版本方法
        if (method_exists($cart, 'get_subtotal')) {
            return $cart->get_subtotal();
        }
        
        // 舊版本方法
        if (method_exists($cart, 'subtotal')) {
            return $cart->subtotal;
        }
        
        return 0;
    }
    
    /**
     * 添加購物車費用的相容方法
     */
    public function add_cart_fee($name, $amount, $taxable = false) {
        $cart = $this->get_cart();
        if (!$cart) {
            return false;
        }
        
        if (method_exists($cart, 'add_fee')) {
            return $cart->add_fee($name, $amount, $taxable);
        }
        
        return false;
    }
    
    /**
     * 取得客戶實例的安全方法
     */
    public function get_customer() {
        if (!WC()->customer) {
            return null;
        }
        
        return WC()->customer;
    }
    
    /**
     * 檢查是否在購物車頁面
     */
    public function is_cart() {
        if (function_exists('is_cart')) {
            return is_cart();
        }
        
        // 舊版本備用方法
        global $woocommerce;
        return $woocommerce && $woocommerce->cart && is_page(wc_get_page_id('cart'));
    }
    
    /**
     * 檢查是否在結帳頁面
     */
    public function is_checkout() {
        if (function_exists('is_checkout')) {
            return is_checkout();
        }
        
        // 舊版本備用方法
        global $woocommerce;
        return $woocommerce && is_page(wc_get_page_id('checkout'));
    }
    
    /**
     * 處理 AJAX 相容性
     */
    public function handle_ajax_compatibility() {
        // 確保 WooCommerce AJAX 正常工作
        if (!did_action('woocommerce_loaded')) {
            add_action('woocommerce_loaded', array($this, 'setup_ajax_handlers'));
        } else {
            $this->setup_ajax_handlers();
        }
    }
    
    /**
     * 設定 AJAX 處理器
     */
    public function setup_ajax_handlers() {
        // 為前端用戶和後端用戶都註冊 AJAX 處理器
        add_action('wp_ajax_wc_points_rewards_apply_discount', array($this, 'proxy_ajax_apply_discount'));
        add_action('wp_ajax_nopriv_wc_points_rewards_apply_discount', array($this, 'proxy_ajax_apply_discount'));
        add_action('wp_ajax_wc_points_rewards_remove_discount', array($this, 'proxy_ajax_remove_discount'));
        add_action('wp_ajax_nopriv_wc_points_rewards_remove_discount', array($this, 'proxy_ajax_remove_discount'));
    }
    
    /**
     * 代理 AJAX 應用折扣
     */
    public function proxy_ajax_apply_discount() {
        if (class_exists('WC_Points_Rewards_Checkout')) {
            $checkout = WC_Points_Rewards_Checkout::instance();
            if (method_exists($checkout, 'ajax_apply_points_discount')) {
                $checkout->ajax_apply_points_discount();
            }
        }
    }
    
    /**
     * 代理 AJAX 移除折扣
     */
    public function proxy_ajax_remove_discount() {
        if (class_exists('WC_Points_Rewards_Checkout')) {
            $checkout = WC_Points_Rewards_Checkout::instance();
            if (method_exists($checkout, 'ajax_remove_points_discount')) {
                $checkout->ajax_remove_points_discount();
            }
        }
    }
    
    /**
     * 取得相容的貨幣符號
     */
    public function get_currency_symbol($currency = '') {
        if (function_exists('get_woocommerce_currency_symbol')) {
            return get_woocommerce_currency_symbol($currency);
        }
        
        // 舊版本備用方法
        if (empty($currency)) {
            $currency = get_woocommerce_currency();
        }
        
        $symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'TWD' => 'NT$',
            'CNY' => '¥',
            'JPY' => '¥',
        );
        
        return isset($symbols[$currency]) ? $symbols[$currency] : $currency;
    }
    
    /**
     * 格式化價格的相容方法
     */
    public function format_price($price, $args = array()) {
        if (function_exists('wc_price')) {
            return wc_price($price, $args);
        }
        
        // 舊版本備用方法
        if (function_exists('woocommerce_price')) {
            return woocommerce_price($price);
        }
        
        // 最基本的格式化
        return $this->get_currency_symbol() . number_format($price, 2);
    }
}