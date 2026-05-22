<?php
/**
 * 管理後台 - 點數記錄列表
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

$can_grant_points = current_user_can('manage_options');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('點數記錄管理', 'wc-points-rewards'); ?></h1>
    
    <hr class="wp-header-end">

    <?php if ($can_grant_points): ?>
    <div class="wcpr-manual-points-card">
        <h2><?php _e('手動發放點數', 'wc-points-rewards'); ?></h2>
        <div id="wcpr-manual-points-message" style="display:none;"></div>
        <form id="wcpr-manual-points-form" onsubmit="return false;">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wcpr-user-keyword"><?php _e('搜尋用戶', 'wc-points-rewards'); ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="wcpr-user-keyword"
                            class="regular-text"
                            autocomplete="off"
                            placeholder="<?php _e('輸入關鍵字即時下拉搜尋', 'wc-points-rewards'); ?>"
                        />
                        <input type="hidden" id="wcpr-user-id" value="">
                        <div id="wcpr-user-dropdown" class="wcpr-user-dropdown" style="display:none;"></div>
                        <p class="description"><?php _e('可搜尋姓名、帳號或 Email。', 'wc-points-rewards'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wcpr-points"><?php _e('發放點數', 'wc-points-rewards'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="wcpr-points" class="small-text" min="0.01" step="0.01" value="0">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wcpr-reason"><?php _e('原因', 'wc-points-rewards'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="wcpr-reason" class="regular-text" placeholder="<?php _e('選填', 'wc-points-rewards'); ?>">
                    </td>
                </tr>
            </table>
            <p>
                <button type="button" id="wcpr-grant-points-btn" class="button button-primary"><?php _e('發放點數', 'wc-points-rewards'); ?></button>
            </p>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- 篩選選項 -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" action="">
                <input type="hidden" name="page" value="wc-points-rewards-points">
                
                <select name="type">
                    <option value=""><?php _e('所有類型', 'wc-points-rewards'); ?></option>
                    <option value="earned" <?php selected($type_filter, 'earned'); ?>><?php _e('獲得點數', 'wc-points-rewards'); ?></option>
                    <option value="redeemed" <?php selected($type_filter, 'redeemed'); ?>><?php _e('使用點數', 'wc-points-rewards'); ?></option>
                    <option value="expired" <?php selected($type_filter, 'expired'); ?>><?php _e('過期點數', 'wc-points-rewards'); ?></option>
                    <option value="admin" <?php selected($type_filter, 'admin'); ?>><?php _e('管理員調整', 'wc-points-rewards'); ?></option>
                </select>
                
                <input type="text" name="search" placeholder="<?php _e('搜尋用戶或描述', 'wc-points-rewards'); ?>" value="<?php echo esc_attr($search); ?>">
                
                <input type="submit" class="button" value="<?php _e('篩選', 'wc-points-rewards'); ?>">
            </form>
        </div>
    </div>
    
    <?php if (!empty($records)): ?>
    <!-- 點數記錄表格 -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('用戶', 'wc-points-rewards'); ?></th>
                <th><?php _e('點數變化', 'wc-points-rewards'); ?></th>
                <th><?php _e('類型', 'wc-points-rewards'); ?></th>
                <th><?php _e('說明', 'wc-points-rewards'); ?></th>
                <th><?php _e('日期', 'wc-points-rewards'); ?></th>
                <th><?php _e('到期日', 'wc-points-rewards'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $record): ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($record->display_name ?: __('未知用戶', 'wc-points-rewards')); ?></strong><br>
                    <small><?php echo esc_html($record->user_email); ?></small>
                </td>
                <td>
                    <span class="points-amount <?php echo floatval($record->points) > 0 ? 'positive' : 'negative'; ?>">
                        <?php echo floatval($record->points) > 0 ? '+' : ''; ?><?php echo wc_points_rewards_number_format(floatval($record->points)); ?>
                    </span>
                </td>
                <td>
                    <span class="points-type-<?php echo esc_attr($record->type); ?>">
                        <?php
                        switch ($record->type) {
                            case 'earned':
                                _e('獲得', 'wc-points-rewards');
                                break;
                            case 'redeemed':
                                _e('使用', 'wc-points-rewards');
                                break;
                            case 'expired':
                                _e('過期', 'wc-points-rewards');
                                break;
                            case 'admin':
                                _e('調整', 'wc-points-rewards');
                                break;
                        }
                        ?>
                    </span>
                </td>
                <td><?php echo esc_html($record->description); ?></td>
                <td><?php echo date('Y-m-d H:i', strtotime($record->created_at)); ?></td>
                <td>
                    <?php if ($record->expiry_date): ?>
                        <?php echo date('Y-m-d', strtotime($record->expiry_date)); ?>
                        <?php if (strtotime($record->expiry_date) < strtotime('+30 days')): ?>
                            <span style="color: #d63638;">⚠️</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color: #8c8f94;">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- 分頁 -->
    <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            $page_links = paginate_links(array(
                'base' => admin_url('admin.php?page=wc-points-rewards-points&%_%'),
                'format' => '&paged=%#%',
                'current' => $current_page,
                'total' => $total_pages,
                'prev_text' => __('« 上一頁', 'wc-points-rewards'),
                'next_text' => __('下一頁 »', 'wc-points-rewards'),
            ));
            echo $page_links;
            ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <div class="no-records-message">
        <p><?php _e('找不到符合條件的點數記錄', 'wc-points-rewards'); ?></p>
    </div>
    <?php endif; ?>
</div>

<style>
.points-amount.positive {
    color: #00a32a;
    font-weight: bold;
}

.points-amount.negative {
    color: #d63638;
    font-weight: bold;
}

.points-type-earned { color: #00a32a; }
.points-type-redeemed { color: #d63638; }
.points-type-expired { color: #8c8f94; }
.points-type-admin { color: #2271b1; }

.no-records-message {
    text-align: center;
    padding: 40px;
    background: white;
    border: 1px solid #c3c4c7;
    margin-top: 20px;
}

.wcpr-manual-points-card {
    margin: 16px 0;
    padding: 16px;
    border: 1px solid #c3c4c7;
    background: #fff;
}

.wcpr-manual-points-card h2 {
    margin-top: 0;
}

.wcpr-user-dropdown {
    margin-top: 4px;
    border: 1px solid #c3c4c7;
    background: #fff;
    max-width: 420px;
    max-height: 220px;
    overflow-y: auto;
}

.wcpr-user-option {
    padding: 8px 10px;
    border-bottom: 1px solid #f0f0f1;
    cursor: pointer;
}

.wcpr-user-option:last-child {
    border-bottom: none;
}

.wcpr-user-option:hover {
    background: #f6f7f7;
}

.wcpr-user-option small {
    display: block;
    color: #646970;
}
</style>

<?php if ($can_grant_points): ?>
<script type="text/javascript">
jQuery(function($) {
    var adminNonce = (window.wcPointsRewardsAdmin && wcPointsRewardsAdmin.nonce) ? wcPointsRewardsAdmin.nonce : '';
    var $keyword = $('#wcpr-user-keyword');
    var $userId = $('#wcpr-user-id');
    var $dropdown = $('#wcpr-user-dropdown');
    var $message = $('#wcpr-manual-points-message');
    var timer = null;

    function hideDropdown() {
        $dropdown.hide().empty();
    }

    function showMessage(type, text) {
        var cls = type === 'error' ? 'notice notice-error' : 'notice notice-success';
        $message.removeClass().addClass(cls).empty().append($('<p>').text(text)).show();
    }

    $keyword.on('input', function() {
        var keyword = $.trim($keyword.val());
        $userId.val('');

        if (timer) {
            clearTimeout(timer);
        }

        if (keyword.length < 1) {
            hideDropdown();
            return;
        }

        timer = setTimeout(function() {
            $.post(ajaxurl, {
                action: 'wc_points_rewards_search_users',
                nonce: adminNonce,
                keyword: keyword
            }).done(function(response) {
                if (!response || !response.success || !response.data || !response.data.length) {
                    hideDropdown();
                    return;
                }

                var html = '';
                $.each(response.data, function(_, user) {
                    var label = $('<div>').text(user.display_name).html();
                    var sub = $('<div>').text(user.user_email + ' (' + user.user_login + ')').html();
                    html += '<div class="wcpr-user-option" data-id="' + user.id + '" data-label="' + label + '">' +
                        '<strong>' + label + '</strong><small>' + sub + '</small></div>';
                });

                $dropdown.html(html).show();
            }).fail(function() {
                hideDropdown();
            });
        }, 250);
    });

    $(document).on('click', '.wcpr-user-option', function() {
        $userId.val($(this).data('id'));
        $keyword.val($(this).data('label'));
        hideDropdown();
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#wcpr-user-keyword, #wcpr-user-dropdown').length) {
            hideDropdown();
        }
    });

    $('#wcpr-grant-points-btn').on('click', function() {
        var userId = parseInt($userId.val(), 10) || 0;
        var points = parseFloat($('#wcpr-points').val() || 0);
        var reason = $.trim($('#wcpr-reason').val());

        $message.hide();

        if (!userId) {
            showMessage('error', '<?php echo esc_js(__('請先從下拉選單選擇用戶', 'wc-points-rewards')); ?>');
            return;
        }
        if (!(points > 0)) {
            showMessage('error', '<?php echo esc_js(__('請輸入大於 0 的點數', 'wc-points-rewards')); ?>');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'wc_points_rewards_admin_add_points',
            nonce: adminNonce,
            user_id: userId,
            points: points,
            reason: reason
        }).done(function(response) {
            if (response && response.success) {
                showMessage('success', response.data.message || '<?php echo esc_js(__('發放成功', 'wc-points-rewards')); ?>');
                setTimeout(function() {
                    window.location.reload();
                }, 900);
                return;
            }

            var errorMessage = (response && response.data && response.data.message) ? response.data.message : (response && response.data ? response.data : '<?php echo esc_js(__('發放失敗', 'wc-points-rewards')); ?>');
            showMessage('error', errorMessage);
        }).fail(function() {
            showMessage('error', '<?php echo esc_js(__('發放失敗，請稍後再試', 'wc-points-rewards')); ?>');
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });
});
</script>
<?php endif; ?>