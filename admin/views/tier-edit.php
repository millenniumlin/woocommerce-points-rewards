<?php
/**
 * 管理後台 - 會員等級編輯
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_new = !$tier || !$tier->id;
$page_title = $is_new ? __('新增會員等級', 'wc-points-rewards') : __('編輯會員等級', 'wc-points-rewards');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=wc-points-rewards-tiers'); ?>" class="page-title-action">
        <?php _e('返回列表', 'wc-points-rewards'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="tier-edit-form">
        <?php wp_nonce_field('wc_points_rewards_save_tier'); ?>
        <input type="hidden" name="action" value="wc_points_rewards_save_tier">
        <?php if (!$is_new): ?>
            <input type="hidden" name="tier_id" value="<?php echo esc_attr($tier->id); ?>">
        <?php endif; ?>
        
        <div class="tier-edit-container">
            <div class="main-content">
                <div class="form-section">
                    <h2><?php _e('基本資訊', 'wc-points-rewards'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="tier_name"><?php _e('等級名稱', 'wc-points-rewards'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="tier_name" 
                                       name="tier_name" 
                                       value="<?php echo esc_attr($tier->name ?? ''); ?>" 
                                       class="regular-text" 
                                       required>
                                <p class="description"><?php _e('會員等級的顯示名稱，例如：銅牌會員、銀牌會員等', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="min_amount"><?php _e('最低消費金額', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="min_amount" 
                                       name="min_amount" 
                                       value="<?php echo esc_attr($tier->min_amount ?? 0); ?>" 
                                       min="0" 
                                       step="0.01" 
                                       class="regular-text">
                                <p class="description"><?php _e('用戶年度累積消費達到此金額時自動升級到此等級。設為 0 表示預設等級。', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="bonus_percentage"><?php _e('額外回饋百分比', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="bonus_percentage" 
                                       name="bonus_percentage" 
                                       value="<?php echo esc_attr($tier->bonus_percentage ?? 0); ?>" 
                                       min="0" 
                                       max="100" 
                                       step="0.01" 
                                       class="small-text">
                                <span class="input-suffix">%</span>
                                <p class="description"><?php _e('此等級會員購物時額外獲得的點數回饋百分比。例如設定 10，表示額外獲得 10% 點數。', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="tier_order"><?php _e('等級順序', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="tier_order" 
                                       name="tier_order" 
                                       value="<?php echo esc_attr($tier->tier_order ?? 1); ?>" 
                                       min="1" 
                                       class="small-text">
                                <p class="description"><?php _e('等級的排序順序，數字越小排序越前面', 'wc-points-rewards'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php if (!$is_new): ?>
                <div class="form-section">
                    <h2><?php _e('等級統計', 'wc-points-rewards'); ?></h2>
                    
                    <?php
                    global $wpdb;
                    $stats_table = $wpdb->prefix . 'wc_points_rewards_user_stats';
                    $current_year = date('Y');
                    
                    // 當前使用此等級的用戶數
                    $current_users = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) FROM $stats_table 
                        WHERE current_tier_id = %d 
                        AND year = %d
                        AND (tier_expiry_date IS NULL OR tier_expiry_date > NOW())
                    ", $tier->id, $current_year));
                    
                    // 歷史使用此等級的用戶數
                    $total_users = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(DISTINCT user_id) FROM $stats_table 
                        WHERE current_tier_id = %d
                    ", $tier->id));
                    
                    // 平均年度消費
                    $avg_spending = $wpdb->get_var($wpdb->prepare("
                        SELECT AVG(total_spent) FROM $stats_table 
                        WHERE current_tier_id = %d 
                        AND year = %d
                    ", $tier->id, $current_year));
                    ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('目前用戶數', 'wc-points-rewards'); ?></th>
                            <td>
                                <strong><?php echo number_format($current_users); ?></strong> <?php _e('人', 'wc-points-rewards'); ?>
                                <?php if ($current_users > 0): ?>
                                    <a href="<?php echo admin_url('users.php?tier_id=' . $tier->id); ?>" class="button button-small">
                                        <?php _e('查看用戶', 'wc-points-rewards'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('歷史總用戶數', 'wc-points-rewards'); ?></th>
                            <td><strong><?php echo number_format($total_users); ?></strong> <?php _e('人', 'wc-points-rewards'); ?></td>
                        </tr>
                        
                        <?php if ($avg_spending > 0): ?>
                        <tr>
                            <th scope="row"><?php _e('平均年度消費', 'wc-points-rewards'); ?></th>
                            <td><strong><?php echo wc_price($avg_spending); ?></strong></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="sidebar">
                <div class="sidebar-section">
                    <h3><?php _e('發佈', 'wc-points-rewards'); ?></h3>
                    <div class="submit-actions">
                        <?php submit_button($is_new ? __('建立等級', 'wc-points-rewards') : __('更新等級', 'wc-points-rewards'), 'primary', 'submit', false); ?>
                        <a href="<?php echo admin_url('admin.php?page=wc-points-rewards-tiers'); ?>" class="button">
                            <?php _e('取消', 'wc-points-rewards'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="sidebar-section">
                    <h3><?php _e('等級預覽', 'wc-points-rewards'); ?></h3>
                    <div class="tier-preview">
                        <div class="preview-tier-name" id="preview-name">
                            <?php echo esc_html($tier->name ?? __('等級名稱', 'wc-points-rewards')); ?>
                        </div>
                        <div class="preview-requirements" id="preview-requirements">
                            <?php 
                            $min_amount = $tier->min_amount ?? 0;
                            if ($min_amount > 0) {
                                printf(__('年消費滿 %s', 'wc-points-rewards'), wc_price($min_amount));
                            } else {
                                _e('無消費門檻', 'wc-points-rewards');
                            }
                            ?>
                        </div>
                        <div class="preview-benefits" id="preview-benefits">
                            <?php 
                            $bonus = $tier->bonus_percentage ?? 0;
                            if ($bonus > 0) {
                                printf(__('額外 +%s%% 點數回饋', 'wc-points-rewards'), $bonus);
                            } else {
                                _e('基礎點數回饋', 'wc-points-rewards');
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="sidebar-section">
                    <h3><?php _e('說明', 'wc-points-rewards'); ?></h3>
                    <div class="help-content">
                        <ul>
                            <li><?php _e('等級名稱會顯示在前端用戶介面中', 'wc-points-rewards'); ?></li>
                            <li><?php _e('最低消費金額為 0 的等級會成為所有新用戶的預設等級', 'wc-points-rewards'); ?></li>
                            <li><?php _e('額外回饋百分比會疊加在基礎點數回饋上', 'wc-points-rewards'); ?></li>
                            <li><?php _e('用戶等級每年重新計算一次', 'wc-points-rewards'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.tier-edit-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-top: 20px;
}

.form-section {
    background: white;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    margin-bottom: 20px;
}

.form-section h2 {
    margin: 0;
    padding: 15px 20px;
    background: #f6f7f7;
    border-bottom: 1px solid #c3c4c7;
    font-size: 16px;
}

.form-section .form-table {
    margin: 0;
    padding: 20px;
}

.required {
    color: #d63638;
}

.input-suffix {
    margin-left: 5px;
    color: #646970;
}

.sidebar {
    align-self: start;
}

.sidebar-section {
    background: white;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    margin-bottom: 20px;
}

.sidebar-section h3 {
    margin: 0;
    padding: 15px 20px;
    background: #f6f7f7;
    border-bottom: 1px solid #c3c4c7;
    font-size: 14px;
    font-weight: 600;
}

.submit-actions {
    padding: 20px;
}

.submit-actions .button {
    display: block;
    text-align: center;
    margin-bottom: 10px;
}

.tier-preview {
    padding: 20px;
    text-align: center;
}

.preview-tier-name {
    font-size: 18px;
    font-weight: bold;
    color: #1d2327;
    margin-bottom: 10px;
}

.preview-requirements {
    color: #646970;
    margin-bottom: 8px;
    font-size: 14px;
}

.preview-benefits {
    color: #00a32a;
    font-weight: bold;
    font-size: 14px;
}

.help-content {
    padding: 20px;
}

.help-content ul {
    margin: 0;
    padding-left: 20px;
}

.help-content li {
    margin-bottom: 8px;
    line-height: 1.5;
    color: #646970;
    font-size: 13px;
}

@media (max-width: 768px) {
    .tier-edit-container {
        grid-template-columns: 1fr;
    }
    
    .submit-actions .button {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // 即時預覽功能
    function updatePreview() {
        var name = $('#tier_name').val() || '<?php _e('等級名稱', 'wc-points-rewards'); ?>';
        var minAmount = parseFloat($('#min_amount').val()) || 0;
        var bonus = parseFloat($('#bonus_percentage').val()) || 0;
        
        $('#preview-name').text(name);
        
        if (minAmount > 0) {
            $('#preview-requirements').text('<?php _e('年消費滿', 'wc-points-rewards'); ?> $' + minAmount.toFixed(2));
        } else {
            $('#preview-requirements').text('<?php _e('無消費門檻', 'wc-points-rewards'); ?>');
        }
        
        if (bonus > 0) {
            $('#preview-benefits').text('<?php _e('額外', 'wc-points-rewards'); ?> +' + bonus + '% <?php _e('點數回饋', 'wc-points-rewards'); ?>');
        } else {
            $('#preview-benefits').text('<?php _e('基礎點數回饋', 'wc-points-rewards'); ?>');
        }
    }
    
    // 綁定輸入事件
    $('#tier_name, #min_amount, #bonus_percentage').on('input', updatePreview);
    
    // 表單驗證
    $('.tier-edit-form').on('submit', function(e) {
        var isValid = true;
        var errors = [];
        
        // 驗證等級名稱
        var tierName = $('#tier_name').val().trim();
        if (!tierName) {
            errors.push('<?php _e('等級名稱不能為空', 'wc-points-rewards'); ?>');
            $('#tier_name').addClass('error');
            isValid = false;
        } else {
            $('#tier_name').removeClass('error');
        }
        
        // 驗證最低消費金額
        var minAmount = parseFloat($('#min_amount').val());
        if (isNaN(minAmount) || minAmount < 0) {
            errors.push('<?php _e('最低消費金額必須為有效數字', 'wc-points-rewards'); ?>');
            $('#min_amount').addClass('error');
            isValid = false;
        } else {
            $('#min_amount').removeClass('error');
        }
        
        // 驗證回饋百分比
        var bonus = parseFloat($('#bonus_percentage').val());
        if (isNaN(bonus) || bonus < 0 || bonus > 100) {
            errors.push('<?php _e('額外回饋百分比必須在 0-100 之間', 'wc-points-rewards'); ?>');
            $('#bonus_percentage').addClass('error');
            isValid = false;
        } else {
            $('#bonus_percentage').removeClass('error');
        }
        
        if (!isValid) {
            e.preventDefault();
            alert(errors.join('\n'));
        }
    });
    
    // 添加錯誤樣式
    $('<style>')
        .text(`
            .error {
                border-color: #d63638 !important;
                box-shadow: 0 0 2px rgba(214,54,56,0.8);
            }
        `)
        .appendTo('head');
});
</script>