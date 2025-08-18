<?php
/**
 * WooCommerce Points & Rewards - Compatibility Verification
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 相容性驗證類別
 */
class WC_Points_Rewards_Compatibility_Verifier {
    
    /**
     * 驗證 WooCommerce 相容性
     */
    public static function verify_compatibility() {
        $results = array(
            'status' => 'success',
            'message' => '',
            'details' => array()
        );
        
        try {
            // 檢查 WooCommerce 是否存在
            if (!class_exists('WooCommerce')) {
                throw new Exception(__('WooCommerce 未安裝或未啟用', 'wc-points-rewards'));
            }
            
            // 檢查相容性類別
            if (!class_exists('WC_Points_Rewards_WooCommerce_Compatibility')) {
                throw new Exception(__('WooCommerce 相容性類別未載入', 'wc-points-rewards'));
            }
            
            $compat = WC_Points_Rewards_WooCommerce_Compatibility::instance();
            
            // 檢查 WooCommerce 版本
            $wc_version = $compat->get_version();
            $results['details']['wc_version'] = $wc_version;
            $results['details']['is_latest'] = $compat->is_latest_version();
            $results['details']['is_legacy'] = $compat->is_legacy_version();
            
            // 檢查支援的功能
            $features = array(
                'has_blocks' => $compat->supports('has_blocks'),
                'has_hpos' => $compat->supports('has_hpos'),
                'has_new_cart_api' => $compat->supports('has_new_cart_api'),
                'has_session_handler' => $compat->supports('has_session_handler'),
                'supports_rest_api' => $compat->supports('supports_rest_api'),
                'has_cart_fragments' => $compat->supports('has_cart_fragments'),
                'has_checkout_blocks' => $compat->supports('has_checkout_blocks')
            );
            $results['details']['features'] = $features;
            
            // 檢查 hooks
            $cart_hooks = $compat->get_cart_hooks();
            $checkout_hooks = $compat->get_checkout_hooks();
            $results['details']['cart_hooks'] = $cart_hooks;
            $results['details']['checkout_hooks'] = $checkout_hooks;
            
            // 檢查重要實例
            $cart = $compat->get_cart();
            $session = $compat->get_session();
            $customer = $compat->get_customer();
            
            $results['details']['instances'] = array(
                'cart_available' => !is_null($cart),
                'session_available' => !is_null($session),
                'customer_available' => !is_null($customer)
            );
            
            // 檢查頁面偵測
            $results['details']['page_detection'] = array(
                'is_cart' => $compat->is_cart(),
                'is_checkout' => $compat->is_checkout()
            );
            
            $results['message'] = sprintf(
                __('WooCommerce %s 相容性驗證成功', 'wc-points-rewards'),
                $wc_version
            );
            
        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['message'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * 驗證前端資源
     */
    public static function verify_frontend_assets() {
        $results = array(
            'status' => 'success',
            'message' => '',
            'details' => array()
        );
        
        try {
            // 檢查 CSS 檔案
            $css_file = WC_POINTS_REWARDS_PLUGIN_DIR . 'assets/css/frontend.css';
            $js_file = WC_POINTS_REWARDS_PLUGIN_DIR . 'assets/js/frontend.js';
            
            $results['details']['css_exists'] = file_exists($css_file);
            $results['details']['js_exists'] = file_exists($js_file);
            
            if (!file_exists($css_file)) {
                throw new Exception(__('前端 CSS 檔案不存在', 'wc-points-rewards'));
            }
            
            if (!file_exists($js_file)) {
                throw new Exception(__('前端 JavaScript 檔案不存在', 'wc-points-rewards'));
            }
            
            // 檢查檔案大小
            $results['details']['css_size'] = filesize($css_file);
            $results['details']['js_size'] = filesize($js_file);
            
            // 檢查模板檔案
            $template_file = WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/views/cart-points-section-enhanced.php';
            $results['details']['template_exists'] = file_exists($template_file);
            
            if (!file_exists($template_file)) {
                throw new Exception(__('增強版模板檔案不存在', 'wc-points-rewards'));
            }
            
            $results['message'] = __('前端資源驗證成功', 'wc-points-rewards');
            
        } catch (Exception $e) {
            $results['status'] = 'error';
            $results['message'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * 取得驗證報告
     */
    public static function get_verification_report() {
        $compat_check = self::verify_compatibility();
        $assets_check = self::verify_frontend_assets();
        
        return array(
            'compatibility' => $compat_check,
            'frontend_assets' => $assets_check,
            'overall_status' => ($compat_check['status'] === 'success' && $assets_check['status'] === 'success') ? 'success' : 'error'
        );
    }
    
    /**
     * 顯示驗證報告（僅供管理員）
     */
    public static function display_verification_report() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $report = self::get_verification_report();
        
        echo '<div class="wc-points-rewards-verification-report">';
        echo '<h3>' . __('WooCommerce Points & Rewards 相容性驗證報告', 'wc-points-rewards') . '</h3>';
        
        // 整體狀態
        $status_class = $report['overall_status'] === 'success' ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $status_class . '">';
        echo '<p><strong>' . __('整體狀態', 'wc-points-rewards') . ':</strong> ';
        echo $report['overall_status'] === 'success' ? __('通過', 'wc-points-rewards') : __('失敗', 'wc-points-rewards');
        echo '</p></div>';
        
        // 相容性檢查
        echo '<h4>' . __('WooCommerce 相容性', 'wc-points-rewards') . '</h4>';
        echo '<p><strong>' . __('狀態', 'wc-points-rewards') . ':</strong> ' . $report['compatibility']['message'] . '</p>';
        
        if (isset($report['compatibility']['details']['wc_version'])) {
            echo '<p><strong>' . __('WooCommerce 版本', 'wc-points-rewards') . ':</strong> ' . $report['compatibility']['details']['wc_version'] . '</p>';
        }
        
        // 前端資源檢查
        echo '<h4>' . __('前端資源', 'wc-points-rewards') . '</h4>';
        echo '<p><strong>' . __('狀態', 'wc-points-rewards') . ':</strong> ' . $report['frontend_assets']['message'] . '</p>';
        
        echo '</div>';
    }
}