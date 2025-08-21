/**
 * WooCommerce Points & Rewards Frontend JavaScript
 * 
 * @package WC_Points_Rewards
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Points Redemption functionality
    var PointsRedemption = {
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Apply points discount
            $(document).on('click', '.wc-points-apply-discount', this.handleApplyDiscount);
            
            // Remove points discount  
            $(document).on('click', '.wc-points-remove-discount', this.handleRemoveDiscount);
            
            // Points input validation
            $(document).on('input', '#points-to-use', this.validatePointsInput);
            
            // Quick use buttons
            $(document).on('click', '.points-quick-use', this.handleQuickUse);
            
            // Update cart when cart totals change
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
            
            // Validate input
            if (!points || points <= 0) {
                PointsRedemption.showMessage(wcPointsRewards.messages.invalid_amount, 'error');
                return;
            }
            
            // Set loading state
            $button.prop('disabled', true).text(wcPointsRewards.messages.loading);
            
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
                        $('body').trigger('update_checkout');
                        // Reload to update the cart display
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
                        $('body').trigger('update_checkout');
                        // Reload to update the cart display
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
                    $button.prop('disabled', false).text('取消使用');
                }
            });
        },
        
        validatePointsInput: function() {
            var $input = $(this);
            var points = parseFloat($input.val());
            var max = parseFloat($input.attr('max'));
            var $preview = $('.discount-preview');
            
            if (points > 0 && points <= max) {
                // Calculate and show discount preview
                var pointValue = 0.01; // Default point value, could be made dynamic
                var discountAmount = points * pointValue;
                
                if ($preview.length === 0) {
                    $preview = $('<div class="discount-preview"></div>').insertAfter('#points-to-use');
                }
                
                $preview.html('折抵金額：<strong>$' + discountAmount.toFixed(2) + '</strong>');
            } else {
                $('.discount-preview').remove();
            }
        },
        
        handleCartUpdate: function() {
            // Re-bind events after cart update
            setTimeout(function() {
                PointsRedemption.bindEvents();
            }, 100);
        },
        
        showMessage: function(message, type) {
            var $messages = $('.points-messages');
            
            if ($messages.length === 0) {
                $messages = $('<div class="points-messages"></div>').insertAfter('.wc-points-redemption-wrapper');
            }
            
            $messages.removeClass('wc-points-success wc-points-error')
                    .addClass('wc-points-' + type)
                    .html(message)
                    .show();
            
            setTimeout(function() {
                $messages.fadeOut();
            }, 5000);
        }
    };
    
    // Initialize
    PointsRedemption.init();
    
    // Make it globally available
    window.WCPointsRewards = window.WCPointsRewards || {};
    window.WCPointsRewards.PointsRedemption = PointsRedemption;
});