/**
 * WooCommerce Points & Rewards - Frontend JavaScript
 * 
 * @package WC_Points_Rewards
 */

(function($) {
    'use strict';

    /**
     * Points Redemption Handler
     */
    var PointsRedemption = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Apply discount button
            $(document).on('click', '.wc-points-apply-discount', this.handleApplyDiscount);
            
            // Remove discount button
            $(document).on('click', '.wc-points-remove-discount', this.handleRemoveDiscount);
            
            // Points input validation
            $(document).on('input', '#points-to-use', this.handlePointsInput);
            
            // Cart/checkout updates
            $(document.body).on('updated_cart_totals updated_checkout', this.handleCartUpdate);
        },
        
        handleApplyDiscount: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $input = $('#points-to-use');
            var points = parseFloat($input.val());
            var nonce = $button.data('nonce');
            
            // Validation
            if (!points || points <= 0) {
                PointsRedemption.showMessage(wcPointsRewards.messages.invalid_amount, 'error');
                return;
            }
            
            // Set loading state
            $button.prop('disabled', true).text(wcPointsRewards.messages.loading);
            
            // AJAX call
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
                        // Trigger cart update
                        $('body').trigger('update_checkout');
                        // Reload page to show updated display
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
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
            
            $button.prop('disabled', true).text('處理中...');
            
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
                        $('body').trigger('update_checkout');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
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
            
            // Real-time validation
            if (points > maxPoints) {
                $input.val(maxPoints);
                PointsRedemption.showMessage('超過可使用的最大點數', 'warning');
            }
            
            // Update preview if exists
            PointsRedemption.updateDiscountPreview(points);
        },
        
        updateDiscountPreview: function(points) {
            if (points > 0) {
                var discountAmount = points; // Assuming 1 point = 1 currency unit
                var $preview = $('.discount-preview');
                
                if ($preview.length) {
                    $preview.text('約可折抵：' + wcPointsRewards.formatPrice(discountAmount));
                    $preview.show();
                }
            } else {
                $('.discount-preview').hide();
            }
        },
        
        handleCartUpdate: function() {
            // Refresh points section after cart updates
            var $pointsSection = $('.points-redemption-section');
            if ($pointsSection.length) {
                // Re-initialize any dynamic content
                PointsRedemption.refreshPointsDisplay();
            }
        },
        
        refreshPointsDisplay: function() {
            // Update available points and limits based on current cart state
            // This would typically make an AJAX call to get updated values
        },
        
        showMessage: function(message, type) {
            var $messages = $('.points-messages');
            
            if (!$messages.length) {
                $messages = $('<div class="points-messages"></div>');
                $('.wc-points-redemption-wrapper').append($messages);
            }
            
            $messages.removeClass('wc-points-success wc-points-error wc-points-warning')
                    .addClass('wc-points-' + type)
                    .html(message)
                    .show();
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $messages.fadeOut();
            }, 5000);
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if (typeof wcPointsRewards !== 'undefined') {
            PointsRedemption.init();
        }
    });

})(jQuery);