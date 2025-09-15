<?php
/**
 * Plugin Name: Quick Checkout Button
 * Description: Adds a "Click to Checkout" button to shop page products and single product pages that redirects directly to checkout
 * Version: 1.1.0
 * Author: Hannan
 * Author URI: https://github.com/coderembassy
 * Plugin URI: https://coderembassy.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       quick-checkout-button
 * Requires at least: 5.0
 * Requires PHP:      7.3
 * Requires Plugins:  woocommerce
 * Tested up to:      6.8
 * WC tested up to:	  10.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class QuickCheckoutButton {
    
    public function __init() {
        // Hook into WordPress
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Add the checkout button to shop page products
        add_action('woocommerce_after_shop_loop_item', array($this, 'add_quick_checkout_button_shop'), 15);
        
        // Add the checkout button to single product pages
        add_action('woocommerce_single_product_summary', array($this, 'add_quick_checkout_button_single'), 35);
        
        // Add button after add to cart on single product page (alternative position)
        add_action('woocommerce_after_add_to_cart_button', array($this, 'add_quick_checkout_button_after_cart'), 10);
        
        // Handle AJAX request for adding to cart and redirecting
        add_action('wp_ajax_quick_checkout', array($this, 'handle_quick_checkout'));
        add_action('wp_ajax_nopriv_quick_checkout', array($this, 'handle_quick_checkout'));
        
        // Handle variable product quick checkout
        add_action('wp_ajax_quick_checkout_variable', array($this, 'handle_quick_checkout_variable'));
        add_action('wp_ajax_nopriv_quick_checkout_variable', array($this, 'handle_quick_checkout_variable'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Display notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>Quick Checkout Button plugin requires WooCommerce to be installed and active.</p></div>';
    }
    
    /**
     * Add quick checkout button to shop page products
     */
    public function add_quick_checkout_button_shop() {
        global $product;
        
        // Only show on shop page and product archives
        if (!is_shop() && !is_product_category() && !is_product_tag()) {
            return;
        }
        
        $this->render_quick_checkout_button($product, 'shop');
    }
    
    /**
     * Add quick checkout button to single product page
     */
    public function add_quick_checkout_button_single() {
        global $product;
        
        // Only show on single product pages
        if (!is_product()) {
            return;
        }
        
        $this->render_quick_checkout_button($product, 'single');
    }
    
    /**
     * Add quick checkout button after add to cart button on single product page
     */
    public function add_quick_checkout_button_after_cart() {
        global $product;
        
        // Only show on single product pages
        if (!is_product()) {
            return;
        }
        
        // Don't show if already shown above
        // You can comment this out if you want buttons in both positions
        return;
        
        $this->render_quick_checkout_button($product, 'after-cart');
    }
    
    /**
     * Render the quick checkout button based on product type and context
     */
    private function render_quick_checkout_button($product, $context = 'shop') {
        // Don't show for out of stock products
        if (!$product->is_in_stock()) {
            return;
        }
        
        // Don't show for external products
        if ($product->is_type('external')) {
            return;
        }
        
        $product_id = $product->get_id();
        $button_class = 'quick-checkout-btn quick-checkout-' . $context;
        $button_text = 'Click to Checkout';
        
        // Handle different product types
        if ($product->is_type('simple')) {
            $this->render_simple_checkout_button($product_id, $button_class, $button_text);
        } elseif ($product->is_type('variable')) {
            $this->render_variable_checkout_button($product, $button_class, $button_text, $context);
        } elseif ($product->is_type('grouped')) {
            // For grouped products, link to single product page for selection
            $this->render_grouped_checkout_button($product, $button_class);
        } else {
            // Default behavior for other product types
            $this->render_simple_checkout_button($product_id, $button_class, $button_text);
        }
    }
    
    /**
     * Render simple product checkout button
     */
    private function render_simple_checkout_button($product_id, $button_class, $button_text) {
        echo '<button class="' . esc_attr($button_class) . '" data-product-id="' . esc_attr($product_id) . '" data-product-type="simple">
                <span class="btn-text">' . esc_html($button_text) . '</span>
                <span class="btn-loading" style="display:none;">
                    <i class="loading-icon">⏳</i> Adding to cart...
                </span>
              </button>';
    }
    
    /**
     * Render variable product checkout button
     */
    private function render_variable_checkout_button($product, $button_class, $button_text, $context) {
        $product_id = $product->get_id();
        
        if ($context === 'shop') {
            // On shop page, link to single product page for variation selection
            echo '<a href="' . esc_url(get_permalink($product_id)) . '" class="' . esc_attr($button_class) . ' variable-link">
                    Select Options & Checkout
                  </a>';
        } else {
            // On single product page, show button that works with selected variations
            echo '<button class="' . esc_attr($button_class) . '" 
                         data-product-id="' . esc_attr($product_id) . '" 
                         data-product-type="variable"
                         disabled>
                    <span class="btn-text">Select Options to Checkout</span>
                    <span class="btn-text-ready" style="display:none;">' . esc_html($button_text) . '</span>
                    <span class="btn-loading" style="display:none;">
                        <i class="loading-icon">⏳</i> Adding to cart...
                    </span>
                  </button>';
        }
    }
    
    /**
     * Render grouped product checkout button
     */
    private function render_grouped_checkout_button($product, $button_class) {
        $product_id = $product->get_id();
        
        echo '<a href="' . esc_url(get_permalink($product_id)) . '" class="' . esc_attr($button_class) . ' grouped-link">
                Select Products & Checkout
              </a>';
    }
    
    /**
     * Handle AJAX request for quick checkout (simple products)
     */
    public function handle_quick_checkout() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'quick_checkout_nonce')) {
            wp_die('Security check failed');
        }
        
        $product_id = intval($_POST['product_id']);
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        
        // Validate product
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_in_stock()) {
            wp_send_json_error(array(
                'message' => 'Product is not available'
            ));
        }
        
        // Clear cart first (optional - remove this line if you want to add to existing cart)
        WC()->cart->empty_cart();
        
        // Add product to cart
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);
        
        if ($cart_item_key) {
            // Success - return checkout URL
            wp_send_json_success(array(
                'checkout_url' => wc_get_checkout_url(),
                'message' => 'Product added to cart successfully!'
            ));
        } else {
            // Error adding to cart
            wp_send_json_error(array(
                'message' => 'Could not add product to cart'
            ));
        }
    }
    
    /**
     * Handle AJAX request for variable product quick checkout
     */
    public function handle_quick_checkout_variable() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'quick_checkout_nonce')) {
            wp_die('Security check failed');
        }
        
        $product_id = intval($_POST['product_id']);
        $variation_id = intval($_POST['variation_id']);
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        $variation_data = isset($_POST['variation']) ? $_POST['variation'] : array();
        
        // Validate product and variation
        $product = wc_get_product($product_id);
        $variation = wc_get_product($variation_id);
        
        if (!$product || !$variation || !$variation->is_in_stock()) {
            wp_send_json_error(array(
                'message' => 'Selected variation is not available'
            ));
        }
        
        // Clear cart first (optional)
        WC()->cart->empty_cart();
        
        // Add variation to cart
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation_data);
        
        if ($cart_item_key) {
            wp_send_json_success(array(
                'checkout_url' => wc_get_checkout_url(),
                'message' => 'Product variation added to cart successfully!'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Could not add product variation to cart'
            ));
        }
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Load on shop pages and single product pages
        if (!is_shop() && !is_product_category() && !is_product_tag() && !is_product()) {
            return;
        }
        
        // Enqueue jQuery (usually already loaded)
        wp_enqueue_script('jquery');
        
        // Add inline script
        wp_add_inline_script('jquery', $this->get_inline_script());
        
        // Add inline CSS
        wp_add_inline_style('wp-block-library', $this->get_inline_css());
    }
    
    /**
     * Get inline JavaScript
     */
    private function get_inline_script() {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('quick_checkout_nonce');
        
        return "
        jQuery(document).ready(function($) {
            // Handle simple product quick checkout
            $(document).on('click', '.quick-checkout-btn[data-product-type=\"simple\"]', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var productId = button.data('product-id');
                var quantity = 1;
                
                // Try to get quantity from nearby quantity input
                var quantityInput = button.closest('.product, .summary').find('input[name=\"quantity\"], .qty');
                if (quantityInput.length && quantityInput.val()) {
                    quantity = parseInt(quantityInput.val());
                }
                
                // Show loading state
                button.prop('disabled', true);
                button.find('.btn-text').hide();
                button.find('.btn-loading').show();
                
                // AJAX request
                $.post('{$ajax_url}', {
                    action: 'quick_checkout',
                    product_id: productId,
                    quantity: quantity,
                    nonce: '{$nonce}'
                }, function(response) {
                    if (response.success) {
                        // Redirect to checkout
                        window.location.href = response.data.checkout_url;
                    } else {
                        alert('Error: ' + (response.data.message || 'Something went wrong'));
                        resetButton(button);
                    }
                }).fail(function() {
                    alert('Network error occurred');
                    resetButton(button);
                });
            });
            
            // Handle variable product quick checkout
            $(document).on('click', '.quick-checkout-btn[data-product-type=\"variable\"]:not([disabled])', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var productId = button.data('product-id');
                var variationsForm = button.closest('.product').find('.variations_form');
                
                if (variationsForm.length === 0) {
                    alert('Please select product options first');
                    return;
                }
                
                var variationId = variationsForm.find('input[name=\"variation_id\"]').val();
                var quantity = variationsForm.find('input[name=\"quantity\"]').val() || 1;
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
                    alert('Please select all product options first');
                    return;
                }
                
                // Show loading state
                button.prop('disabled', true);
                button.find('.btn-text, .btn-text-ready').hide();
                button.find('.btn-loading').show();
                
                // AJAX request
                $.post('{$ajax_url}', {
                    action: 'quick_checkout_variable',
                    product_id: productId,
                    variation_id: variationId,
                    quantity: quantity,
                    variation: variationData,
                    nonce: '{$nonce}'
                }, function(response) {
                    if (response.success) {
                        // Redirect to checkout
                        window.location.href = response.data.checkout_url;
                    } else {
                        alert('Error: ' + (response.data.message || 'Something went wrong'));
                        resetButton(button, true);
                    }
                }).fail(function() {
                    alert('Network error occurred');
                    resetButton(button, true);
                });
            });
            
            // Listen for variation changes to enable/disable button
            $(document).on('show_variation', '.variations_form', function(event, variation) {
                var checkoutBtn = $(this).closest('.product').find('.quick-checkout-btn[data-product-type=\"variable\"]');
                if (checkoutBtn.length) {
                    checkoutBtn.prop('disabled', false);
                    checkoutBtn.find('.btn-text').hide();
                    checkoutBtn.find('.btn-text-ready').show();
                }
            });
            
            $(document).on('hide_variation', '.variations_form', function() {
                var checkoutBtn = $(this).closest('.product').find('.quick-checkout-btn[data-product-type=\"variable\"]');
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
        ";
    }
    
    /**
     * Get inline CSS
     */
    private function get_inline_css() {
        return "
        .quick-checkout-btn {
            background-color: #ff6b35;
            color: white !important;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            margin-top: 15px;
            width: 100%;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            line-height: 1.4;
        }
        
        .quick-checkout-btn:hover {
            background-color: #e55a2b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
            color: white !important;
        }
        
        .quick-checkout-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .quick-checkout-btn.variable-link,
        .quick-checkout-btn.grouped-link {
            background-color: #007cba;
        }
        
        .quick-checkout-btn.variable-link:hover,
        .quick-checkout-btn.grouped-link:hover {
            background-color: #005a87;
        }
        
        .quick-checkout-btn .btn-loading {
            font-style: italic;
        }
        
        .quick-checkout-btn .loading-icon {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Single product page specific styles */
        .quick-checkout-single {
            margin-top: 20px;
            font-size: 16px;
            padding: 15px 30px;
            background: linear-gradient(45deg, #ff6b35, #ff8c42);
        }
        
        .quick-checkout-single:hover {
            background: linear-gradient(45deg, #e55a2b, #e67635);
        }
        
        /* After cart button styles */
        .quick-checkout-after-cart {
            margin-top: 10px;
            margin-left: 10px;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .quick-checkout-btn {
                font-size: 14px;
                padding: 12px 20px;
            }
            
            .quick-checkout-single {
                font-size: 15px;
                padding: 14px 25px;
            }
        }
        
        /* Integration with WooCommerce styles */
        .woocommerce .quick-checkout-btn,
        .woocommerce-page .quick-checkout-btn {
            margin-top: 15px;
        }
        
        .single-product .quick-checkout-btn {
            max-width: 300px;
            margin-left: auto;
            margin-right: auto;
        }
        ";
    }
}

// Initialize the plugin
$quick_checkout_button = new QuickCheckoutButton();
$quick_checkout_button->__init();

?>