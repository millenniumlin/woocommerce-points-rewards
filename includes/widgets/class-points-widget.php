<?php
/**
 * 點數顯示 Widget
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Points_Rewards_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'wc_points_rewards_widget',
            __('WC 點數餘額', 'wc-points-rewards'),
            array(
                'description' => __('顯示用戶的點數餘額和會員等級', 'wc-points-rewards')
            )
        );
    }
    
    public function widget($args, $instance) {
        if (!is_user_logged_in()) {
            return;
        }
        
        $title = !empty($instance['title']) ? $instance['title'] : __('我的點數', 'wc-points-rewards');
        $show_tier = !empty($instance['show_tier']);
        $show_progress = !empty($instance['show_progress']);
        
        $user_id = get_current_user_id();
        $database = WC_Points_Rewards_Database::instance();
        $tier_manager = WC_Points_Rewards_Member_Tier::instance();
        
        $current_points = $database->get_user_points($user_id);
        $current_tier = $database->get_user_current_tier($user_id);
        $tier_progress = $tier_manager->get_tier_progress($user_id);
        
        echo $args['before_widget'];
        
        if ($title) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }
        
        echo '<div class="wc-points-widget-content">';
        
        // 點數餘額
        echo '<div class="widget-points-balance">';
        echo '<div class="points-amount">' . wc_points_rewards_number_format($current_points) . '</div>';
        echo '<div class="points-label">' . __('可用點數', 'wc-points-rewards') . '</div>';
        echo '</div>';
        
        // 會員等級
        if ($show_tier && $current_tier) {
            echo '<div class="widget-member-tier">';
            echo '<div class="tier-name">' . esc_html($current_tier->name) . '</div>';
            if ($current_tier->bonus_percentage > 0) {
                echo '<div class="tier-benefit">+' . wc_points_rewards_format_percentage($current_tier->bonus_percentage) . ' ' . __('回饋', 'wc-points-rewards') . '</div>';
            }
            echo '</div>';
        }
        
        // 升級進度
        if ($show_progress && $tier_progress['next_tier']) {
            echo '<div class="widget-tier-progress">';
            echo '<div class="progress-text">';
            printf(__('升級至 %s', 'wc-points-rewards'), '<strong>' . esc_html($tier_progress['next_tier']->name) . '</strong>');
            echo '</div>';
            echo '<div class="progress-bar">';
            echo '<div class="progress-fill" style="width: ' . $tier_progress['progress_percentage'] . '%"></div>';
            echo '</div>';
            echo '<div class="progress-amount">';
            printf(__('還需 %s', 'wc-points-rewards'), wc_price($tier_progress['amount_to_next']));
            echo '</div>';
            echo '</div>';
        }
        
        // 連結
        echo '<div class="widget-links">';
        echo '<a href="' . wc_points_rewards_get_account_endpoint_url('points-rewards') . '" class="points-link">' . __('查看詳情', 'wc-points-rewards') . '</a>';
        echo '</div>';
        
        echo '</div>';
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('我的點數', 'wc-points-rewards');
        $show_tier = !empty($instance['show_tier']);
        $show_progress = !empty($instance['show_progress']);
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('標題:', 'wc-points-rewards'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_tier); ?> id="<?php echo esc_attr($this->get_field_id('show_tier')); ?>" name="<?php echo esc_attr($this->get_field_name('show_tier')); ?>" />
            <label for="<?php echo esc_attr($this->get_field_id('show_tier')); ?>"><?php _e('顯示會員等級', 'wc-points-rewards'); ?></label>
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_progress); ?> id="<?php echo esc_attr($this->get_field_id('show_progress')); ?>" name="<?php echo esc_attr($this->get_field_name('show_progress')); ?>" />
            <label for="<?php echo esc_attr($this->get_field_id('show_progress')); ?>"><?php _e('顯示升級進度', 'wc-points-rewards'); ?></label>
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['show_tier'] = (!empty($new_instance['show_tier'])) ? 1 : 0;
        $instance['show_progress'] = (!empty($new_instance['show_progress'])) ? 1 : 0;
        
        return $instance;
    }
}