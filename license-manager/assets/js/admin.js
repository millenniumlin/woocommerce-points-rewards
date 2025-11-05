/**
 * 管理介面 JavaScript
 * 
 * @package ML_License_Manager
 */

(function($) {
    'use strict';
    
    // 當 DOM 準備就緒時執行
    $(document).ready(function() {
        
        // 確認刪除
        $('.ml-delete-license').on('click', function(e) {
            if (!confirm(mlLicenseManager.i18n.confirmDelete)) {
                e.preventDefault();
                return false;
            }
        });
        
        // 確認撤銷
        $('.ml-revoke-license').on('click', function(e) {
            if (!confirm(mlLicenseManager.i18n.confirmRevoke)) {
                e.preventDefault();
                return false;
            }
        });
        
        // 複製授權碼到剪貼簿
        $('.ml-copy-license').on('click', function(e) {
            e.preventDefault();
            
            var $this = $(this);
            var licenseKey = $this.data('license-key');
            
            // 創建臨時輸入框
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(licenseKey).select();
            document.execCommand('copy');
            $temp.remove();
            
            // 顯示提示
            var originalText = $this.text();
            $this.text('已複製！');
            
            setTimeout(function() {
                $this.text(originalText);
            }, 2000);
        });
        
        // 自動隱藏通知
        $('.notice.is-dismissible').each(function() {
            var $notice = $(this);
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        });
        
        // 表單驗證
        $('form[name="ml_add_license"]').on('submit', function(e) {
            var activationLimit = $('#activation_limit').val();
            
            if (activationLimit < 1) {
                alert('啟用次數限制必須大於 0');
                e.preventDefault();
                return false;
            }
        });
        
        // 設定表單驗證
        $('form[name="ml_save_settings"]').on('submit', function(e) {
            var licenseKeyLength = $('#license_key_length').val();
            
            if (licenseKeyLength < 16 || licenseKeyLength > 64) {
                alert('授權碼長度必須在 16 到 64 之間');
                e.preventDefault();
                return false;
            }
        });
    });
    
})(jQuery);
