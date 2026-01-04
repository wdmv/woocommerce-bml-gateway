=== Bank of Maldives Payment Gateway for WooCommerce ===
Contributors: wdmv
Tags: woocommerce, payment gateway, bml, bank of maldives, maldives, checkout, payment
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 6.0
WC tested up to: 9.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bank of Maldives (BML) payment gateway integration for WooCommerce.

== Description ==

Bank of Maldives Payment Gateway is a WooCommerce extension that allows merchants to accept payments through Bank of Maldives' secure payment gateway.

= Features =

* Secure Payments - Built with security in mind, handling sensitive payment data safely
* HPOS Compatible - Fully compatible with WooCommerce High-Performance Order Storage
* Checkout Blocks Support - Works with WooCommerce Checkout Blocks (block-based checkout)
* Test Mode - Built-in test mode for development and testing
* Webhook Handling - Reliable server-to-server webhook processing
* Auto-Capture - Supports automatic payment capture functionality

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 6.0 or higher
* PHP 7.4 or higher
* A valid BML merchant account (contact Bank of Maldives directly to apply)

== Installation ==

= Via WordPress Admin =

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "Bank of Maldives Payment Gateway"
3. Click **Install Now**
4. Activate the plugin

= Via Upload =

1. Download the plugin ZIP file
2. Go to **Plugins > Add New** in your WordPress admin
3. Click **Upload Plugin**
4. Select the ZIP file and click **Install Now**
5. Activate the plugin

= Configuration =

1. Navigate to **WooCommerce > Settings**
2. Click on the **Payments** tab
3. Find **Bank of Maldives** in the payment gateways list
4. Click the **Manage** button
5. Toggle **Enable** to **ON**
6. Enter your BML merchant credentials:
   - Merchant ID
   - API Key
   - API Secret
7. Configure additional settings:
   - Test Mode - Enable for testing with BML sandbox
   - Auto-Capture - Automatically capture payments after authorization
8. Click **Save changes**

== Changelog ==

= 1.0.0 =
* Initial release
* BML payment gateway integration
* Webhook handling for payment notifications
* HPOS compatibility
* Checkout blocks support
* Test mode functionality

== Upgrade Notice ==

= 1.0.0 =
Initial release of Bank of Maldives Payment Gateway for WooCommerce.
