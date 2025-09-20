<?php
/**
 * Plugin Name: Quick Checkout Button
 * Description: Adds a "Click to Checkout" button to shop page products and single product pages that redirects directly to checkout.
 * Version: 1.0.0
 * Author: Techplnt
 * Author URI: https://github.com/techplnt04
 * Plugin URI: https://github.com/techplnt04/quick-checkout-button
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: techplnt-quick-checkout-button
 * Requires at least: 5.0
 * Requires PHP: 7.3
 * Requires Plugins: woocommerce
 * Tested up to: 6.8
 * WC tested up to: 10.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TECHPLNT_QCB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TECHPLNT_QCB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TECHPLNT_QCB_VERSION', '1.0.0');

class TechplntQuickCheckoutButton {
    
    public function __construct() {
        // Hook into WordPress
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        /**
         * Declare HPOS compatibility.
         *
         * @since 1.0.0
         */
        add_action('before_woocommerce_init', function () {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                    'custom_order_tables',
                    __FILE__,
                    true
                );
            }
        }, 10);
        
        // Load text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Add the checkout button to shop page products
        add_action('woocommerce_after_shop_loop_item', array($this, 'techplnt_add_quick_checkout_button_shop'), 15);
        
        // Add the checkout button to single product pages
        add_action('woocommerce_single_product_summary', array($this, 'techplnt_add_quick_checkout_button_single'), 35);
        
        // Add button after add to cart on single product page (alternative position)
        add_action('woocommerce_after_add_to_cart_button', array($this, 'techplnt_add_quick_checkout_button_after_cart'), 10);
        
        // Handle AJAX request for adding to cart and redirecting
        add_action('wp_ajax_quick_checkout', array($this, 'techplnt_handle_quick_checkout_single'));
        add_action('wp_ajax_nopriv_quick_checkout', array($this, 'techplnt_handle_quick_checkout_single'));
        
        // Handle variable product quick checkout
        add_action('wp_ajax_quick_checkout_variable', array($this, 'techplnt_handle_quick_checkout_variable'));
        add_action('wp_ajax_nopriv_quick_checkout_variable', array($this, 'techplnt_handle_quick_checkout_variable'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'techplnt-quick-checkout-button',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Display notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>' . 
             esc_html__('Quick Checkout Button plugin requires WooCommerce to be installed and active.', 'techplnt-quick-checkout-button') . 
             '</p></div>';
    }
    
    /**
     * Add quick checkout button to shop page products
     */
    public function techplnt_add_quick_checkout_button_shop() {
        global $product;
        
        // Only show on shop page and product archives
        if (!is_shop() && !is_product_category() && !is_product_tag()) {
            return;
        }
        
        $this->techplnt_render_quick_checkout_button($product, 'shop');
    }
    
    /**
     * Add quick checkout button to single product page
     */
    public function techplnt_add_quick_checkout_button_single() {
        global $product;
        
        // Only show on single product pages
        if (!is_product()) {
            return;
        }
        
        $this->techplnt_render_quick_checkout_button($product, 'single');
    }
    
    /**
     * Add quick checkout button after add to cart button on single product page
     */
    public function techplnt_add_quick_checkout_button_after_cart() {       
        global $product;
        
        // Only show on single product pages
        if (!is_product()) {
            return;
        }
        
        // Don't show if already shown above
        // You can comment this out if you want buttons in both positions
        return;
        
        $this->techplnt_render_quick_checkout_button($product, 'after-cart');
    }
    
    /**
     * Render the quick checkout button based on product type and context
     */
    private function techplnt_render_quick_checkout_button($product, $context = 'shop') {
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
        $button_text = esc_html__('Click to Checkout', 'techplnt-quick-checkout-button');
        
        // Handle different product types
        if ($product->is_type('simple')) {
            $this->techplnt_render_simple_checkout_button($product_id, $button_class, $button_text);
        } elseif ($product->is_type('variable')) {
            $this->techplnt_render_variable_checkout_button($product, $button_class, $button_text, $context);
        } elseif ($product->is_type('grouped')) {
            // For grouped products, link to single product page for selection
            $this->techplnt_render_grouped_checkout_button($product, $button_class);
        } else {
            // Default behavior for other product types
            $this->techplnt_render_simple_checkout_button($product_id, $button_class, $button_text);
        }
    }
    
    /**
     * Render simple product checkout button
     */
    private function techplnt_render_simple_checkout_button($product_id, $button_class, $button_text) {
        echo '<button class="' . esc_attr($button_class) . '" data-product-id="' . esc_attr($product_id) . '" data-product-type="simple">
                <span class="btn-text">' . esc_html($button_text) . '</span>
                <span class="btn-loading" style="display:none;">
                    <i class="loading-icon">⏳</i> ' . esc_html__('Adding to cart...', 'techplnt-quick-checkout-button') . '
                </span>
              </button>';
    }
    
    /**
     * Render variable product checkout button
     */
    private function techplnt_render_variable_checkout_button($product, $button_class, $button_text, $context) {
        $product_id = $product->get_id();
        
        if ($context === 'shop') {
            // On shop page, link to single product page for variation selection
            echo '<a href="' . esc_url(get_permalink($product_id)) . '" class="' . esc_attr($button_class) . ' variable-link">
                    ' . esc_html__('Select Options & Checkout', 'techplnt-quick-checkout-button') . '
                  </a>';
        } else {
            // On single product page, show button that works with selected variations
            echo '<button class="' . esc_attr($button_class) . '" 
                         data-product-id="' . esc_attr($product_id) . '" 
                         data-product-type="variable"
                         disabled>
                    <span class="btn-text">' . esc_html__('Select Options to Checkout', 'techplnt-quick-checkout-button') . '</span>
                    <span class="btn-text-ready" style="display:none;">' . esc_html($button_text) . '</span>
                    <span class="btn-loading" style="display:none;">
                        <i class="loading-icon">⏳</i> ' . esc_html__('Adding to cart...', 'techplnt-quick-checkout-button') . '
                    </span>
                  </button>';
        }
    }
    
    /**
     * Render grouped product checkout button
     */
    private function techplnt_render_grouped_checkout_button($product, $button_class) {
        $product_id = $product->get_id();
        
        echo '<a href="' . esc_url(get_permalink($product_id)) . '" class="' . esc_attr($button_class) . ' grouped-link">
                ' . esc_html__('Select Products & Checkout', 'techplnt-quick-checkout-button') . '
              </a>';
    }
    
    /**
     * Handle AJAX request for quick checkout (simple products)
     */
    public function techplnt_handle_quick_checkout_single() {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'quick_checkout_nonce')) {
            wp_die(esc_html__('Security check failed', 'techplnt-quick-checkout-button'));
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        
        if (!$product_id) {
            wp_send_json_error(array(
                'message' => esc_html__('Invalid product ID', 'techplnt-quick-checkout-button')
            ));
        }
        
        // Validate product
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_in_stock()) {
            wp_send_json_error(array(
                'message' => esc_html__('Product is not available', 'techplnt-quick-checkout-button')
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
                'message' => esc_html__('Product added to cart successfully!', 'techplnt-quick-checkout-button')
            ));
        } else {
            // Error adding to cart
            wp_send_json_error(array(
                'message' => esc_html__('Could not add product to cart', 'techplnt-quick-checkout-button')
            ));
        }
    }
    
    /**
     * Handle AJAX request for variable product quick checkout
     */
    public function techplnt_handle_quick_checkout_variable() {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'quick_checkout_nonce')) {
            wp_die(esc_html__('Security check failed', 'techplnt-quick-checkout-button'));
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        $variation_data = isset($_POST['variation']) ? map_deep(wp_unslash($_POST['variation']), 'sanitize_text_field') : array();
        
        if (!$product_id || !$variation_id) {
            wp_send_json_error(array(
                'message' => esc_html__('Invalid product or variation ID', 'techplnt-quick-checkout-button')
            ));
        }
        
        // Validate product and variation
        $product = wc_get_product($product_id);
        $variation = wc_get_product($variation_id);
        
        if (!$product || !$variation || !$variation->is_in_stock()) {
            wp_send_json_error(array(
                'message' => esc_html__('Selected variation is not available', 'techplnt-quick-checkout-button')
            ));
        }
        
        // Clear cart first (optional)
        WC()->cart->empty_cart();
        
        // Add variation to cart
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation_data);
        
        if ($cart_item_key) {
            wp_send_json_success(array(
                'checkout_url' => wc_get_checkout_url(),
                'message' => esc_html__('Product variation added to cart successfully!', 'techplnt-quick-checkout-button')
            ));
        } else {
            wp_send_json_error(array(
                'message' => esc_html__('Could not add product variation to cart', 'techplnt-quick-checkout-button')
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
        
        // Enqueue CSS
        wp_enqueue_style(
            'quick-checkout-styles',
            TECHPLNT_QCB_PLUGIN_URL . 'assets/css/quick-checkout-styles.css',
            array(),
            TECHPLNT_QCB_VERSION,
            'all'
        );
        
        // Enqueue JS
        wp_enqueue_script(
            'quick-checkout-scripts',
            TECHPLNT_QCB_PLUGIN_URL . 'assets/js/quick-checkout-scripts.js',
            array('jquery'),
            TECHPLNT_QCB_VERSION,
            true
        );
        
        // Localize script for AJAX and translations
        wp_localize_script('quick-checkout-scripts', 'quickCheckoutAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('quick_checkout_nonce'),
            'i18n' => array(
                'error_generic' => esc_html__('Something went wrong', 'techplnt-quick-checkout-button'),
                'error_network' => esc_html__('Network error occurred', 'techplnt-quick-checkout-button'),
                'error_select_options' => esc_html__('Please select product options first', 'techplnt-quick-checkout-button'),
                'error_select_all_options' => esc_html__('Please select all product options first', 'techplnt-quick-checkout-button'),
            )
        ));
    }
}

// Initialize the plugin
new TechplntQuickCheckoutButton();
?>