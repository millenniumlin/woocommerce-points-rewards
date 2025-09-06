<?php
/**
 * 我的帳戶 - 會員等級模板
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wc-points-rewards-member-tier">
    <!-- 時間篩選器 -->
    <div class="tier-filters">
        <form method="get" class="tier-filter-form">
            <select name="filter_year" onchange="this.form.submit()">
                <?php
                $selected_year = intval($_GET['filter_year'] ?? date('Y'));
                $current_year = date('Y');
                for ($year = $current_year; $year >= $current_year - 5; $year--):
                ?>
                <option value="<?php echo $year; ?>" <?php selected($selected_year, $year); ?>><?php echo $year; ?> 年</option>
                <?php endfor; ?>
            </select>
            
            <!-- 保持其他查詢參數 -->
            <?php foreach ($_GET as $key => $value): ?>
                <?php if ($key !== 'filter_year'): ?>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" />
                <?php endif; ?>
            <?php endforeach; ?>
        </form>
    </div>
    
    <!-- 當前等級資訊 -->
    <div class="current-tier-section">
        <div class="tier-header">
            <div class="tier-icon">⭐</div>
            <div class="tier-info">
                <h2 class="tier-name"><?php echo esc_html($current_tier->name); ?></h2>
                <?php if ($current_tier->bonus_percentage > 0): ?>
                    <div class="tier-benefits">
                        <?php printf(__('享有 %s 額外點數回饋', 'wc-points-rewards'), wc_points_rewards_format_percentage($current_tier->bonus_percentage)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 年度消費金額與會員資格到期日區塊 -->
        <div class="member-key-info-section">
            <div class="key-info-grid">
                <div class="key-info-item annual-spending">
                    <div class="info-icon">💰</div>
                    <div class="info-content">
                        <div class="info-label"><?php printf(__('%d年度消費金額', 'wc-points-rewards'), $yearly_stats->year ?? date('Y')); ?></div>
                        <div class="info-value"><?php echo wc_price($yearly_stats ? $yearly_stats->total_spent : 0); ?></div>
                    </div>
                </div>
                
                <div class="key-info-item membership-expiry">
                    <div class="info-icon">📅</div>
                    <div class="info-content">
                        <div class="info-label"><?php _e('會員資格到期日', 'wc-points-rewards'); ?></div>
                        <div class="info-value">
                            <?php if ($yearly_stats && $yearly_stats->tier_expiry_date): ?>
                                <?php echo date('Y-m-d', strtotime($yearly_stats->tier_expiry_date)); ?>
                            <?php else: ?>
                                <span class="no-expiry"><?php _e('無到期日', 'wc-points-rewards'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($yearly_stats): ?>
        <div class="tier-stats">
            <div class="stat-item">
                <div class="stat-label"><?php _e('獲得總點數', 'wc-points-rewards'); ?></div>
                <div class="stat-value"><?php echo wc_points_rewards_number_format($yearly_stats->total_points_earned); ?> <?php echo wc_points_rewards_get_points_name(); ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- 升級進度 -->
    <?php if ($tier_progress['next_tier']): ?>
    <div class="tier-upgrade-section">
        <h3><?php _e('升級進度', 'wc-points-rewards'); ?></h3>
        
        <div class="upgrade-target">
            <div class="target-tier">
                <span class="target-name"><?php echo esc_html($tier_progress['next_tier']->name); ?></span>
                <span class="target-benefit">+<?php echo wc_points_rewards_format_percentage($tier_progress['next_tier']->bonus_percentage); ?> <?php _e('回饋', 'wc-points-rewards'); ?></span>
            </div>
        </div>
        
        <div class="progress-details">
            <div class="progress-amounts">
                <span class="target-amount"><?php echo wc_price($tier_progress['next_tier']->min_amount); ?></span>
            </div>
            
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $tier_progress['progress_percentage']; ?>%"></div>
            </div>
            
            <div class="remaining-amount">
                <?php printf(__('還需消費 %s', 'wc-points-rewards'), '<strong>' . wc_price($tier_progress['amount_to_next']) . '</strong>'); ?>
            </div>
        </div>
        
        <div class="upgrade-cta">
            <a href="<?php echo wc_get_page_permalink('shop'); ?>" class="button button-primary">
                <?php _e('立即購物', 'wc-points-rewards'); ?>
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="max-tier-section">
        <div class="max-tier-badge">🏆</div>
        <h3><?php _e('恭喜！您已達到最高會員等級', 'wc-points-rewards'); ?></h3>
        <p><?php _e('感謝您的長期支持，請繼續享受我們的優質服務！', 'wc-points-rewards'); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- 所有等級說明 -->
    <div class="all-tiers-section">
        <h3><?php _e('會員等級說明', 'wc-points-rewards'); ?></h3>
        
        <div class="tiers-list">
            <?php foreach ($all_tiers as $tier): ?>
            <div class="tier-item <?php echo $tier->id === $current_tier->id ? 'current-tier' : ''; ?>">
                <div class="tier-basic-info">
                    <div class="tier-name-badge">
                        <?php echo esc_html($tier->name); ?>
                        <?php if ($tier->id === $current_tier->id): ?>
                            <span class="current-badge"><?php _e('目前等級', 'wc-points-rewards'); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tier-requirements">
                        <?php if ($tier->min_amount > 0): ?>
                            <?php printf(__('年消費滿 %s', 'wc-points-rewards'), wc_price($tier->min_amount)); ?>
                        <?php else: ?>
                            <?php _e('無消費門檻', 'wc-points-rewards'); ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="tier-benefits-info">
                    <?php if ($tier->bonus_percentage > 0): ?>
                        <span class="benefit-item">+<?php echo wc_points_rewards_format_percentage($tier->bonus_percentage); ?> <?php _e('點數回饋', 'wc-points-rewards'); ?></span>
                    <?php else: ?>
                        <span class="benefit-item"><?php _e('基礎回饋', 'wc-points-rewards'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- 等級說明 -->
    <div class="tier-rules-section">
        <h4><?php _e('等級規則說明', 'wc-points-rewards'); ?></h4>
        <ul class="tier-rules-list">
            <li><?php _e('會員等級根據年度累積消費金額自動升級', 'wc-points-rewards'); ?></li>
            <li><?php _e('會員資格有效期為一年，到期後將重新計算等級', 'wc-points-rewards'); ?></li>
            <li><?php _e('等級加成適用於購物獲得的點數回饋', 'wc-points-rewards'); ?></li>
            <li><?php _e('消費金額以實際付款金額為準（不包含點數折抵部分）', 'wc-points-rewards'); ?></li>
        </ul>
    </div>
</div>

<style>
/* 年度消費金額與會員資格到期日區塊 */
.member-key-info-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin: 20px 0;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.key-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.key-info-item {
    display: flex;
    align-items: center;
    background: rgba(255,255,255,0.1);
    padding: 20px;
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.info-icon {
    font-size: 2.5em;
    margin-right: 15px;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
}

.info-content {
    flex: 1;
}

.info-label {
    font-size: 14px;
    opacity: 0.9;
    margin-bottom: 5px;
    font-weight: 500;
}

.info-value {
    font-size: 1.5em;
    font-weight: bold;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.no-expiry {
    color: rgba(255,255,255,0.8);
    font-style: italic;
}

.tier-expiry {
    margin-top: 10px;
    padding: 8px 12px;
    background: #f0f8ff;
    border-left: 3px solid #007cba;
    border-radius: 4px;
    font-size: 14px;
}

.expiry-label {
    color: #6c757d;
    margin-right: 5px;
}

.expiry-date {
    font-weight: bold;
    color: #007cba;
}
</style>