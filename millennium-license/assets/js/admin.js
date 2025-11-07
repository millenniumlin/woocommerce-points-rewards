/**
 * Millennium License Manager - Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // 確認刪除授權碼
        $('.millennium-licenses-table .delete').on('click', function(e) {
            if (!confirm(millenniumLicense.strings?.confirmDelete || '確定要刪除此授權碼嗎？')) {
                e.preventDefault();
                return false;
            }
        });
        
        // 複製授權碼到剪貼簿
        $('.license-key').on('click', function() {
            var $this = $(this);
            var text = $this.text();
            
            // 創建臨時 textarea
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            
            // 顯示提示
            var originalBg = $this.css('background-color');
            $this.css('background-color', '#d4edda');
            
            setTimeout(function() {
                $this.css('background-color', originalBg);
            }, 500);
        });
        
        // 複製 API 密鑰
        $('.api-key').on('click', function() {
            var $this = $(this);
            var text = $this.text();
            
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            
            alert(millenniumLicense.strings?.apiKeyCopied || 'API 密鑰已複製到剪貼簿');
        });
        
        // 產品選擇器改進
        if (typeof $.fn.select2 !== 'undefined') {
            $('#product_id').select2({
                placeholder: millenniumLicense.strings?.selectProduct || '選擇產品',
                allowClear: true
            });
        }
        
        // 到期時間欄位預設值設定
        $('#expires_at').on('focus', function() {
            if (!$(this).val()) {
                var defaultDays = parseInt($(this).closest('form').find('[name="default_expiry_days"]').val()) || 365;
                var date = new Date();
                date.setDate(date.getDate() + defaultDays);
                
                var year = date.getFullYear();
                var month = String(date.getMonth() + 1).padStart(2, '0');
                var day = String(date.getDate()).padStart(2, '0');
                var hours = String(date.getHours()).padStart(2, '0');
                var minutes = String(date.getMinutes()).padStart(2, '0');
                
                $(this).val(year + '-' + month + '-' + day + 'T' + hours + ':' + minutes);
            }
        });
        
        // 批次操作確認
        $('select[name="action"], select[name="action2"]').on('change', function() {
            var action = $(this).val();
            
            if (action === 'delete') {
                $(this).closest('form').on('submit', function(e) {
                    var checkedCount = $('input[name="licenses[]"]:checked').length;
                    
                    if (checkedCount > 0) {
                        var message = millenniumLicense.strings?.confirmBulkDelete || 
                                    '確定要刪除 ' + checkedCount + ' 個授權碼嗎？';
                        
                        if (!confirm(message.replace('%d', checkedCount))) {
                            e.preventDefault();
                            return false;
                        }
                    }
                });
            }
        });
        
        // 授權碼格式驗證
        $('#license_key_format').on('blur', function() {
            var format = $(this).val();
            var pattern = /^[X\-]+$/;
            
            if (!pattern.test(format)) {
                alert(millenniumLicense.strings?.invalidFormat || 
                     '授權碼格式無效。請使用 X 代表字元，- 代表分隔符號。');
                $(this).focus();
            }
        });
        
        // AJAX 生成新授權碼（管理介面用）
        $('#generate-new-license').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $spinner = $('<span class="millennium-license-loading"></span>');
            
            $button.prop('disabled', true).after($spinner);
            
            $.ajax({
                url: millenniumLicense.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'millennium_generate_license',
                    nonce: millenniumLicense.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(millenniumLicense.strings?.licenseGenerated || 
                             '授權碼已生成：' + response.data.license_key);
                        location.reload();
                    } else {
                        alert(response.data.message || millenniumLicense.strings?.error || '發生錯誤');
                    }
                },
                error: function() {
                    alert(millenniumLicense.strings?.error || '發生錯誤');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.remove();
                }
            });
        });
        
    });
    
})(jQuery);
