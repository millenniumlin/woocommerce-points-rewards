<?php
/**
 * 管理後台 - 設定頁面 - 完整版
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('wc_points_rewards_settings', array());

// 處理表單提交
if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'wc_points_rewards_settings')) {
    $new_settings = array(
        // 基本設定
        'enable_points_system' => sanitize_text_field($_POST['enable_points_system'] ?? 'no'),
        'points_name' => sanitize_text_field($_POST['points_name'] ?? '點'),
        
        // 點數獲得
        'points_per_amount' => floatval($_POST['points_per_amount'] ?? 100),
        'points_amount' => floatval($_POST['points_amount'] ?? 1),
        'points_expiry_months' => intval($_POST['points_expiry_months'] ?? 12),
        
        // 購物車點數使用設定
        'enable_cart_redemption' => sanitize_text_field($_POST['enable_cart_redemption'] ?? 'yes'),
        'max_discount_percent' => floatval($_POST['max_discount_percent'] ?? 20),
        'min_cart_total' => floatval($_POST['min_cart_total'] ?? 500),
        
        // 顯示設定
        'show_in_menu' => sanitize_text_field($_POST['show_in_menu'] ?? 'yes'),
        'show_in_shop_loop' => sanitize_text_field($_POST['show_in_shop_loop'] ?? 'yes'),
        'show_in_single_product' => sanitize_text_field($_POST['show_in_single_product'] ?? 'yes'),
    );
    
    update_option('wc_points_rewards_settings', $new_settings);
    $settings = $new_settings;
    
    echo '<div class="notice notice-success"><p>' . __('設定已儲存', 'wc-points-rewards') . '</p></div>';
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('點數獎勵設定', 'wc-points-rewards'); ?></h1>
    
    <hr class="wp-header-end">
    
    <form method="post" action="" class="wc-points-settings-form">
        <?php wp_nonce_field('wc_points_rewards_settings'); ?>
        
        <!-- 基本設定 -->
        <div class="settings-section">
            <h2><?php _e('基本設定', 'wc-points-rewards'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('啟用點數系統', 'wc-points-rewards'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_points_system" value="yes" <?php checked($settings['enable_points_system'] ?? 'yes', 'yes'); ?>>
                            <?php _e('啟用點數獎勵功能', 'wc-points-rewards'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('點數名稱', 'wc-points-rewards'); ?></th>
                    <td>
                        <input type="text" name="points_name" value="<?php echo esc_attr($settings['points_name'] ?? '點'); ?>" class="regular-text">
                        <p class="description"><?php _e('點數的顯示名稱，例如：點、積分、金幣等', 'wc-points-rewards'); ?></p>
                    </td>
                </tr>
                
                <!-- 小數位數設定說明 -->
                <tr>
                    <th scope="row"><?php _e('小數位數顯示', 'wc-points-rewards'); ?></th>
                    <td>
                        <p class="description"><?php _e('點數小數位數現在自動跟隨 WooCommerce > 設定 > 一般 > 貨幣選項 > 小數位數 的設定。', 'wc-points-rewards'); ?></p>
                        <p class="description"><?php printf(__('前往 <a href="%s" target="_blank">WooCommerce 一般設定</a> 調整小數位數顯示。', 'wc-points-rewards'), admin_url('admin.php?page=wc-settings')); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- 點數獲得設定 -->
        <div class="settings-section">
            <h2><?php _e('點數獲得設定', 'wc-points-rewards'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('點數回饋比例', 'wc-points-rewards'); ?></th>
                    <td>
                        <label>
                            <?php _e('每', 'wc-points-rewards'); ?>
                            <input type="number" name="points_per_amount" value="<?php echo esc_attr($settings['points_per_amount'] ?? 100); ?>" min="1" step="0.01" class="small-text">
                            <?php _e('元回饋', 'wc-points-rewards'); ?>
                            <input type="number" name="points_amount" value="<?php echo esc_attr($settings['points_amount'] ?? 1); ?>" min="0.01" step="0.01" class="small-text">
                            <?php echo wc_points_rewards_get_points_name(); ?>
                        </label>
                        <p class="description"><?php _e('例如：每100元回饋1點', 'wc-points-rewards'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('點數有效期', 'wc-points-rewards'); ?></th>
                    <td>
                        <input type="number" name="points_expiry_months" value="<?php echo esc_attr($settings['points_expiry_months'] ?? 12); ?>" min="1" class="small-text">
                        <?php _e('個月', 'wc-points-rewards'); ?>
                        <p class="description"><?php _e('點數的有效期限，超過此期限點數將自動過期', 'wc-points-rewards'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- 購物車點數使用設定 -->
        <div class="settings-section">
            <h2><?php _e('購物車點數使用設定', 'wc-points-rewards'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('啟用購物車點數折抵', 'wc-points-rewards'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_cart_redemption" value="yes" <?php checked($settings['enable_cart_redemption'] ?? 'yes', 'yes'); ?>>
                            <?php _e('允許客戶在購物車頁面使用點數折抵', 'wc-points-rewards'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('最大折扣百分比', 'wc-points-rewards'); ?></th>
                    <td>
                        <input type="number" name="max_discount_percent" value="<?php echo esc_attr($settings['max_discount_percent'] ?? 20); ?>" min="1" max="100" step="1" class="small-text">
                        %
                        <p class="description"><?php _e('消費者最多可使用訂單金額多少百分比的點數來折抵（例如：設定50，表示最多可用點數折抵訂單金額的50%）', 'wc-points-rewards'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('最低購物車金額', 'wc-points-rewards'); ?></th>
                    <td>
                        <input type="number" name="min_cart_total" value="<?php echo esc_attr($settings['min_cart_total'] ?? 500); ?>" min="0" step="0.01" class="regular-text">
                        <?php echo get_woocommerce_currency_symbol(); ?>
                        <p class="description"><?php _e('購物車金額需達到此數額才能使用點數折抵（設定為0表示無限制）', 'wc-points-rewards'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- 顯示設定 -->
        <div class="settings-section">
            <h2><?php _e('顯示設定', 'wc-points-rewards'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('選單位置顯示', 'wc-points-rewards'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_in_menu" value="yes" <?php checked($settings['show_in_menu'] ?? 'yes', 'yes'); ?>>
                            <?php _e('在網站選單顯示點數資訊', 'wc-points-rewards'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('商品列表頁顯示', 'wc-points-rewards'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_in_shop_loop" value="yes" <?php checked($settings['show_in_shop_loop'] ?? 'yes', 'yes'); ?>>
                            <?php _e('在商品列表頁顯示可獲得點數', 'wc-points-rewards'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('單一商品頁顯示', 'wc-points-rewards'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_in_single_product" value="yes" <?php checked($settings['show_in_single_product'] ?? 'yes', 'yes'); ?>>
                            <?php _e('在單一商品頁顯示可獲得點數', 'wc-points-rewards'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- 回溯歷史訂單 -->
        <div class="settings-section">
            <h2><?php _e('回溯歷史訂單點數', 'wc-points-rewards'); ?></h2>
            
            <div class="historical-orders-section">
                <p class="description">
                    <?php _e('系統會找出指定日期範圍內所有已完成的訂單，為這些訂單的會員補發對應的點數（基礎點數 + 等級加成），更新會員的年度消費記錄，重新計算並升級會員等級。已經處理過的訂單會自動跳過，不會重複給點數。', 'wc-points-rewards'); ?>
                </p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('開始日期', 'wc-points-rewards'); ?></th>
                        <td>
                            <input type="date" id="historical_start_date" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('結束日期', 'wc-points-rewards'); ?></th>
                        <td>
                            <input type="date" id="historical_end_date" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('執行處理', 'wc-points-rewards'); ?></th>
                        <td>
                            <button type="button" id="process_historical_orders" class="button button-secondary">
                                <?php _e('開始處理歷史訂單', 'wc-points-rewards'); ?>
                            </button>
                            <div id="process_progress" style="display: none; margin-top: 10px;">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 0%"></div>
                                </div>
                                <div class="progress-text">處理中...</div>
                            </div>
                            <div id="process_results" style="margin-top: 10px;"></div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="save_settings" class="button-primary" value="<?php _e('儲存設定', 'wc-points-rewards'); ?>">
        </p>
    </form>
</div>

<style>
.settings-section {
    background: white;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    margin-bottom: 20px;
    padding: 0;
}

.settings-section h2 {
    margin: 0;
    padding: 15px 20px;
    background: #f6f7f7;
    border-bottom: 1px solid #c3c4c7;
    font-size: 16px;
}

.settings-section .form-table {
    margin: 0;
    padding: 20px;
}

.historical-orders-section {
    padding: 20px;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background: #f0f0f1;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #00a32a, #00ba37);
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 14px;
    color: #646970;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#process_historical_orders').on('click', function() {
        var startDate = $('#historical_start_date').val();
        var endDate = $('#historical_end_date').val();
        
        if (!startDate || !endDate) {
            alert('<?php _e('請選擇開始和結束日期', 'wc-points-rewards'); ?>');
            return;
        }
        
        if (startDate > endDate) {
            alert('<?php _e('開始日期不能晚於結束日期', 'wc-points-rewards'); ?>');
            return;
        }
        
        if (!confirm('<?php _e('確定要處理此日期範圍內的歷史訂單嗎？此操作無法撤銷。', 'wc-points-rewards'); ?>')) {
            return;
        }
        
        var $button = $(this);
        var $progress = $('#process_progress');
        var $results = $('#process_results');
        
        $button.prop('disabled', true).text('<?php _e('處理中...', 'wc-points-rewards'); ?>');
        $progress.show();
        $results.empty();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_points_rewards_process_historical_orders',
                start_date: startDate,
                end_date: endDate,
                nonce: '<?php echo wp_create_nonce('wc_points_rewards_historical_orders'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('.progress-fill').css('width', '100%');
                    $('.progress-text').text('<?php _e('處理完成', 'wc-points-rewards'); ?>');
                    
                    $results.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                } else {
                    $results.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $results.html('<div class="notice notice-error"><p><?php _e('處理失敗，請稍後再試', 'wc-points-rewards'); ?></p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e('開始處理歷史訂單', 'wc-points-rewards'); ?>');
                setTimeout(function() {
                    $progress.hide();
                }, 2000);
            }
        });
    });
});
</script>