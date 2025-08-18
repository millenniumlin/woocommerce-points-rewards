/**
 * WooCommerce Points & Rewards - Frontend JavaScript
 * 提供購物車和結帳頁面的點數使用功能
 */

(function($) {
    'use strict';
    
    /**
     * WooCommerce Points Rewards Frontend 類別
     */
    var WCPointsRewardsFrontend = {
        
        /**
         * 初始化
         */
        init: function() {
            this.bindEvents();
            this.setupCompatibility();
            this.initializePointsSection();
        },
        
        /**
         * 綁定事件
         */
        bindEvents: function() {
            // 點數使用快捷按鈕
            $(document).on('click', '.points-quick-use', this.handleQuickUse);
            
            // 應用點數折扣
            $(document).on('click', '.wc-points-apply-discount', this.handleApplyDiscount);
            
            // 移除點數折扣
            $(document).on('click', '.wc-points-remove-discount', this.handleRemoveDiscount);
            
            // 點數輸入框變化
            $(document).on('input', '.points-input', this.handlePointsInput);
            
            // 購物車更新事件
            $(document.body).on('updated_cart_totals', this.refreshPointsSection);
            $(document.body).on('updated_checkout', this.refreshPointsSection);
            
            // 當點數區塊載入時
            $(document).on('wc_points_rewards_section_loaded', this.initializePointsSection);
        },
        
        /**
         * 設置相容性
         */
        setupCompatibility: function() {
            // 檢查 WooCommerce 版本相容性
            if (typeof wc_checkout_params !== 'undefined') {
                this.isCheckoutPage = true;
            }
            
            if (typeof wc_cart_params !== 'undefined') {
                this.isCartPage = true;
            }
            
            // 確保 wcPointsRewards 對象存在
            if (typeof wcPointsRewards === 'undefined') {
                window.wcPointsRewards = {
                    ajaxUrl: wc_checkout_params && wc_checkout_params.ajax_url ? wc_checkout_params.ajax_url : '/wp-admin/admin-ajax.php',
                    nonce: '',
                    messages: {
                        loading: '載入中...',
                        error: '發生錯誤，請稍後再試',
                        success: '操作成功',
                        insufficient_points: '點數不足',
                        invalid_amount: '請輸入有效的點數'
                    }
                };
            }
        },
        
        /**
         * 初始化點數區塊
         */
        initializePointsSection: function() {
            // 檢查是否存在點數區塊
            if ($('.wc-points-redemption-wrapper').length === 0) {
                return;
            }
            
            // 初始化工具提示
            this.initTooltips();
            
            // 設置輸入驗證
            this.setupInputValidation();
            
            // 檢查現有的點數使用狀態
            this.checkExistingDiscount();
        },
        
        /**
         * 處理快速使用按鈕
         */
        handleQuickUse: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var points = $button.data('points');
            var $input = $('#points-to-use');
            
            if ($input.length && points) {
                $input.val(points).trigger('input');
                
                // 視覺反饋
                $button.addClass('selected');
                setTimeout(function() {
                    $button.removeClass('selected');
                }, 200);
            }
        },
        
        /**
         * 處理點數輸入
         */
        handlePointsInput: function(e) {
            var $input = $(this);
            var value = parseFloat($input.val());
            var max = parseFloat($input.attr('max'));
            var min = parseFloat($input.attr('min')) || 0;
            
            // 驗證輸入值
            if (isNaN(value) || value < min) {
                $input.removeClass('valid').addClass('invalid');
                return;
            }
            
            if (max && value > max) {
                $input.val(max);
                value = max;
            }
            
            $input.removeClass('invalid').addClass('valid');
            
            // 更新應用按鈕狀態
            var $applyButton = $('.wc-points-apply-discount');
            $applyButton.prop('disabled', false);
        },
        
        /**
         * 處理應用點數折扣
         */
        handleApplyDiscount: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $input = $('#points-to-use');
            var points = parseFloat($input.val());
            var nonce = $button.data('nonce');
            
            // 驗證輸入
            if (!points || points <= 0) {
                WCPointsRewardsFrontend.showMessage(wcPointsRewards.messages.invalid_amount, 'error');
                return;
            }
            
            // 設置載入狀態
            WCPointsRewardsFrontend.setLoadingState($button, true);
            
            // 發送 AJAX 請求
            $.ajax({
                url: wcPointsRewards.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_points_rewards_apply_discount',
                    points: points,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        WCPointsRewardsFrontend.showMessage(response.data.message, 'success');
                        WCPointsRewardsFrontend.updateCartTotals();
                    } else {
                        WCPointsRewardsFrontend.showMessage(response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    WCPointsRewardsFrontend.showMessage(wcPointsRewards.messages.error, 'error');
                },
                complete: function() {
                    WCPointsRewardsFrontend.setLoadingState($button, false);
                }
            });
        },
        
        /**
         * 處理移除點數折扣
         */
        handleRemoveDiscount: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var nonce = $button.data('nonce');
            
            // 設置載入狀態
            WCPointsRewardsFrontend.setLoadingState($button, true);
            
            // 發送 AJAX 請求
            $.ajax({
                url: wcPointsRewards.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_points_rewards_remove_discount',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        WCPointsRewardsFrontend.showMessage(response.data.message, 'success');
                        WCPointsRewardsFrontend.updateCartTotals();
                    } else {
                        WCPointsRewardsFrontend.showMessage(response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    WCPointsRewardsFrontend.showMessage(wcPointsRewards.messages.error, 'error');
                },
                complete: function() {
                    WCPointsRewardsFrontend.setLoadingState($button, false);
                }
            });
        },
        
        /**
         * 顯示訊息
         */
        showMessage: function(message, type) {
            var $messagesContainer = $('.points-messages');
            
            if ($messagesContainer.length === 0) {
                $messagesContainer = $('<div class="points-messages"></div>');
                $('.wc-points-redemption-wrapper').append($messagesContainer);
            }
            
            var $message = $('<div class="wc-points-message ' + type + '">' + message + '</div>');
            
            $messagesContainer.empty().append($message).show();
            
            // 自動隱藏成功訊息
            if (type === 'success') {
                setTimeout(function() {
                    $message.fadeOut(function() {
                        if ($messagesContainer.children().length === 0) {
                            $messagesContainer.hide();
                        }
                    });
                }, 5000);
            }
        },
        
        /**
         * 設置載入狀態
         */
        setLoadingState: function($button, loading) {
            if (loading) {
                $button.prop('disabled', true)
                       .data('original-text', $button.text())
                       .text(wcPointsRewards.messages.loading);
            } else {
                $button.prop('disabled', false)
                       .text($button.data('original-text') || $button.text());
            }
        },
        
        /**
         * 更新購物車總計
         */
        updateCartTotals: function() {
            if (this.isCheckoutPage) {
                $(document.body).trigger('update_checkout');
            } else if (this.isCartPage) {
                $(document.body).trigger('wc_update_cart');
            } else {
                // 通用更新方法
                location.reload();
            }
        },
        
        /**
         * 刷新點數區塊
         */
        refreshPointsSection: function() {
            // 在購物車或結帳頁面更新後刷新點數區塊
            setTimeout(function() {
                WCPointsRewardsFrontend.initializePointsSection();
            }, 100);
        },
        
        /**
         * 初始化工具提示
         */
        initTooltips: function() {
            $('.points-info-item[title]').each(function() {
                var $this = $(this);
                $this.on('mouseenter', function() {
                    var tooltip = '<div class="points-tooltip">' + $this.attr('title') + '</div>';
                    $('body').append(tooltip);
                    
                    var $tooltip = $('.points-tooltip');
                    var offset = $this.offset();
                    
                    $tooltip.css({
                        position: 'absolute',
                        top: offset.top - $tooltip.outerHeight() - 5,
                        left: offset.left + ($this.outerWidth() - $tooltip.outerWidth()) / 2,
                        backgroundColor: '#333',
                        color: '#fff',
                        padding: '5px 10px',
                        borderRadius: '4px',
                        fontSize: '12px',
                        zIndex: 9999
                    });
                });
                
                $this.on('mouseleave', function() {
                    $('.points-tooltip').remove();
                });
            });
        },
        
        /**
         * 設置輸入驗證
         */
        setupInputValidation: function() {
            $('.points-input').on('keypress', function(e) {
                // 只允許數字、小數點和退格鍵
                var charCode = e.which ? e.which : e.keyCode;
                if (charCode != 46 && charCode > 31 && (charCode < 48 || charCode > 57)) {
                    return false;
                }
                
                // 避免多個小數點
                if (charCode == 46 && $(this).val().indexOf('.') != -1) {
                    return false;
                }
                
                return true;
            });
        },
        
        /**
         * 檢查現有的折扣狀態
         */
        checkExistingDiscount: function() {
            var $appliedSection = $('.points-applied-section');
            if ($appliedSection.length > 0) {
                // 如果已經有應用的點數，隱藏輸入區域
                $('.points-input-section').hide();
            }
        }
    };
    
    /**
     * 當文件準備就緒時初始化
     */
    $(document).ready(function() {
        WCPointsRewardsFrontend.init();
    });
    
    /**
     * 對外公開 API
     */
    window.WCPointsRewardsFrontend = WCPointsRewardsFrontend;
    
})(jQuery);