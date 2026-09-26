<?php
/**
 * 結帳頁面類別
 *
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 結帳頁面類別
 */
class WC_Points_Rewards_Checkout {

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
        add_action('woocommerce_cart_totals_before_order_total',  array($this, 'display_cart_points_section'));
        add_action('woocommerce_review_order_before_order_total', array($this, 'display_checkout_points_section'));
        add_action('woocommerce_cart_calculate_fees',             array($this, 'apply_points_discount'));
        add_action('woocommerce_checkout_order_processed',        array($this, 'record_points_usage'), 10, 2);

        add_action('wp_ajax_wc_points_rewards_apply_discount',         array($this, 'ajax_apply_points_discount'));
        add_action('wp_ajax_wc_points_rewards_remove_discount',        array($this, 'ajax_remove_points_discount'));
        add_action('wp_ajax_nopriv_wc_points_rewards_apply_discount',  array($this, 'ajax_apply_points_discount'));
        add_action('wp_ajax_nopriv_wc_points_rewards_remove_discount', array($this, 'ajax_remove_points_discount'));

        add_action('woocommerce_cart_updated',        array($this, 'validate_points_usage'));
        add_action('woocommerce_after_calculate_totals', array($this, 'ensure_discount_applied'));
    }

    /**
     * 在購物車顯示點數使用區塊
     */
    public function display_cart_points_section() {
        if (!is_user_logged_in()) {
            return;
        }
        $this->render_points_section('cart');
    }

    /**
     * 在結帳頁面顯示點數使用區塊
     */
    public function display_checkout_points_section() {
        if (!is_user_logged_in()) {
            return;
        }
        $this->render_points_section('checkout');
    }

    /**
     * 渲染點數使用區塊
     */
    private function render_points_section($context = 'cart') {
        if (!function_exists('WC') || !WC()->cart) {
            return;
        }

        $user_id    = get_current_user_id();
        $database   = WC_Points_Rewards_Database::instance();
        $calculator = WC_Points_Rewards_Points_Calculator::instance();

        if (!wc_points_rewards_is_enabled()) {
            return;
        }

        $enable_cart_redemption = get_option('wc_points_rewards_enable_cart_redemption', 'yes');
        if ($enable_cart_redemption !== 'yes') {
            return;
        }

        if ($context === 'checkout') {
            include WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/views/checkout-points-section.php';
            return;
        }

        $available_points = $database->get_user_points($user_id);
        $cart_total       = WC()->cart->get_subtotal();
        $min_cart_total   = floatval(get_option('wc_points_rewards_min_cart_total', '0'));

        if ($cart_total < $min_cart_total) {
            if ($min_cart_total > 0) {
                echo '<tr class="points-requirements"><td colspan="2"><div class="wc-points-message wc-points-info">';
                echo sprintf(
                    esc_html__('購物車滿 %s 即可使用點數折抵', 'wc-points-rewards'),
                    wp_kses_post(wc_price($min_cart_total))
                );
                echo '</div></td></tr>';
            }
            return;
        }

        if ($available_points <= 0) {
            echo '<tr class="points-no-balance"><td colspan="2"><div class="wc-points-message wc-points-info">';
            echo esc_html__('您目前沒有可用的點數', 'wc-points-rewards');
            echo '</div></td></tr>';
            return;
        }

        $max_discount_percent  = floatval(get_option('wc_points_rewards_max_discount_percent', '100'));
        $max_discount_amount   = ($cart_total * $max_discount_percent) / 100;
        $point_value           = wc_points_rewards_get_points_value();
        $max_points_by_amount  = $max_discount_amount / $point_value;
        $decimal_places        = wc_get_price_decimals();

        if ($decimal_places > 0) {
            $scale                = pow(10, $decimal_places);
            $max_points_by_amount = floor($max_points_by_amount * $scale) / $scale;
        } else {
            $max_points_by_amount = floor($max_points_by_amount);
        }

        $max_points      = min($available_points, $max_points_by_amount);
        $current_discount = WC()->session->get('wc_points_rewards_discount_amount', 0);
        $max_usable_points = $max_points;

        include WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/views/cart-points-section.php';
    }

    /**
     * 應用點數折扣
     */
    public function apply_points_discount() {
        if (!is_admin()) {
            if (!function_exists('WC') || !WC()->session || !WC()->cart) {
                return;
            }

            $discount_amount = WC()->session->get('wc_points_rewards_discount_amount', 0);

            if ($discount_amount > 0) {
                $calculator    = WC_Points_Rewards_Points_Calculator::instance();
                $discount_value = $calculator->calculate_discount_amount($discount_amount);

                $fees = WC()->cart->get_fees();
                $discount_already_applied = false;

                foreach ($fees as $fee) {
                    if ($fee->name === __('點數折抵', 'wc-points-rewards')) {
                        $discount_already_applied = true;
                        break;
                    }
                }

                if (!$discount_already_applied) {
                    WC()->cart->add_fee(
                        __('點數折抵', 'wc-points-rewards'),
                        -$discount_value,
                        false
                    );
                }
            }
        }
    }

    /**
     * 記錄點數使用
     *
     * [修正 LOGIC-7] 原本在 woocommerce_checkout_order_processed 鉤子拋出 Exception 會導致
     * 已建立的訂單留在不確定狀態。改為：
     * 1. 點數扣除失敗時，僅加入訂單備註 + 前台通知，不拋出例外
     * 2. 清除 session 中的點數使用資訊，避免後續請求重複嘗試扣除
     */
    public function record_points_usage($order_id, $posted_data) {
        $discount_amount = WC()->session->get('wc_points_rewards_discount_amount', 0);

        if ($discount_amount <= 0) {
            return;
        }

        $order   = wc_get_order($order_id);
        $user_id = $order ? $order->get_user_id() : 0;

        if (!$order || !$user_id) {
            WC()->session->__unset('wc_points_rewards_discount_amount');
            return;
        }

        $database   = WC_Points_Rewards_Database::instance();
        $calculator = WC_Points_Rewards_Points_Calculator::instance();

        $description = sprintf(__('訂單 #%s 使用點數折抵', 'wc-points-rewards'), $order->get_order_number());
        $result = $database->deduct_points_with_lock(
            $user_id,
            $discount_amount,
            'redeemed',
            $description,
            $order_id
        );

        if (!is_wp_error($result)) {
            $discount_value = $calculator->calculate_discount_amount($discount_amount);

            // [修正 L5-HPOS] 改用 HPOS-safe update_meta_data
            $order->update_meta_data('_points_discount_amount', $discount_value);
            $order->update_meta_data('_points_used', $discount_amount);
            $order->save();
        } else {
            // [修正 LOGIC-7] 不拋出 Exception，僅記錄並通知，不中斷訂單流程
            WC()->session->__unset('wc_points_rewards_discount_amount');

            $order->add_order_note(sprintf(
                __('⚠ 點數折抵未完成：%s', 'wc-points-rewards'),
                $result->get_error_message()
            ));

            if (class_exists('WC_Points_Rewards_Security')) {
                WC_Points_Rewards_Security::instance()->log_security_event(
                    'checkout_points_deduction_failed',
                    sprintf('訂單 %1$d 點數扣除失敗：%2$s', $order_id, $result->get_error_message()),
                    $user_id
                );
            }

            wc_add_notice(
                sprintf(
                    __('點數折抵未能完成（%s），訂單仍已成立，請聯繫客服補辦點數。', 'wc-points-rewards'),
                    esc_html($result->get_error_message())
                ),
                'error'
            );
            // [修正 LOGIC-7] 不再 throw new Exception，避免破壞已建立的訂單
        }

        WC()->session->__unset('wc_points_rewards_discount_amount');
    }

    /**
     * AJAX: 應用點數折扣
     */
    public function ajax_apply_points_discount() {
        try {
            check_ajax_referer('wc_points_rewards_nonce', 'nonce');
        } catch (Exception $e) {
            error_log('WC Points Rewards: Nonce驗證失敗 - ' . $e->getMessage());
            wp_send_json_error(__('安全驗證失敗，請重新整理頁面再試', 'wc-points-rewards'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('請先登入', 'wc-points-rewards'));
        }

        $points_to_use = floatval(isset($_POST['points']) ? $_POST['points'] : 0);

        if ($points_to_use <= 0) {
            wp_send_json_error(__('請輸入有效的點數', 'wc-points-rewards'));
        }

        if ($points_to_use > 999999999.99) {
            wp_send_json_error(__('點數數值過大', 'wc-points-rewards'));
        }

        try {
            if (!class_exists('WooCommerce') || !function_exists('WC') || !WC()->cart) {
                wp_send_json_error(__('購物車未初始化，請重新整理頁面', 'wc-points-rewards'));
            }

            if (!WC()->session || !WC()->session->get_customer_id()) {
                if (method_exists(WC()->session, 'init')) {
                    WC()->session->init();
                }
            }

            $user_id   = get_current_user_id();
            $database  = WC_Points_Rewards_Database::instance();
            $calculator = WC_Points_Rewards_Points_Calculator::instance();

            $available_points = $database->get_user_points($user_id);
            if ($points_to_use > $available_points) {
                wp_send_json_error(__('點數不足', 'wc-points-rewards'));
            }

            $cart_total   = WC()->cart->get_subtotal();
            $can_use_points = $calculator->can_use_points($cart_total, $points_to_use);

            if (!$can_use_points && current_user_can('manage_woocommerce')) {
                $settings = get_option('wc_points_rewards_settings', array());
                if (!empty($settings['allow_admin_override']) && $settings['allow_admin_override'] === 'yes') {
                    $can_use_points = true;
                }
            }

            if (!$can_use_points) {
                $min_cart_total      = floatval(get_option('wc_points_rewards_min_cart_total', '0'));
                $max_discount_percent = floatval(get_option('wc_points_rewards_max_discount_percent', '100'));
                $discount_amount     = $calculator->calculate_discount_amount($points_to_use);
                $max_discount_amount = ($cart_total * $max_discount_percent) / 100;

                $error_message = __('不符合點數使用條件', 'wc-points-rewards');

                if ($cart_total < $min_cart_total) {
                    $error_message = sprintf(__('購物車金額須達 %s 才能使用點數', 'wc-points-rewards'), wc_price($min_cart_total));
                } elseif ($discount_amount > $max_discount_amount) {
                    $error_message = sprintf(__('最多只能折抵 %s%% 的金額（%s）', 'wc-points-rewards'), $max_discount_percent, wc_price($max_discount_amount));
                }

                wp_send_json_error($error_message);
            }

            WC()->session->set('wc_points_rewards_discount_amount', $points_to_use);

            $discount_value = $calculator->calculate_discount_amount($points_to_use);

            $fees = WC()->cart->get_fees();
            $discount_found = false;

            foreach ($fees as $fee) {
                if ($fee->name === __('點數折抵', 'wc-points-rewards')) {
                    $discount_found = true;
                    break;
                }
            }

            if (!$discount_found) {
                WC()->cart->add_fee(
                    __('點數折抵', 'wc-points-rewards'),
                    -$discount_value,
                    false
                );
            }

            if (WC()->cart) {
                WC()->cart->calculate_totals();
            }

            wp_send_json_success(array(
                'message' => sprintf(
                    __('已使用 %s 點數，折抵 %s', 'wc-points-rewards'),
                    wc_points_rewards_number_format($points_to_use),
                    wc_price($discount_value)
                ),
                'discount_amount' => $discount_value,
                'points_used'     => $points_to_use,
                'reload_cart'     => true,
            ));

        } catch (Exception $e) {
            error_log('WC Points Rewards: AJAX處理錯誤 - ' . $e->getMessage());
            wp_send_json_error(__('系統發生錯誤，請稍後再試或聯繫管理員', 'wc-points-rewards'));
        }
    }

    /**
     * AJAX: 移除點數折扣
     */
    public function ajax_remove_points_discount() {
        check_ajax_referer('wc_points_rewards_nonce', 'nonce');

        if (!class_exists('WooCommerce') || !function_exists('WC') || !WC()->session) {
            wp_send_json_error(__('購物車未初始化，請重新整理頁面', 'wc-points-rewards'));
        }

        if (!WC()->session->get_customer_id()) {
            if (method_exists(WC()->session, 'init')) {
                WC()->session->init();
            }
        }

        WC()->session->__unset('wc_points_rewards_discount_amount');

        if (WC()->cart) {
            WC()->cart->calculate_totals();
        }

        wp_send_json_success(array(
            'message'     => __('已移除點數折抵', 'wc-points-rewards'),
            'reload_cart' => true,
        ));
    }

    /**
     * 驗證點數使用
     */
    public function validate_points_usage() {
        if (!function_exists('WC') || !WC()->session || !WC()->cart) {
            return;
        }

        $discount_amount = WC()->session->get('wc_points_rewards_discount_amount', 0);

        if ($discount_amount > 0) {
            $user_id = get_current_user_id();
            if (!$user_id) {
                WC()->session->__unset('wc_points_rewards_discount_amount');
                return;
            }

            $database   = WC_Points_Rewards_Database::instance();
            $calculator = WC_Points_Rewards_Points_Calculator::instance();

            $available_points = $database->get_user_points($user_id);
            $cart_total       = WC()->cart->get_subtotal();

            if ($discount_amount > $available_points || !$calculator->can_use_points($cart_total, $discount_amount)) {
                WC()->session->__unset('wc_points_rewards_discount_amount');
                wc_add_notice(__('您的點數使用已自動調整', 'wc-points-rewards'), 'notice');
            }
        }
    }

    /**
     * 確保在購物車計算後點數折扣有被正確應用
     *
     * [修正 LOGIC-6] 原本直接存取 WC()->cart->fees（protected 屬性），
     * 改用 WC()->cart->calculate_totals() 觸發重新計算以移除舊費用，
     * 並透過 woocommerce_cart_calculate_fees 鉤子重新添加，避免直接操作 protected 屬性
     */
    public function ensure_discount_applied() {
        if (!function_exists('WC') || !WC()->session || !WC()->cart) {
            return;
        }

        $discount_amount = WC()->session->get('wc_points_rewards_discount_amount', 0);

        if ($discount_amount <= 0) {
            return;
        }

        $calculator    = WC_Points_Rewards_Points_Calculator::instance();
        $discount_value = $calculator->calculate_discount_amount($discount_amount);

        $fees = WC()->cart->get_fees();
        $discount_found   = false;
        $amount_incorrect = false;

        foreach ($fees as $fee) {
            if ($fee->name === __('點數折抵', 'wc-points-rewards')) {
                $discount_found = true;
                // 檢查金額是否正確（允許 0.01 誤差）
                if (abs($fee->amount + $discount_value) > 0.01) {
                    $amount_incorrect = true;
                }
                break;
            }
        }

        if (!$discount_found) {
            // 折扣不存在，直接添加
            WC()->cart->add_fee(__('點數折抵', 'wc-points-rewards'), -$discount_value, false);
        } elseif ($amount_incorrect) {
            // [修正 LOGIC-6] 金額不正確時，不直接操作 protected 屬性，
            // 而是透過 calculate_totals 觸發重新計算，讓 apply_points_discount hook 重新添加正確金額
            // 此處僅更新 session 讓下次計算時使用正確值（已在 session 中）
            // Note: 深度修正需要移除舊費用，目前 WooCommerce 無公開 remove_fee() API
            // 最安全的做法是觸發重算（可能造成一次額外循環，但避免 protected 存取）
            WC()->cart->calculate_totals();
        }
    }
}
