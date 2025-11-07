<?php
/**
 * Admin Licenses Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$list_table = new Millennium_License_List_Table();
$list_table->prepare_items();

?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('授權碼', 'millennium-license'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=millennium-license-new'); ?>" class="page-title-action">
        <?php _e('新增授權碼', 'millennium-license'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <?php if (isset($_GET['message'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                switch ($_GET['message']) {
                    case 'created':
                        _e('授權碼已成功建立。', 'millennium-license');
                        break;
                    case 'updated':
                        _e('授權碼已成功更新。', 'millennium-license');
                        break;
                    case 'deleted':
                        _e('授權碼已成功刪除。', 'millennium-license');
                        break;
                }
                ?>
            </p>
        </div>
    <?php endif; ?>
    
    <form method="get">
        <input type="hidden" name="page" value="millennium-license">
        <?php $list_table->display(); ?>
    </form>
</div>
