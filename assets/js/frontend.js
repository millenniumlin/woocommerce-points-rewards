/**
 * WooCommerce Points & Rewards - 前端 JavaScript
 * 
 * @package WC_Points_Rewards
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // 點數使用功能
    var PointsRedemption = {
        
        init: function() {
            this.bindEvents();
            this.updateCartOnPointsChange();
        },
        
        bindEvents: function() {
            // 快速使用點數按鈕
            $(document).on('click', '.points-quick-use', this.handleQuickUse);
            
            // 應用點數折扣
            $(document).on('click', '.wc-points-apply-discount', this.handleApplyDiscount);
            
            // 移除點數折扣
            $(document).on('click', '.wc-points-remove-discount', this.handleRemoveDiscount);
            
            // 點數輸入框變化
            $(document).on('input', '#points-to-use', this.handlePointsInput);
            
            // 購物車更新時重新載入點數區塊
            $(document.body).on('updated_cart_totals updated_checkout', this.handleCartUpdate);
        },
        
        handleQuickUse: function(e) {
            e.preventDefault();
            var points = $(this).data('points');
            $('#points-to-use').val(points).trigger('input');
        },
        
        handleApplyDiscount: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $input = $('#points-to-use');
            var points = parseFloat($input.val());
            var nonce = $button.data('nonce');
            
            // 驗證輸入
            if (!points || points <= 0) {
                PointsRedemption.showMessage(wcPointsRewards.messages.invalid_amount, 'error');
                return;
            }
            
            // 設定按鈕載入狀態
            $button.prop('disabled', true).text(wcPointsRewards.messages.loading);
            
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
                        PointsRedemption.showMessage(response.data.message, 'success');
                        
                        // 更新購物車
                        if (typeof wc_checkout_params !== 'undefined') {
                            $('body').trigger('update_checkout');
                        } else {
                            $('[name="update_cart"]').prop('disabled', false).trigger('click');
                        }
                        
                        // 延遲重新載入以顯示更新
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                        
                    } else {
                        PointsRedemption.showMessage(response.data, 'error');
                    }
                },
                error: function() {
                    PointsRedemption.showMessage(wcPointsRewards.messages.error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('使用點數');
                }
            });
        },
        
        handleRemoveDiscount: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var nonce = $button.data('nonce');
            
            $button.prop('disabled', true).text(wcPointsRewards.messages.loading);
            
            $.ajax({
                url: wcPointsRewards.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_points_rewards_remove_discount',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        PointsRedemption.showMessage(response.data.message, 'success');
                        
                        // 更新購物車
                        if (typeof wc_checkout_params !== 'undefined') {
                            $('body').trigger('update_checkout');
                        } else {
                            $('[name="update_cart"]').prop('disabled', false).trigger('click');
                        }
                        
                        // 延遲重新載入
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                        
                    } else {
                        PointsRedemption.showMessage(response.data, 'error');
                    }
                },
                error: function() {
                    PointsRedemption.showMessage(wcPointsRewards.messages.error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('移除');
                }
            });
        },
        
        handlePointsInput: function() {
            var $input = $(this);
            var points = parseFloat($input.val());
            var maxPoints = parseFloat($input.attr('max'));
            
            // 即時驗證
            if (points > maxPoints) {
                $input.val(maxPoints);
                PointsRedemption.showMessage('超過可使用的最大點數', 'warning');
            }
            
            // 更新預覽折扣金額
            PointsRedemption.updateDiscountPreview(points);
        },
        
        updateDiscountPreview: function(points) {
            if (points > 0) {
                var discountAmount = points; // 假設 1 點 = 1 元
                var $preview = $('.discount-preview');
                
                if ($preview.length === 0) {
                    $preview = $('<div class="discount-preview"></div>').insertAfter('#points-to-use');
                }
                
                $preview.html('折抵金額：<strong>$' + discountAmount.toFixed(2) + '</strong>');
            } else {
                $('.discount-preview').remove();
            }
        },
        
        handleCartUpdate: function() {
            // 購物車更新後重新綁定事件
            setTimeout(function() {
                PointsRedemption.bindEvents();
            }, 100);
        },
        
        updateCartOnPointsChange: function() {
            // 監聽點數變化並自動更新購物車
            var pointsChangeTimer;
            $(document).on('input', '#points-to-use', function() {
                clearTimeout(pointsChangeTimer);
                pointsChangeTimer = setTimeout(function() {
                    // 可以在這裡添加即時更新邏輯
                }, 1000);
            });
        },
        
        showMessage: function(message, type) {
            var $messages = $('.points-messages');
            
            if ($messages.length === 0) {
                $messages = $('<div class="points-messages"></div>').appendTo('.wc-points-redemption-wrapper');
            }
            
            $messages.removeClass('wc-points-success wc-points-error wc-points-warning')
                    .addClass('wc-points-' + type)
                    .html(message)
                    .show();
            
            // 自動隱藏訊息
            setTimeout(function() {
                $messages.fadeOut();
            }, 5000);
            
            // 滾動到訊息位置
            $('html, body').animate({
                scrollTop: $messages.offset().top - 100
            }, 500);
        }
    };
    
    // 產品頁面點數顯示
    var ProductPoints = {
        
        init: function() {
            this.bindEvents();
            this.updateVariableProductPoints();
        },
        
        bindEvents: function() {
            // 變化產品價格改變時更新點數顯示
            $(document).on('found_variation', 'form.variations_form', this.handleVariationChange);
            $(document).on('reset_data', 'form.variations_form', this.handleVariationReset);
        },
        
        handleVariationChange: function(event, variation) {
            if (variation.display_price) {
                ProductPoints.updatePointsDisplay(variation.display_price);
            }
        },
        
        handleVariationReset: function() {
            $('.wc-points-rewards-product-points').hide();
        },
        
        updateVariableProductPoints: function() {
            // 處理變化產品的點數顯示
            if ($('.variations_form').length > 0) {
                $('.wc-points-rewards-product-points').hide();
            }
        },
        
        updatePointsDisplay: function(price) {
            var points = Math.floor(price / 100); // 每100元1點
            var $pointsDiv = $('.wc-points-rewards-product-points');
            
            if (points > 0) {
                var pointsText = '購買可得 <strong>' + points + '</strong> 點';
                $pointsDiv.find('.points-text').html(pointsText);
                $pointsDiv.show();
            } else {
                $pointsDiv.hide();
            }
        }
    };
    
    // 會員等級進度動畫
    var TierProgress = {
        
        init: function() {
            this.animateProgressBars();
            this.addProgressTooltips();
        },
        
        animateProgressBars: function() {
            $('.progress-fill').each(function() {
                var $bar = $(this);
                var width = $bar.css('width');
                
                $bar.css('width', '0%').animate({
                    width: width
                }, 1500, 'easeOutCubic');
            });
        },
        
        addProgressTooltips: function() {
            $('.progress-bar').each(function() {
                var $progressBar = $(this);
                var $fill = $progressBar.find('.progress-fill');
                var percentage = parseFloat($fill.css('width')) / parseFloat($progressBar.css('width')) * 100;
                
                $progressBar.attr('title', '進度：' + percentage.toFixed(1) + '%');
            });
        }
    };
    
    // 點數記錄篩選
    var PointsHistory = {
        
        init: function() {
            this.bindEvents();
            this.addLoadingStates();
        },
        
        bindEvents: function() {
            // 篩選表單提交
            $('.points-filter-form select').on('change', function() {
                var $form = $(this).closest('form');
                $form.find('select').prop('disabled', true);
                $form.submit();
            });
            
            // 分頁連結點擊
            $('.points-history-pagination a').on('click', function(e) {
                $(this).addClass('loading').text('載入中...');
            });
        },
        
        addLoadingStates: function() {
            // 為表格添加載入效果
            $('.wc-points-history-table tbody tr').each(function(index) {
                $(this).css('animation-delay', (index * 50) + 'ms').addClass('fade-in');
            });
        }
    };
    
    // 通知系統
    var NotificationSystem = {
        
        init: function() {
            this.checkPointsExpiry();
            this.showWelcomeMessages();
        },
        
        checkPointsExpiry: function() {
            // 檢查是否有即將到期的點數
            var $expiringCard = $('.expiring-card');
            if ($expiringCard.length > 0) {
                this.showExpiryNotification();
            }
        },
        
        showExpiryNotification: function() {
            var notification = $('<div class="points-expiry-notification">')
                .html('⚠️ 您有點數即將在30天內到期，請盡快使用！')
                .css({
                    'position': 'fixed',
                    'top': '20px',
                    'right': '20px',
                    'background': '#fff3cd',
                    'border': '1px solid #ffeaa7',
                    'color': '#856404',
                    'padding': '15px 20px',
                    'border-radius': '8px',
                    'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                    'z-index': 9999,
                    'max-width': '300px',
                    'font-size': '14px',
                    'cursor': 'pointer'
                });
            
            $('body').append(notification);
            
            // 自動隱藏
            setTimeout(function() {
                notification.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 8000);
            
            // 點擊隱藏
            notification.on('click', function() {
                $(this).fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },
        
        showWelcomeMessages: function() {
            // 顯示歡迎訊息或重要通知
            var urlParams = new URLSearchParams(window.location.search);
            var message = urlParams.get('points_message');
            
            if (message) {
                this.showFloatingMessage(decodeURIComponent(message), 'success');
            }
        },
        
        showFloatingMessage: function(message, type) {
            var className = 'points-floating-message-' + type;
            var backgroundColor = type === 'success' ? '#d4edda' : '#f8d7da';
            var borderColor = type === 'success' ? '#c3e6cb' : '#f5c6cb';
            var textColor = type === 'success' ? '#155724' : '#721c24';
            
            var notification = $('<div class="' + className + '">')
                .html(message)
                .css({
                    'position': 'fixed',
                    'top': '20px',
                    'left': '50%',
                    'transform': 'translateX(-50%)',
                    'background': backgroundColor,
                    'border': '1px solid ' + borderColor,
                    'color': textColor,
                    'padding': '15px 25px',
                    'border-radius': '8px',
                    'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                    'z-index': 9999,
                    'max-width': '400px',
                    'text-align': 'center',
                    'font-weight': 'bold'
                });
            
            $('body').append(notification);
            
            setTimeout(function() {
                notification.fadeOut(1000, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // 工具函數
    var Utils = {
        
        // 格式化數字
        formatNumber: function(number, decimals) {
            decimals = decimals || 2;
            return parseFloat(number).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },
        
        // 防抖函數
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        // 本地存儲
        storage: {
            set: function(key, value) {
                try {
                    localStorage.setItem('wc_points_rewards_' + key, JSON.stringify(value));
                } catch (e) {
                    console.warn('無法儲存到本地存儲:', e);
                }
            },
            
            get: function(key) {
                try {
                    var item = localStorage.getItem('wc_points_rewards_' + key);
                    return item ? JSON.parse(item) : null;
                } catch (e) {
                    console.warn('無法從本地存儲讀取:', e);
                    return null;
                }
            },
            
            remove: function(key) {
                try {
                    localStorage.removeItem('wc_points_rewards_' + key);
                } catch (e) {
                    console.warn('無法從本地存儲刪除:', e);
                }
            }
        }
    };
    
    // 添加 CSS 動畫
    var style = $('<style>')
        .text(`
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .fade-in {
                animation: fadeIn 0.5s ease-out forwards;
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            .points-badge:hover {
                animation: pulse 0.3s ease-in-out;
            }
            
            .loading {
                position: relative;
                color: transparent !important;
            }
            
            .loading::after {
                content: "";
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 16px;
                height: 16px;
                border: 2px solid #f3f3f3;
                border-top: 2px solid #333;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: translate(-50%, -50%) rotate(0deg); }
                100% { transform: translate(-50%, -50%) rotate(360deg); }
            }
        `);
    
    $('head').append(style);
    
    // 初始化所有模組
    PointsRedemption.init();
    ProductPoints.init();
    TierProgress.init();
    PointsHistory.init();
    NotificationSystem.init();
    
    // 點數獲得動畫系統
    var PointsAnimation = {
        
        init: function() {
            this.bindEvents();
            this.setupAnimationQueue();
        },
        
        bindEvents: function() {
            // 監聽點數獲得事件
            $(document).on('points_earned', this.handlePointsEarned);
            
            // 監聽購物車更新
            $(document.body).on('updated_cart_totals', this.handleCartUpdate);
            
            // 監聽結帳完成
            $(document).on('checkout_completed', this.handleCheckoutComplete);
        },
        
        setupAnimationQueue: function() {
            this.animationQueue = [];
            this.isAnimating = false;
        },
        
        handlePointsEarned: function(event, data) {
            PointsAnimation.showPointsEarnedAnimation(data.points, data.reason);
            PointsAnimation.updatePointsBalance(data.newBalance);
        },
        
        showPointsEarnedAnimation: function(points, reason) {
            var animation = $('<div class="points-earned-animation">')
                .html(`+${points} 點<br><small>${reason || '獲得點數'}</small>`);
            
            $('body').append(animation);
            
            // 動畫完成後移除元素
            setTimeout(function() {
                animation.remove();
            }, 3000);
            
            // 觸發點數徽章動畫
            $('.points-badge').addClass('points-badge-animated');
            setTimeout(function() {
                $('.points-badge').removeClass('points-badge-animated');
            }, 600);
        },
        
        updatePointsBalance: function(newBalance) {
            var $balanceElements = $('.points-balance, .available-points, .points-value');
            
            $balanceElements.each(function() {
                var $element = $(this);
                var currentValue = parseInt($element.text().replace(/[^\d]/g, ''));
                
                if (currentValue !== newBalance) {
                    PointsAnimation.animateNumberChange($element, currentValue, newBalance);
                }
            });
        },
        
        animateNumberChange: function($element, from, to) {
            $element.addClass('points-balance-updating');
            
            var duration = 1000;
            var startTime = performance.now();
            
            var animate = function(currentTime) {
                var elapsed = currentTime - startTime;
                var progress = Math.min(elapsed / duration, 1);
                
                var currentValue = Math.floor(from + (to - from) * progress);
                $element.text(currentValue.toLocaleString() + ' 點');
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    $element.removeClass('points-balance-updating');
                }
            };
            
            requestAnimationFrame(animate);
        },
        
        handleCartUpdate: function() {
            // 檢查是否有新的點數折扣
            PointsAnimation.checkForDiscountChanges();
        },
        
        checkForDiscountChanges: function() {
            var $discountSection = $('.wc-points-cart-discount');
            if ($discountSection.length > 0) {
                $discountSection.addClass('discount-updated');
                setTimeout(function() {
                    $discountSection.removeClass('discount-updated');
                }, 1000);
            }
        },
        
        handleCheckoutComplete: function(event, data) {
            if (data.pointsEarned) {
                PointsAnimation.showPointsEarnedAnimation(
                    data.pointsEarned, 
                    '購物完成獲得'
                );
            }
        }
    };
    
    // 即時點數計算器
    var PointsCalculator = {
        
        init: function() {
            this.bindEvents();
            this.setupCache();
        },
        
        bindEvents: function() {
            // 購物車數量變更
            $(document).on('input change', 'input[name="cart[*][qty]"]', 
                this.debounce(this.handleQuantityChange, 500));
            
            // 變化產品選擇
            $(document).on('found_variation', this.handleVariationChange);
            
            // 優惠券應用
            $(document).on('applied_coupon', this.handleCouponApplied);
        },
        
        setupCache: function() {
            this.calculationCache = new Map();
            this.lastCalculation = null;
        },
        
        handleQuantityChange: function() {
            var cartTotal = PointsCalculator.getCartTotal();
            PointsCalculator.calculateEarnablePoints(cartTotal);
        },
        
        handleVariationChange: function(event, variation) {
            if (variation.display_price) {
                PointsCalculator.calculateSingleProductPoints(variation.display_price);
            }
        },
        
        handleCouponApplied: function() {
            // 重新計算點數
            setTimeout(function() {
                var cartTotal = PointsCalculator.getCartTotal();
                PointsCalculator.calculateEarnablePoints(cartTotal);
            }, 1000);
        },
        
        getCartTotal: function() {
            var total = 0;
            $('.cart-subtotal .amount, .order-total .amount').each(function() {
                var amount = parseFloat($(this).text().replace(/[^\d.]/g, ''));
                if (!isNaN(amount)) {
                    total = Math.max(total, amount);
                }
            });
            return total;
        },
        
        calculateEarnablePoints: function(amount) {
            // 檢查快取
            var cacheKey = 'earnable_' + amount;
            if (this.calculationCache.has(cacheKey)) {
                return this.calculationCache.get(cacheKey);
            }
            
            // 使用設定中的點數規則
            var pointsPerAmount = window.wcPointsRewards?.pointsPerAmount || 100;
            var pointsAmount = window.wcPointsRewards?.pointsAmount || 1;
            
            var earnablePoints = Math.floor((amount / pointsPerAmount) * pointsAmount);
            
            // 更新顯示
            this.updateEarnablePointsDisplay(earnablePoints);
            
            // 存入快取
            this.calculationCache.set(cacheKey, earnablePoints);
            
            return earnablePoints;
        },
        
        calculateSingleProductPoints: function(price) {
            var pointsPerAmount = window.wcPointsRewards?.pointsPerAmount || 100;
            var pointsAmount = window.wcPointsRewards?.pointsAmount || 1;
            
            var earnablePoints = Math.floor((price / pointsPerAmount) * pointsAmount);
            
            var $pointsDisplay = $('.wc-points-rewards-product-points');
            if ($pointsDisplay.length > 0) {
                $pointsDisplay.find('.points-text').html(
                    `購買可得 <strong>${earnablePoints}</strong> 點`
                );
                $pointsDisplay.show();
            }
        },
        
        updateEarnablePointsDisplay: function(points) {
            var $displays = $('.earnable-points-display');
            
            if ($displays.length === 0) {
                $displays = $('<div class="earnable-points-display">')
                    .html(`完成此訂單可獲得 <strong>${points}</strong> 點`);
                $('.cart-totals, .checkout-review-order').append($displays);
            } else {
                $displays.html(`完成此訂單可獲得 <strong>${points}</strong> 點`);
            }
            
            // 添加動畫
            $displays.addClass('updated');
            setTimeout(function() {
                $displays.removeClass('updated');
            }, 500);
        },
        
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // 增強的點數到期提醒
    var ExpiryReminder = {
        
        init: function() {
            this.checkExpiringPoints();
            this.setupCountdowns();
            this.bindEvents();
        },
        
        bindEvents: function() {
            // 關閉提醒
            $(document).on('click', '.dismiss-expiry-reminder', this.handleDismissReminder);
            
            // 立即使用點數
            $(document).on('click', '.use-expiring-points', this.handleUseExpiringPoints);
        },
        
        checkExpiringPoints: function() {
            // 檢查是否有即將到期的點數
            if (window.wcPointsRewards?.expiringPoints) {
                this.showExpiryReminder(window.wcPointsRewards.expiringPoints);
            }
        },
        
        setupCountdowns: function() {
            $('.expiry-countdown').each(function() {
                var $countdown = $(this);
                var expiryDate = new Date($countdown.data('expiry-date'));
                
                ExpiryReminder.startCountdown($countdown, expiryDate);
            });
        },
        
        startCountdown: function($container, targetDate) {
            var updateCountdown = function() {
                var now = new Date().getTime();
                var distance = targetDate.getTime() - now;
                
                if (distance < 0) {
                    $container.html('<div class="countdown-expired">已到期</div>');
                    return;
                }
                
                var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                
                $container.html(`
                    <div class="countdown-item">
                        <span class="countdown-number">${days}</span>
                        <span class="countdown-label">天</span>
                    </div>
                    <div class="countdown-item">
                        <span class="countdown-number">${hours}</span>
                        <span class="countdown-label">時</span>
                    </div>
                    <div class="countdown-item">
                        <span class="countdown-number">${minutes}</span>
                        <span class="countdown-label">分</span>
                    </div>
                `);
                
                setTimeout(updateCountdown, 60000); // 每分鐘更新
            };
            
            updateCountdown();
        },
        
        showExpiryReminder: function(expiringPoints) {
            var reminderHtml = `
                <div class="points-expiry-alert">
                    <div class="expiry-header">
                        <div class="expiry-icon">⏰</div>
                        <div class="expiry-title">點數即將到期提醒</div>
                    </div>
                    <div class="expiry-details">
                        您有 <strong>${expiringPoints.amount}</strong> 點將在 
                        <strong>${expiringPoints.days}</strong> 天後到期，請盡快使用！
                    </div>
                    <div class="expiry-countdown" data-expiry-date="${expiringPoints.expiry_date}"></div>
                    <div class="expiry-actions">
                        <button class="expiry-action use-expiring-points" data-points="${expiringPoints.amount}">
                            立即使用
                        </button>
                        <button class="expiry-action dismiss-expiry-reminder">
                            稍後提醒
                        </button>
                    </div>
                </div>
            `;
            
            $('.wc-points-redemption-wrapper, .points-summary-cards').first().before(reminderHtml);
            
            // 啟動倒計時
            var $countdown = $('.expiry-countdown');
            if ($countdown.length > 0) {
                this.startCountdown($countdown, new Date(expiringPoints.expiry_date));
            }
        },
        
        handleDismissReminder: function() {
            $(this).closest('.points-expiry-alert').slideUp(300, function() {
                $(this).remove();
            });
            
            // 記錄已提醒，24小時內不再顯示
            Utils.storage.set('expiry_reminder_dismissed', Date.now());
        },
        
        handleUseExpiringPoints: function() {
            var points = $(this).data('points');
            
            if ($('#points-to-use').length > 0) {
                $('#points-to-use').val(points).trigger('input');
                
                // 滾動到點數使用區域
                $('html, body').animate({
                    scrollTop: $('#points-to-use').offset().top - 100
                }, 500);
            }
        }
    };
    
    // 智能點數建議系統
    var PointsSuggestions = {
        
        init: function() {
            this.bindEvents();
            this.setupSuggestionEngine();
        },
        
        bindEvents: function() {
            // 購物車變更時提供建議
            $(document.body).on('updated_cart_totals', this.handleCartUpdate);
            
            // 點數輸入時提供建議
            $(document).on('input', '#points-to-use', this.handlePointsInput);
        },
        
        setupSuggestionEngine: function() {
            this.suggestions = [];
            this.userPreferences = Utils.storage.get('points_preferences') || {};
        },
        
        handleCartUpdate: function() {
            setTimeout(function() {
                PointsSuggestions.generateSuggestions();
            }, 500);
        },
        
        handlePointsInput: function() {
            var points = parseFloat($(this).val()) || 0;
            PointsSuggestions.showInputSuggestions(points);
        },
        
        generateSuggestions: function() {
            var cartTotal = this.getCartTotal();
            var availablePoints = this.getAvailablePoints();
            
            if (cartTotal === 0 || availablePoints === 0) {
                return;
            }
            
            var suggestions = [];
            
            // 建議1: 最佳折扣比例
            var optimalPoints = Math.min(availablePoints, cartTotal * 0.3);
            if (optimalPoints > 0) {
                suggestions.push({
                    points: Math.floor(optimalPoints),
                    reason: '建議使用30%點數，保留餘額供下次使用',
                    type: 'optimal'
                });
            }
            
            // 建議2: 湊整數
            var roundAmount = this.findRoundAmount(cartTotal);
            if (roundAmount > 0 && roundAmount <= availablePoints) {
                suggestions.push({
                    points: roundAmount,
                    reason: '使用點數後金額為整數，方便付款',
                    type: 'round'
                });
            }
            
            // 建議3: 即將到期的點數
            if (window.wcPointsRewards?.expiringPoints) {
                var expiringAmount = window.wcPointsRewards.expiringPoints.amount;
                if (expiringAmount <= cartTotal) {
                    suggestions.push({
                        points: expiringAmount,
                        reason: '優先使用即將到期的點數',
                        type: 'expiring'
                    });
                }
            }
            
            this.displaySuggestions(suggestions);
        },
        
        findRoundAmount: function(total) {
            // 找到使總額變為整數的點數
            var decimal = total - Math.floor(total);
            if (decimal > 0.01) {
                return Math.ceil(decimal);
            }
            return 0;
        },
        
        displaySuggestions: function(suggestions) {
            if (suggestions.length === 0) {
                return;
            }
            
            var $container = $('.points-suggestions');
            if ($container.length === 0) {
                $container = $('<div class="points-suggestions"></div>');
                $('#points-to-use').after($container);
            }
            
            var suggestionsHtml = '<div class="suggestions-title">💡 智能建議:</div>';
            
            suggestions.forEach(function(suggestion, index) {
                suggestionsHtml += `
                    <div class="suggestion-item" data-points="${suggestion.points}">
                        <div class="suggestion-points">${suggestion.points} 點</div>
                        <div class="suggestion-reason">${suggestion.reason}</div>
                        <button class="apply-suggestion" data-points="${suggestion.points}">採用</button>
                    </div>
                `;
            });
            
            $container.html(suggestionsHtml);
            
            // 綁定建議點擊事件
            $container.find('.apply-suggestion').on('click', function() {
                var points = $(this).data('points');
                $('#points-to-use').val(points).trigger('input');
                
                // 記錄使用偏好
                PointsSuggestions.recordPreference($(this).closest('.suggestion-item').index());
            });
        },
        
        showInputSuggestions: function(currentPoints) {
            // 顯示即時建議，如超過可用點數的警告等
            var availablePoints = this.getAvailablePoints();
            var $suggestions = $('.input-suggestions');
            
            if ($suggestions.length === 0) {
                $suggestions = $('<div class="input-suggestions"></div>');
                $('#points-to-use').after($suggestions);
            }
            
            if (currentPoints > availablePoints) {
                $suggestions.html(`
                    <div class="suggestion-warning">
                        ⚠️ 超過可用點數，最多可使用 ${availablePoints} 點
                    </div>
                `).addClass('warning');
            } else if (currentPoints > 0) {
                var discount = currentPoints * (window.wcPointsRewards?.pointValue || 0.01);
                $suggestions.html(`
                    <div class="suggestion-info">
                        💰 可折抵 $${discount.toFixed(2)}
                    </div>
                `).removeClass('warning');
            } else {
                $suggestions.empty();
            }
        },
        
        getCartTotal: function() {
            var total = 0;
            $('.cart-subtotal .amount, .order-total .amount').each(function() {
                var amount = parseFloat($(this).text().replace(/[^\d.]/g, ''));
                if (!isNaN(amount)) {
                    total = Math.max(total, amount);
                }
            });
            return total;
        },
        
        getAvailablePoints: function() {
            var points = $('.available-points').text().match(/\d+/);
            return points ? parseInt(points[0]) : 0;
        },
        
        recordPreference: function(suggestionIndex) {
            var preferences = Utils.storage.get('points_preferences') || {};
            preferences.lastUsedSuggestion = suggestionIndex;
            preferences.usageCount = (preferences.usageCount || 0) + 1;
            Utils.storage.set('points_preferences', preferences);
        }
    };
    
    // 初始化新模組
    PointsAnimation.init();
    PointsCalculator.init();
    ExpiryReminder.init();
    PointsSuggestions.init();
    
    // 全域可用的 API
    window.WCPointsRewards = {
        PointsRedemption: PointsRedemption,
        ProductPoints: ProductPoints,
        TierProgress: TierProgress,
        NotificationSystem: NotificationSystem,
        Utils: Utils
    };
});