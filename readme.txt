=== Quick Checkout Button ===
Contributors: hannan
Tags: direct checkout, checkout, quick checkout, ecommerce
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a "Click to Checkout" button to shop page products and single product pages that redirects directly to checkout.

== Description ==

Quick Checkout Button is a simple yet powerful WooCommerce extension that adds a prominent "Click to Checkout" button to your product pages. This plugin streamlines the purchasing process by allowing customers to bypass the traditional cart page and go directly to checkout with a single click.

**Features:**

* Add quick checkout buttons to shop/archive pages
* Add quick checkout buttons to single product pages  
* Support for simple, variable, and grouped products
* Responsive design that works on all devices
* AJAX-powered for smooth user experience
* Translation ready
* No configuration needed - works out of the box
* Compatible with most WooCommerce themes

**How it works:**

1. Customer clicks the "Click to Checkout" button
2. Product is automatically added to cart
3. Customer is redirected to checkout page
4. Purchase process is completed faster

Perfect for stores wanting to reduce cart abandonment and simplify the buying process.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/quick-checkout-button` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Make sure WooCommerce is installed and activated
4. The quick checkout buttons will appear automatically on your shop and product pages

== Frequently Asked Questions ==

= Does this plugin work with variable products? =

Yes! For variable products, customers will need to select their options first, then the quick checkout button becomes active.

= Will this empty my cart? =

By default, yes. The plugin clears the cart before adding the selected product to ensure a clean checkout experience. This can be modified in the code if needed.

= Is it compatible with my theme? =

The plugin is designed to work with most WooCommerce-compatible themes. If you experience styling issues, you may need to add custom CSS.

= Can I customize the button appearance? =

Yes, you can add custom CSS to override the default button styling. The button uses the class `.quick-checkout-btn`.

== Screenshots ==

1. Quick checkout button on shop page
2. Quick checkout button on single product page
3. Button in action with loading state

== Changelog ==

= 1.0.0 =
* Added support for variable products
* Added support for grouped products
* Improved AJAX handling
* Better error messages
* Enhanced loading states

== Upgrade Notice ==

= 1.0.0 =
Initial release of Quick Checkout Button. Adds a "Click to Checkout" button to shop page products and single product pages that redirects directly to checkout.