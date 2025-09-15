jQuery(document).ready(function($) {
    'use strict';
    
    // Handle simple product quick checkout
    $(document).on('click', '.quick-checkout-btn[data-product-type="simple"]', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var productId = button.data('product-id');
        var quantity = 1;
        
        // Try to get quantity from nearby quantity input
        var quantityInput = button.closest('.product, .summary').find('input[name="quantity"], .qty');
        if (quantityInput.length && quantityInput.val()) {
            quantity = parseInt(quantityInput.val());
        }
        
        // Show loading state
        button.prop('disabled', true);
        button.find('.btn-text').hide();
        button.find('.btn-loading').show();
        
        // AJAX request
        $.post(quickCheckoutAjax.ajax_url, {
            action: 'quick_checkout',
            product_id: productId,
            quantity: quantity,
            nonce: quickCheckoutAjax.nonce
        }, function(response) {
            if (response.success) {
                // Redirect to checkout
                window.location.href = response.data.checkout_url;
            } else {
                alert('Error: ' + (response.data.message || quickCheckoutAjax.i18n.error_generic));
                resetButton(button);
            }
        }).fail(function() {
            alert(quickCheckoutAjax.i18n.error_network);
            resetButton(button);
        });
    });
    
    // Handle variable product quick checkout
    $(document).on('click', '.quick-checkout-btn[data-product-type="variable"]:not([disabled])', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var productId = button.data('product-id');
        var variationsForm = button.closest('.product').find('.variations_form');
        
        if (variationsForm.length === 0) {
            alert(quickCheckoutAjax.i18n.error_select_options);
            return;
        }
        
        var variationId = variationsForm.find('input[name="variation_id"]').val();
        var quantity = variationsForm.find('input[name="quantity"]').val() || 1;
        var variationData = {};
        
        // Collect variation attributes
        variationsForm.find('.variations select').each(function() {
            var name = $(this).attr('name');
            var value = $(this).val();
            if (name && value) {
                variationData[name] = value;
            }
        });
        
        if (!variationId || variationId == '0') {
            alert(quickCheckoutAjax.i18n.error_select_all_options);
            return;
        }
        
        // Show loading state
        button.prop('disabled', true);
        button.find('.btn-text, .btn-text-ready').hide();
        button.find('.btn-loading').show();
        
        // AJAX request
        $.post(quickCheckoutAjax.ajax_url, {
            action: 'quick_checkout_variable',
            product_id: productId,
            variation_id: variationId,
            quantity: quantity,
            variation: variationData,
            nonce: quickCheckoutAjax.nonce
        }, function(response) {
            if (response.success) {
                // Redirect to checkout
                window.location.href = response.data.checkout_url;
            } else {
                alert('Error: ' + (response.data.message || quickCheckoutAjax.i18n.error_generic));
                resetButton(button, true);
            }
        }).fail(function() {
            alert(quickCheckoutAjax.i18n.error_network);
            resetButton(button, true);
        });
    });
    
    // Listen for variation changes to enable/disable button
    $(document).on('show_variation', '.variations_form', function(event, variation) {
        var checkoutBtn = $(this).closest('.product').find('.quick-checkout-btn[data-product-type="variable"]');
        if (checkoutBtn.length) {
            checkoutBtn.prop('disabled', false);
            checkoutBtn.find('.btn-text').hide();
            checkoutBtn.find('.btn-text-ready').show();
        }
    });
    
    $(document).on('hide_variation', '.variations_form', function() {
        var checkoutBtn = $(this).closest('.product').find('.quick-checkout-btn[data-product-type="variable"]');
        if (checkoutBtn.length) {
            checkoutBtn.prop('disabled', true);
            checkoutBtn.find('.btn-text').show();
            checkoutBtn.find('.btn-text-ready').hide();
        }
    });
    
    // Helper function to reset button state
    function resetButton(button, isVariable) {
        button.prop('disabled', isVariable ? true : false);
        button.find('.btn-loading').hide();
        if (isVariable) {
            button.find('.btn-text').show();
            button.find('.btn-text-ready').hide();
        } else {
            button.find('.btn-text').show();
        }
    }
});