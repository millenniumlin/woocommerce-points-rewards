<?php
/**
 * 管理後台 - 用戶編輯頁面點數管理欄位
 * 修正 D: 新增聯絡電話欄位到後台用戶管理
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

// 獲取聯絡電話
$contact_phone = get_user_meta($user->ID, 'contact_phone', true);
$birthday = get_user_meta($user->ID, 'birthday', true);
?>

<h3><?php _e('點數獎勵資訊', 'wc-points-rewards'); ?></h3>

<table class="form-table">
    <tr>
        <th><label for="user_current_points"><?php _e('目前點數', 'wc-points-rewards'); ?></label></th>
        <td>
            <strong><?php echo wc_points_rewards_number_format($user_points); ?></strong> <?php echo wc_points_rewards_get_points_name(); ?>
            <p class="description"><?php _e('用戶目前的點數餘額', 'wc-points-rewards'); ?></p>
        </td>
    </tr>
    
    <tr>
        <th><label for="user_current_tier"><?php _e('會員等級', 'wc-points-rewards'); ?></label></th>
        <td>
            <?php if ($current_tier): ?>
                <strong><?php echo esc_html($current_tier->name); ?></strong>
                <?php if ($current_tier->bonus_percentage > 0): ?>
                    <span class="tier-bonus">(+<?php echo $current_tier->bonus_percentage; ?>% 回饋)</span>
                <?php endif; ?>
            <?php else: ?>
                <span><?php _e('一般會員', 'wc-points-rewards'); ?></span>
            <?php endif; ?>
            <p class="description"><?php _e('基於年度消費金額自動計算的會員等級', 'wc-points-rewards'); ?></p>
        </td>
    </tr>
</table>

<h3><?php _e('會員個人資訊', 'wc-points-rewards'); ?></h3>

<table class="form-table">
    <tr>
        <th><label for="user_birthday"><?php _e('出生日期', 'wc-points-rewards'); ?></label></th>
        <td>
            <input type="date" name="user_birthday" id="user_birthday" value="<?php echo esc_attr($birthday); ?>" class="regular-text" />
            <p class="description"><?php _e('用戶生日，用於生日點數發放', 'wc-points-rewards'); ?></p>
        </td>
    </tr>
    
    <!-- 修正 D: 新增聯絡電話欄位 -->
    <tr>
        <th><label for="user_contact_phone"><?php _e('聯絡電話', 'wc-points-rewards'); ?></label></th>
        <td>
            <input type="tel" name="user_contact_phone" id="user_contact_phone" value="<?php echo esc_attr($contact_phone); ?>" class="regular-text" />
            <p class="description"><?php _e('用戶聯絡電話', 'wc-points-rewards'); ?></p>
        </td>
    </tr>
</table>

<?php if (!empty($recent_history)): ?>
<h3><?php _e('最近點數記錄', 'wc-points-rewards'); ?></h3>

<table class="widefat fixed striped">
    <thead>
        <tr>
            <th><?php _e('日期', 'wc-points-rewards'); ?></th>
            <th><?php _e('類型', 'wc-points-rewards'); ?></th>
            <th><?php _e('說明', 'wc-points-rewards'); ?></th>
            <th><?php _e('點數', 'wc-points-rewards'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($recent_history as $record): ?>
        <tr>
            <td><?php echo date('Y-m-d H:i', strtotime($record->created_at)); ?></td>
            <td>
                <?php
                switch ($record->type) {
                    case 'earned':
                        echo '<span class="earned">' . __('獲得', 'wc-points-rewards') . '</span>';
                        break;
                    case 'redeemed':
                        echo '<span class="redeemed">' . __('使用', 'wc-points-rewards') . '</span>';
                        break;
                    case 'expired':
                        echo '<span class="expired">' . __('過期', 'wc-points-rewards') . '</span>';
                        break;
                    case 'admin':
                        echo '<span class="admin">' . __('管理員', 'wc-points-rewards') . '</span>';
                        break;
                    default:
                        echo '<span class="other">' . esc_html($record->type) . '</span>';
                }
                ?>
            </td>
            <td><?php echo esc_html($record->description); ?></td>
            <td>
                <span class="points-value <?php echo $record->points > 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $record->points > 0 ? '+' : ''; ?><?php echo wc_points_rewards_number_format($record->points); ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<style>
.points-value.positive {
    color: #46b450;
    font-weight: bold;
}

.points-value.negative {
    color: #dc3232;
    font-weight: bold;
}

.tier-bonus {
    color: #0073aa;
    font-weight: normal;
}

.earned {
    color: #46b450;
    font-weight: bold;
}

.redeemed {
    color: #dc3232;
    font-weight: bold;
}

.expired {
    color: #ffb900;
    font-weight: bold;
}

.admin {
    color: #0073aa;
    font-weight: bold;
}
</style>