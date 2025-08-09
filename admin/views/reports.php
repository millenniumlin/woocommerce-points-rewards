<?php
/**
 * 管理後台 - 報表頁面
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

// 獲取報表實例
if (!isset($reports) || !$reports) {
    $reports = WC_Points_Rewards_Reports::instance();
}

// 獲取查詢參數
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
$period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-t');

// 根據期間設定日期
switch ($period) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
        break;
    case 'year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
    case 'custom':
        // 使用自定義日期
        break;
    default: // month
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
}

// 獲取數據
$overview_data = $reports->get_points_overview($start_date, $end_date);
$tier_distribution = $reports->get_tier_distribution();
$points_trend = $reports->get_points_earning_trend(30);
$top_users = $reports->get_top_users(10, $period);
$redemption_analysis = $reports->get_redemption_analysis($period);
$expiring_points = $reports->get_expiring_points_stats();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('點數獎勵報表', 'wc-points-rewards'); ?></h1>
    
    <hr class="wp-header-end">
    
    <!-- 報表導航 -->
    <nav class="nav-tab-wrapper">
        <a href="?page=wc-points-rewards-reports&tab=overview" class="nav-tab <?php echo $current_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
            <?php _e('總覽', 'wc-points-rewards'); ?>
        </a>
        <a href="?page=wc-points-rewards-reports&tab=trends" class="nav-tab <?php echo $current_tab === 'trends' ? 'nav-tab-active' : ''; ?>">
            <?php _e('趨勢分析', 'wc-points-rewards'); ?>
        </a>
        <a href="?page=wc-points-rewards-reports&tab=members" class="nav-tab <?php echo $current_tab === 'members' ? 'nav-tab-active' : ''; ?>">
            <?php _e('會員分析', 'wc-points-rewards'); ?>
        </a>
        <a href="?page=wc-points-rewards-reports&tab=export" class="nav-tab <?php echo $current_tab === 'export' ? 'nav-tab-active' : ''; ?>">
            <?php _e('數據匯出', 'wc-points-rewards'); ?>
        </a>
    </nav>
    
    <!-- 期間選擇器 -->
    <div class="report-filters" style="margin: 20px 0;">
        <form method="get" action="">
            <input type="hidden" name="page" value="wc-points-rewards-reports">
            <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">
            
            <label for="period"><?php _e('時期：', 'wc-points-rewards'); ?></label>
            <select name="period" id="period" onchange="toggleCustomDates()">
                <option value="week" <?php selected($period, 'week'); ?>><?php _e('最近7天', 'wc-points-rewards'); ?></option>
                <option value="month" <?php selected($period, 'month'); ?>><?php _e('本月', 'wc-points-rewards'); ?></option>
                <option value="year" <?php selected($period, 'year'); ?>><?php _e('本年', 'wc-points-rewards'); ?></option>
                <option value="custom" <?php selected($period, 'custom'); ?>><?php _e('自定義', 'wc-points-rewards'); ?></option>
            </select>
            
            <span id="custom-dates" style="<?php echo $period === 'custom' ? '' : 'display:none;'; ?>">
                <label for="start_date"><?php _e('開始日期：', 'wc-points-rewards'); ?></label>
                <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>">
                
                <label for="end_date"><?php _e('結束日期：', 'wc-points-rewards'); ?></label>
                <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>">
            </span>
            
            <input type="submit" class="button" value="<?php _e('更新', 'wc-points-rewards'); ?>">
        </form>
    </div>
    
    <div class="reports-container">
        
        <?php if ($current_tab === 'overview'): ?>
            
            <!-- 點數概覽 -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php _e('點數統計概覽', 'wc-points-rewards'); ?></h2>
                </div>
                <div class="inside">
                    <div class="overview-stats">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo wc_points_rewards_number_format($overview_data['total_earned']); ?></div>
                            <div class="stat-label"><?php _e('總發放點數', 'wc-points-rewards'); ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo wc_points_rewards_number_format($overview_data['total_redeemed']); ?></div>
                            <div class="stat-label"><?php _e('總使用點數', 'wc-points-rewards'); ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo wc_points_rewards_number_format($overview_data['total_expired']); ?></div>
                            <div class="stat-label"><?php _e('過期點數', 'wc-points-rewards'); ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number"><?php echo wc_points_rewards_number_format($overview_data['total_active']); ?></div>
                            <div class="stat-label"><?php _e('有效點數總額', 'wc-points-rewards'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 會員等級分布 -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php _e('會員等級分布', 'wc-points-rewards'); ?></h2>
                </div>
                <div class="inside">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('等級名稱', 'wc-points-rewards'); ?></th>
                                <th><?php _e('會員人數', 'wc-points-rewards'); ?></th>
                                <th><?php _e('比例', 'wc-points-rewards'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_users = array_sum(array_column($tier_distribution, 'user_count'));
                            foreach ($tier_distribution as $tier): 
                                $percentage = $total_users > 0 ? round(($tier->user_count / $total_users) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td><?php echo esc_html($tier->tier_name); ?></td>
                                    <td><?php echo number_format($tier->user_count); ?></td>
                                    <td><?php echo $percentage; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- 點數使用分析 -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php _e('點數使用分析', 'wc-points-rewards'); ?></h2>
                </div>
                <div class="inside">
                    <?php if ($redemption_analysis): ?>
                        <div class="redemption-stats">
                            <p><strong><?php _e('使用點數的用戶數：', 'wc-points-rewards'); ?></strong> <?php echo number_format($redemption_analysis->unique_users); ?></p>
                            <p><strong><?php _e('總使用次數：', 'wc-points-rewards'); ?></strong> <?php echo number_format($redemption_analysis->total_redemptions); ?></p>
                            <p><strong><?php _e('平均每次使用：', 'wc-points-rewards'); ?></strong> <?php echo wc_points_rewards_number_format($redemption_analysis->avg_redemption_amount); ?> <?php echo wc_points_rewards_get_points_name(); ?></p>
                        </div>
                    <?php else: ?>
                        <p><?php _e('此期間內沒有點數使用記錄', 'wc-points-rewards'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($current_tab === 'trends'): ?>
            
            <!-- 點數趨勢圖 -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php _e('點數獲得與使用趨勢（最近30天）', 'wc-points-rewards'); ?></h2>
                </div>
                <div class="inside">
                    <canvas id="pointsTrendChart" width="400" height="200"></canvas>
                </div>
            </div>
            
            <!-- 點數即將到期統計 -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php _e('點數到期警報', 'wc-points-rewards'); ?></h2>
                </div>
                <div class="inside">
                    <div class="expiry-stats">
                        <div class="expiry-warning">
                            <h4><?php _e('7天內到期', 'wc-points-rewards'); ?></h4>
                            <div class="expiry-amount urgent"><?php echo wc_points_rewards_number_format($expiring_points['expiring_7_days']); ?> <?php echo wc_points_rewards_get_points_name(); ?></div>
                        </div>
                        <div class="expiry-warning">
                            <h4><?php _e('30天內到期', 'wc-points-rewards'); ?></h4>
                            <div class="expiry-amount warning"><?php echo wc_points_rewards_number_format($expiring_points['expiring_30_days']); ?> <?php echo wc_points_rewards_get_points_name(); ?></div>
                        </div>
                        <div class="expiry-warning">
                            <h4><?php _e('已過期未標記', 'wc-points-rewards'); ?></h4>
                            <div class="expiry-amount expired"><?php echo wc_points_rewards_number_format($expiring_points['expired_unmarked']); ?> <?php echo wc_points_rewards_get_points_name(); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($current_tab === 'members'): ?>
            
            <!-- 最活躍用戶 -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php _e('最活躍用戶 TOP 10', 'wc-points-rewards'); ?></h2>
                </div>
                <div class="inside">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('排名', 'wc-points-rewards'); ?></th>
                                <th><?php _e('用戶', 'wc-points-rewards'); ?></th>
                                <th><?php _e('獲得點數', 'wc-points-rewards'); ?></th>
                                <th><?php _e('使用點數', 'wc-points-rewards'); ?></th>
                                <th><?php _e('活躍度', 'wc-points-rewards'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($top_users as $user): 
                                $activity_score = $user->total_earned + $user->total_redeemed;
                            ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td>
                                        <?php echo esc_html($user->display_name); ?>
                                        <br><small><?php echo esc_html($user->user_email); ?></small>
                                    </td>
                                    <td class="points-earned">+<?php echo wc_points_rewards_number_format($user->total_earned); ?></td>
                                    <td class="points-redeemed">-<?php echo wc_points_rewards_number_format($user->total_redeemed); ?></td>
                                    <td><?php echo wc_points_rewards_number_format($activity_score); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        <?php elseif ($current_tab === 'export'): ?>
            
            <!-- 數據匯出 -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php _e('數據匯出', 'wc-points-rewards'); ?></h2>
                </div>
                <div class="inside">
                    <p><?php _e('選擇要匯出的報表類型：', 'wc-points-rewards'); ?></p>
                    
                    <div class="export-options">
                        <form method="post" action="">
                            <?php wp_nonce_field('wc_points_rewards_export', 'export_nonce'); ?>
                            
                            <p>
                                <input type="radio" name="export_type" value="points_history" id="export_points" checked>
                                <label for="export_points"><?php _e('點數歷史記錄', 'wc-points-rewards'); ?></label>
                            </p>
                            
                            <p>
                                <input type="radio" name="export_type" value="tier_distribution" id="export_tiers">
                                <label for="export_tiers"><?php _e('會員等級分布', 'wc-points-rewards'); ?></label>
                            </p>
                            
                            <p>
                                <input type="radio" name="export_type" value="top_users" id="export_users">
                                <label for="export_users"><?php _e('最活躍用戶', 'wc-points-rewards'); ?></label>
                            </p>
                            
                            <p>
                                <input type="date" name="export_start_date" value="<?php echo $start_date; ?>">
                                <?php _e('至', 'wc-points-rewards'); ?>
                                <input type="date" name="export_end_date" value="<?php echo $end_date; ?>">
                            </p>
                            
                            <p>
                                <input type="submit" name="export_csv" class="button button-primary" value="<?php _e('匯出 CSV', 'wc-points-rewards'); ?>">
                            </p>
                        </form>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
        
    </div>
</div>

<!-- 樣式 -->
<style>
.overview-stats {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.stat-box {
    flex: 1;
    min-width: 200px;
    background: #f9f9f9;
    padding: 20px;
    text-align: center;
    border-radius: 5px;
}

.stat-number {
    font-size: 2em;
    font-weight: bold;
    color: #0073aa;
}

.stat-label {
    margin-top: 5px;
    font-size: 0.9em;
    color: #666;
}

.points-earned {
    color: #46b450;
    font-weight: bold;
}

.points-redeemed {
    color: #dc3232;
    font-weight: bold;
}

.expiry-stats {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.expiry-warning {
    flex: 1;
    min-width: 200px;
    text-align: center;
    padding: 15px;
    border-radius: 5px;
    background: #f9f9f9;
}

.expiry-amount {
    font-size: 1.5em;
    font-weight: bold;
    margin-top: 10px;
}

.expiry-amount.urgent {
    color: #dc3232;
}

.expiry-amount.warning {
    color: #f56e28;
}

.expiry-amount.expired {
    color: #666;
}

.redemption-stats p {
    margin: 10px 0;
}

.export-options {
    margin: 20px 0;
}

.export-options p {
    margin: 10px 0;
}

.report-filters {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
}

.report-filters label {
    margin-right: 10px;
}

.report-filters select, .report-filters input {
    margin-right: 15px;
}
</style>

<!-- JavaScript for charts and interactions -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function toggleCustomDates() {
    var period = document.getElementById('period').value;
    var customDates = document.getElementById('custom-dates');
    
    if (period === 'custom') {
        customDates.style.display = 'inline';
    } else {
        customDates.style.display = 'none';
    }
}

<?php if ($current_tab === 'trends' && !empty($points_trend)): ?>
// 趨勢圖
var ctx = document.getElementById('pointsTrendChart').getContext('2d');
var chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: [
            <?php 
            foreach ($points_trend as $data) {
                echo "'" . date('m/d', strtotime($data->date)) . "',";
            }
            ?>
        ],
        datasets: [{
            label: '<?php _e('獲得點數', 'wc-points-rewards'); ?>',
            data: [
                <?php 
                foreach ($points_trend as $data) {
                    echo floatval($data->earned) . ',';
                }
                ?>
            ],
            borderColor: '#46b450',
            backgroundColor: 'rgba(70, 180, 80, 0.1)',
            tension: 0.4
        }, {
            label: '<?php _e('使用點數', 'wc-points-rewards'); ?>',
            data: [
                <?php 
                foreach ($points_trend as $data) {
                    echo floatval($data->redeemed) . ',';
                }
                ?>
            ],
            borderColor: '#dc3232',
            backgroundColor: 'rgba(220, 50, 50, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
<?php endif; ?>
</script>

<?php
// 處理CSV匯出
if (isset($_POST['export_csv']) && wp_verify_nonce($_POST['export_nonce'], 'wc_points_rewards_export')) {
    $export_type = sanitize_text_field($_POST['export_type']);
    $export_start = sanitize_text_field($_POST['export_start_date']);
    $export_end = sanitize_text_field($_POST['export_end_date']);
    
    switch ($export_type) {
        case 'points_history':
            global $wpdb;
            $points_table = $wpdb->prefix . 'wc_points_rewards_points';
            $data = $wpdb->get_results($wpdb->prepare("
                SELECT p.*, u.display_name, u.user_email
                FROM $points_table p
                LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
                WHERE p.created_at BETWEEN %s AND %s
                ORDER BY p.created_at DESC
            ", $export_start, $export_end . ' 23:59:59'));
            $reports->export_csv('points_history', $data);
            break;
            
        case 'tier_distribution':
            $reports->export_csv('tier_distribution', $tier_distribution);
            break;
            
        case 'top_users':
            $reports->export_csv('top_users', $top_users);
            break;
    }
}
?>