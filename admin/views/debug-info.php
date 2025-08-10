<?php
/**
 * 診斷信息頁面 - 檢查點數獎勵系統狀態
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

// 檢查權限
if (!current_user_can('manage_woocommerce')) {
    wp_die(__('您沒有權限查看此頁面', 'wc-points-rewards'));
}

// 獲取診斷信息
$diagnostics = array();

// 1. 基本插件信息
$diagnostics['plugin'] = array(
    'title' => __('插件信息', 'wc-points-rewards'),
    'items' => array(
        __('插件版本', 'wc-points-rewards') => WC_POINTS_REWARDS_VERSION,
        __('WordPress 版本', 'wc-points-rewards') => get_bloginfo('version'),
        __('WooCommerce 版本', 'wc-points-rewards') => WC()->version,
        __('PHP 版本', 'wc-points-rewards') => PHP_VERSION,
        __('插件路径', 'wc-points-rewards') => WC_POINTS_REWARDS_PLUGIN_DIR,
        __('插件 URL', 'wc-points-rewards') => WC_POINTS_REWARDS_PLUGIN_URL,
    )
);

// 2. 點數系統設定檢查
$settings = get_option('wc_points_rewards_settings', array());
$diagnostics['settings'] = array(
    'title' => __('點數系統設定', 'wc-points-rewards'),
    'items' => array(
        __('點數系統啟用', 'wc-points-rewards') => isset($settings['enable_points_system']) ? ($settings['enable_points_system'] === 'yes' ? '✅ 已啟用' : '❌ 未啟用') : '❌ 未設定',
        __('購物車點數折抵', 'wc-points-rewards') => isset($settings['enable_cart_redemption']) ? ($settings['enable_cart_redemption'] === 'yes' ? '✅ 已啟用' : '❌ 未啟用') : '❌ 未設定',
        __('商店頁面顯示點數', 'wc-points-rewards') => isset($settings['show_in_shop_loop']) ? ($settings['show_in_shop_loop'] === 'yes' ? '✅ 已啟用' : '⚠️ 未啟用') : '❌ 未設定',
        __('單一商品頁面顯示點數', 'wc-points-rewards') => isset($settings['show_in_single_product']) ? ($settings['show_in_single_product'] === 'yes' ? '✅ 已啟用' : '❌ 未啟用') : '❌ 未設定',
        __('點數比例設定', 'wc-points-rewards') => isset($settings['points_per_amount']) ? sprintf(__('每 %s 元得 1 點', 'wc-points-rewards'), $settings['points_per_amount']) : '❌ 未設定',
        __('點數價值設定', 'wc-points-rewards') => isset($settings['points_value']) ? sprintf(__('1 點 = %s', 'wc-points-rewards'), wc_price($settings['points_value'])) : '❌ 未設定',
    )
);

// 3. 核心類別檢查
$diagnostics['classes'] = array(
    'title' => __('核心類別狀態', 'wc-points-rewards'),
    'items' => array(
        'WC_Points_Rewards' => class_exists('WC_Points_Rewards') ? '✅ 已載入' : '❌ 未載入',
        'WC_Points_Rewards_Database' => class_exists('WC_Points_Rewards_Database') ? '✅ 已載入' : '❌ 未載入',
        'WC_Points_Rewards_Points_Calculator' => class_exists('WC_Points_Rewards_Points_Calculator') ? '✅ 已載入' : '❌ 未載入',
        'WC_Points_Rewards_Frontend' => class_exists('WC_Points_Rewards_Frontend') ? '✅ 已載入' : '❌ 未載入',
        'WC_Points_Rewards_Checkout' => class_exists('WC_Points_Rewards_Checkout') ? '✅ 已載入' : '❌ 未載入',
        'WC_Points_Rewards_Settings' => class_exists('WC_Points_Rewards_Settings') ? '✅ 已載入' : '❌ 未載入',
    )
);

// 4. 數據庫表格檢查
global $wpdb;
$diagnostics['database'] = array(
    'title' => __('數據庫表格狀態', 'wc-points-rewards'),
    'items' => array()
);

$tables = array(
    'wc_points_rewards_history',
    'wc_points_rewards_tiers',
    'wc_points_rewards_user_stats'
);

foreach ($tables as $table) {
    $table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_name") : 0;
    
    $diagnostics['database']['items'][$table] = $exists ? 
        sprintf('✅ 存在 (%d 筆記錄)', $count) : 
        '❌ 不存在';
}

// 5. 前端功能檢查
$diagnostics['frontend'] = array(
    'title' => __('前端功能檢查', 'wc-points-rewards'),
    'items' => array()
);

// 檢查 CSS 和 JS 文件是否存在
$assets_to_check = array(
    'CSS - 前端樣式' => WC_POINTS_REWARDS_PLUGIN_DIR . 'assets/css/frontend.css',
    'JS - 前端腳本' => WC_POINTS_REWARDS_PLUGIN_DIR . 'assets/js/frontend.js',
    '購物車點數區塊模板' => WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/views/cart-points-section.php',
);

foreach ($assets_to_check as $name => $path) {
    $diagnostics['frontend']['items'][$name] = file_exists($path) ? '✅ 存在' : '❌ 缺失';
}

// 6. WooCommerce 兼容性檢查
$diagnostics['woocommerce'] = array(
    'title' => __('WooCommerce 兼容性', 'wc-points-rewards'),
    'items' => array(
        __('WooCommerce 已啟用', 'wc-points-rewards') => class_exists('WooCommerce') ? '✅ 是' : '❌ 否',
        __('WooCommerce 頁面檢測', 'wc-points-rewards') => function_exists('is_woocommerce') ? '✅ 可用' : '❌ 不可用',
        __('購物車功能', 'wc-points-rewards') => class_exists('WC_Cart') ? '✅ 可用' : '❌ 不可用',
        __('結帳功能', 'wc-points-rewards') => function_exists('is_checkout') ? '✅ 可用' : '❌ 不可用',
    )
);

// 7. 用戶測試 (如果已登入)
if (is_user_logged_in()) {
    $user_id = get_current_user_id();
    $user_points = 0;
    
    if (class_exists('WC_Points_Rewards_Database')) {
        $database = WC_Points_Rewards_Database::instance();
        $user_points = $database->get_user_points($user_id);
    }
    
    $diagnostics['user_test'] = array(
        'title' => __('當前用戶測試', 'wc-points-rewards'),
        'items' => array(
            __('用戶 ID', 'wc-points-rewards') => $user_id,
            __('當前點數餘額', 'wc-points-rewards') => wc_points_rewards_number_format($user_points),
            __('點數系統可用', 'wc-points-rewards') => function_exists('wc_points_rewards_get_user_points') ? '✅ 是' : '❌ 否',
        )
    );
}

// 8. 建議修復方案
$diagnostics['recommendations'] = array(
    'title' => __('建議修復方案', 'wc-points-rewards'),
    'items' => array()
);

// 根據檢查結果提供建議
if (!isset($settings['enable_cart_redemption']) || $settings['enable_cart_redemption'] !== 'yes') {
    $diagnostics['recommendations']['items'][] = '⚠️ 建議在設定中啟用「購物車點數折抵」功能';
}

if (!class_exists('WC_Points_Rewards_Frontend')) {
    $diagnostics['recommendations']['items'][] = '❌ 前端類別未載入，請檢查文件是否存在';
}

if (!file_exists(WC_POINTS_REWARDS_PLUGIN_DIR . 'frontend/views/cart-points-section.php')) {
    $diagnostics['recommendations']['items'][] = '❌ 購物車點數區塊模板缺失，需要重新安裝插件';
}

if (empty($diagnostics['recommendations']['items'])) {
    $diagnostics['recommendations']['items'][] = '✅ 目前沒有發現需要修復的問題';
}

?>

<div class="wrap">
    <h1><?php _e('點數獎勵系統 - 診斷信息', 'wc-points-rewards'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('此頁面顯示點數獎勵系統的詳細狀態信息，幫助您診斷和解決問題。', 'wc-points-rewards'); ?></p>
    </div>
    
    <?php foreach ($diagnostics as $section_key => $section): ?>
        <div class="postbox" style="margin-top: 20px;">
            <h2 class="hndle" style="padding: 10px 15px; margin: 0; background: #f1f1f1;">
                <?php echo esc_html($section['title']); ?>
            </h2>
            <div class="inside" style="padding: 15px;">
                <?php if ($section_key === 'recommendations'): ?>
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($section['items'] as $item): ?>
                            <li style="margin-bottom: 8px;"><?php echo $item; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <table class="widefat" style="margin: 0;">
                        <tbody>
                            <?php foreach ($section['items'] as $label => $value): ?>
                                <tr>
                                    <td style="width: 300px; font-weight: bold; padding: 8px 12px; border-bottom: 1px solid #eee;">
                                        <?php echo esc_html($label); ?>
                                    </td>
                                    <td style="padding: 8px 12px; border-bottom: 1px solid #eee;">
                                        <?php echo $value; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <div class="postbox" style="margin-top: 20px;">
        <h2 class="hndle" style="padding: 10px 15px; margin: 0; background: #f1f1f1;">
            <?php _e('技術信息', 'wc-points-rewards'); ?>
        </h2>
        <div class="inside" style="padding: 15px;">
            <textarea readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;"><?php
                echo "=== WooCommerce Points & Rewards 診斷報告 ===\n";
                echo "生成時間: " . current_time('Y-m-d H:i:s') . "\n";
                echo "WordPress URL: " . get_site_url() . "\n\n";
                
                foreach ($diagnostics as $section_key => $section) {
                    if ($section_key === 'recommendations') continue;
                    
                    echo "=== " . $section['title'] . " ===\n";
                    foreach ($section['items'] as $label => $value) {
                        // 清理 HTML 標籤用於純文字輸出
                        $clean_value = strip_tags($value);
                        echo sprintf("%-30s: %s\n", $label, $clean_value);
                    }
                    echo "\n";
                }
                
                echo "=== 建議修復方案 ===\n";
                foreach ($diagnostics['recommendations']['items'] as $i => $item) {
                    echo ($i + 1) . ". " . strip_tags($item) . "\n";
                }
            ?></textarea>
            <p style="margin-top: 10px;">
                <em><?php _e('您可以複製上述技術信息用於排除故障或技術支援。', 'wc-points-rewards'); ?></em>
            </p>
        </div>
    </div>
</div>

<style>
.postbox {
    border: 1px solid #c3c4c7;
}
.postbox .hndle {
    font-size: 14px;
    font-weight: 600;
}
.widefat td {
    font-size: 13px;
}
</style>