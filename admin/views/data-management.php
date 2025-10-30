<?php
/**
 * 管理後台 - 資料管理頁面
 * 
 * @package WC_Points_Rewards
 */

if (!defined('ABSPATH')) {
    exit;
}

// 處理匯入結果訊息
if (isset($_GET['import'])) {
    if ($_GET['import'] === 'success') {
        $imported = isset($_GET['imported']) ? intval($_GET['imported']) : 0;
        echo '<div class="notice notice-success is-dismissible"><p>' . 
             sprintf(__('成功匯入 %d 筆資料', 'wc-points-rewards'), $imported) . 
             '</p></div>';
    } elseif ($_GET['import'] === 'error') {
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : __('匯入失敗', 'wc-points-rewards');
        echo '<div class="notice notice-error is-dismissible"><p>' . 
             esc_html($message) . 
             '</p></div>';
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('資料管理', 'wc-points-rewards'); ?></h1>
    
    <hr class="wp-header-end">
    
    <div class="data-management-container">
        
        <!-- 匯出區塊 -->
        <div class="postbox" style="margin-top: 20px;">
            <div class="postbox-header">
                <h2 class="hndle"><?php _e('匯出資料', 'wc-points-rewards'); ?></h2>
            </div>
            <div class="inside">
                <p class="description">
                    <?php _e('匯出所有點數記錄、會員等級、用戶統計資料和設定。匯出的檔案為 JSON 格式，可用於備份或轉移資料。', 'wc-points-rewards'); ?>
                </p>
                
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 15px;">
                    <?php wp_nonce_field('wc_points_rewards_export_data', 'export_nonce'); ?>
                    <input type="hidden" name="action" value="wc_points_rewards_export_data">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('匯出類型', 'wc-points-rewards'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio" name="export_type" value="all" checked>
                                        <strong><?php _e('所有資料', 'wc-points-rewards'); ?></strong>
                                        <span class="description"><?php _e('（包含點數記錄、會員等級、用戶統計和設定）', 'wc-points-rewards'); ?></span>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="radio" name="export_type" value="points">
                                        <?php _e('僅點數記錄', 'wc-points-rewards'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="radio" name="export_type" value="tiers">
                                        <?php _e('僅會員等級', 'wc-points-rewards'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="radio" name="export_type" value="user_stats">
                                        <?php _e('僅用戶統計', 'wc-points-rewards'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="radio" name="export_type" value="settings">
                                        <?php _e('僅設定', 'wc-points-rewards'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="export_data" class="button button-primary" 
                               value="<?php _e('匯出資料', 'wc-points-rewards'); ?>">
                    </p>
                </form>
            </div>
        </div>
        
        <!-- 匯入區塊 -->
        <div class="postbox" style="margin-top: 20px;">
            <div class="postbox-header">
                <h2 class="hndle"><?php _e('匯入資料', 'wc-points-rewards'); ?></h2>
            </div>
            <div class="inside">
                <div class="notice notice-warning inline">
                    <p>
                        <strong><?php _e('警告：', 'wc-points-rewards'); ?></strong>
                        <?php _e('匯入資料前，請先備份現有資料。匯入操作可能會覆蓋或新增資料到資料庫中。', 'wc-points-rewards'); ?>
                    </p>
                </div>
                
                <p class="description">
                    <?php _e('上傳之前匯出的 JSON 檔案來匯入資料。系統會自動驗證檔案格式和內容。', 'wc-points-rewards'); ?>
                </p>
                
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" 
                      enctype="multipart/form-data" style="margin-top: 15px;">
                    <?php wp_nonce_field('wc_points_rewards_import_data', 'import_nonce'); ?>
                    <input type="hidden" name="action" value="wc_points_rewards_import_data">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="import_file"><?php _e('選擇檔案', 'wc-points-rewards'); ?></label>
                            </th>
                            <td>
                                <input type="file" name="import_file" id="import_file" 
                                       accept=".json,application/json" required>
                                <p class="description">
                                    <?php _e('請選擇之前匯出的 JSON 檔案', 'wc-points-rewards'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('匯入模式', 'wc-points-rewards'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio" name="import_mode" value="add" checked>
                                        <strong><?php _e('新增模式', 'wc-points-rewards'); ?></strong>
                                        <span class="description"><?php _e('（將資料新增到現有記錄中，不會刪除現有資料）', 'wc-points-rewards'); ?></span>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="radio" name="import_mode" value="replace">
                                        <strong><?php _e('替換模式', 'wc-points-rewards'); ?></strong>
                                        <span class="description" style="color: #d63638;">
                                            <?php _e('（會刪除相同 ID 的記錄後再匯入，請謹慎使用）', 'wc-points-rewards'); ?>
                                        </span>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="import_data" class="button button-primary" 
                               value="<?php _e('匯入資料', 'wc-points-rewards'); ?>"
                               onclick="return confirm('<?php echo esc_js(__('確定要匯入資料嗎？建議先備份現有資料。', 'wc-points-rewards')); ?>');">
                    </p>
                </form>
            </div>
        </div>
        
        <!-- 使用說明 -->
        <div class="postbox" style="margin-top: 20px;">
            <div class="postbox-header">
                <h2 class="hndle"><?php _e('使用說明', 'wc-points-rewards'); ?></h2>
            </div>
            <div class="inside">
                <h3><?php _e('匯出資料', 'wc-points-rewards'); ?></h3>
                <ol>
                    <li><?php _e('選擇要匯出的資料類型（建議選擇「所有資料」以進行完整備份）', 'wc-points-rewards'); ?></li>
                    <li><?php _e('點擊「匯出資料」按鈕', 'wc-points-rewards'); ?></li>
                    <li><?php _e('系統會自動下載一個 JSON 格式的檔案', 'wc-points-rewards'); ?></li>
                    <li><?php _e('請妥善保存此檔案，以便日後匯入使用', 'wc-points-rewards'); ?></li>
                </ol>
                
                <h3><?php _e('匯入資料', 'wc-points-rewards'); ?></h3>
                <ol>
                    <li><?php _e('選擇之前匯出的 JSON 檔案', 'wc-points-rewards'); ?></li>
                    <li><?php _e('選擇匯入模式：', 'wc-points-rewards'); ?>
                        <ul style="margin-left: 20px; list-style: disc;">
                            <li><strong><?php _e('新增模式：', 'wc-points-rewards'); ?></strong> 
                                <?php _e('將資料新增到現有記錄中，適合從其他站點匯入資料', 'wc-points-rewards'); ?></li>
                            <li><strong><?php _e('替換模式：', 'wc-points-rewards'); ?></strong> 
                                <?php _e('會刪除相同 ID 的記錄後再匯入，適合還原備份', 'wc-points-rewards'); ?></li>
                        </ul>
                    </li>
                    <li><?php _e('點擊「匯入資料」按鈕', 'wc-points-rewards'); ?></li>
                    <li><?php _e('系統會驗證檔案格式並執行匯入', 'wc-points-rewards'); ?></li>
                </ol>
                
                <h3><?php _e('注意事項', 'wc-points-rewards'); ?></h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('匯入前請務必先備份現有資料', 'wc-points-rewards'); ?></li>
                    <li><?php _e('匯入時會根據用戶名稱或郵箱對應到現有用戶', 'wc-points-rewards'); ?></li>
                    <li><?php _e('如果找不到對應的用戶，該筆記錄會被跳過', 'wc-points-rewards'); ?></li>
                    <li><?php _e('匯入過程中如發生錯誤，所有變更都會回滾', 'wc-points-rewards'); ?></li>
                    <li><?php _e('大量資料匯入可能需要較長時間，請耐心等候', 'wc-points-rewards'); ?></li>
                </ul>
            </div>
        </div>
        
    </div>
</div>

<style>
.data-management-container .postbox {
    max-width: 900px;
}

.data-management-container .inside {
    padding: 15px;
}

.data-management-container fieldset label {
    display: block;
    margin-bottom: 8px;
}

.data-management-container .notice.inline {
    margin: 0 0 15px 0;
    padding: 10px;
}
</style>
