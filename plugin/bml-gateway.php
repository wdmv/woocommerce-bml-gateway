<?php
/**
 * Plugin Name: BML Gateway by WDM
 * Plugin URI: https://github.com/wdmv/woocommerce-bml-gateway
 * Description: A third party implementation of BML payment gateway for woocommerce, by WDM
 * Version: 1.0.0
 * Author: WDM
 * Author URI: https://wdm.mv
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bml-gateway
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * WC requires at least: 6.0
 * WC tested up to: 10.4.3
 *
 * @package BML_Gateway
 *
 * DISCLAIMER: This is a third-party plugin and is NOT affiliated with,
 * endorsed by, or sponsored by Bank of Maldives (BML). This is an
 * independent community-developed integration provided as-is, free of charge.
 */

defined('ABSPATH') || exit;

// Define plugin constants.
define('BML_GATEWAY_VERSION', '1.0.0');
define('BML_GATEWAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BML_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BML_GATEWAY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main BML Gateway Class.
 *
 * @class BML_Gateway
 */
class BML_Gateway
{

	/**
	 * The single instance of the class.
	 *
	 * @var BML_Gateway
	 */
	protected static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return BML_Gateway
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct()
	{
		// Declare HPOS compatibility.
		add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));

		// Initialize plugin on plugins_loaded (when WooCommerce is loaded).
		add_action('plugins_loaded', array($this, 'init'));
		add_action('admin_notices', array($this, 'woocommerce_missing_notice'));

		// Webhook and return endpoint handling.
		add_action('init', array($this, 'add_endpoint'));
		add_action('template_redirect', array($this, 'handle_webhook_request'));
		add_filter('query_vars', array($this, 'add_query_var'));
		add_filter('query_vars', array($this, 'add_return_query_var'));
	}

	/**
	 * Add rewrite rules for webhook and return endpoints.
	 */
	public function add_endpoint()
	{
		// Webhook endpoint for BML server-to-server POST notifications.
		add_rewrite_rule(
			'^bml-gateway/webhook$',
			'index.php?bml_webhook=1',
			'top'
		);

		// Return endpoint for customer browser redirects from BML payment page.
		add_rewrite_rule(
			'^bml-gateway/return$',
			'index.php?bml_return=1',
			'top'
		);
	}

	/**
	 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
	 */
	public function declare_hpos_compatibility()
	{
		if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
		}
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	protected function woocommerce_is_active()
	{
		$active_plugins = (array) get_option('active_plugins', array());

		if (is_multisite()) {
			$active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
		}

		return in_array('woocommerce/woocommerce.php', $active_plugins, true) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
	}

	/**
	 * Include required files.
	 */
	protected function includes()
	{
		require_once BML_GATEWAY_PLUGIN_DIR . 'includes/class-bml-gateway-payment-gateway.php';

		// Always load blocks support - the file will check if classes are available.
		require_once BML_GATEWAY_PLUGIN_DIR . 'includes/class-bml-gateway-blocks-integration.php';
	}

	/**
	 * Check if WooCommerce Blocks is active.
	 *
	 * @return bool
	 */
	protected function is_woocommerce_blocks_active()
	{
		// Check if the blocks package is active (common in WooCommerce 8.0+)
		if (class_exists('Automattic\WooCommerce\Blocks\Package')) {
			return true;
		}

		// Check if any block-based features are active via WooCommerce settings
		if (function_exists('wc_current_theme_is_fse_theme') && wc_current_theme_is_fse_theme()) {
			return true;
		}

		// Check for block theme
		if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
			return true;
		}

		return false;
	}

	/**
	 * Initialize plugin.
	 */
	public function init()
	{
		// Check if WooCommerce is active.
		if (!$this->woocommerce_is_active()) {
			return;
		}

		// Include required files after WooCommerce is loaded.
		$this->includes();

		// Note: Since WordPress 4.6, load_plugin_textdomain() is not required
		// for plugins hosted on WordPress.org as translations are loaded automatically.

		// Add the gateway to WooCommerce.
		add_filter('woocommerce_payment_gateways', array($this, 'add_gateway'));
	}

	/**
	 * Add the gateway to WooCommerce.
	 *
	 * @param array $gateways List of payment gateways.
	 * @return array
	 */
	public function add_gateway($gateways)
	{
		$gateways[] = 'BML_Gateway_Payment_Gateway';
		return $gateways;
	}

	/**
	 * WooCommerce missing notice.
	 */
	public function woocommerce_missing_notice()
	{
		if ($this->woocommerce_is_active()) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<?php esc_html_e('Bank of Maldives Payment Gateway requires WooCommerce to be installed and active.', 'bml-gateway'); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Add custom query var for webhook detection.
	 *
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	public function add_query_var($query_vars)
	{
		$query_vars[] = 'bml_webhook';
		return $query_vars;
	}

	/**
	 * Add custom query var for return endpoint detection.
	 *
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	public function add_return_query_var($query_vars)
	{
		$query_vars[] = 'bml_return';
		return $query_vars;
	}

	/**
	 * Handle webhook request (BML server-to-server POST).
	 *
	 * Supports both rewrite rules (pretty permalinks) and query parameters
	 * (plain permalinks) for maximum compatibility.
	 */
	public function handle_webhook_request()
	{
		// Check for webhook via query var (pretty permalinks) or GET param (plain permalinks).
		$is_webhook = get_query_var('bml_webhook') || isset($_GET['bml_webhook']) && '1' === $_GET['bml_webhook'];
		// Check for return via query var (pretty permalinks) or GET param (plain permalinks).
		$is_return = get_query_var('bml_return') || isset($_GET['bml_return']) && '1' === $_GET['bml_return'];

		// Check if this is the webhook endpoint.
		if ($is_webhook) {
			// Ensure WooCommerce is loaded.
			if (!$this->woocommerce_is_active()) {
				status_header(404);
				exit;
			}

			// Load the gateway class and handle the webhook.
			$this->includes();
			$gateway = new BML_Gateway_Payment_Gateway();
			$gateway->webhook_handler();
			exit;
		}

		// Check if this is the return endpoint.
		if ($is_return) {
			// Ensure WooCommerce is loaded.
			if (!$this->woocommerce_is_active()) {
				status_header(404);
				exit;
			}

			// Load the gateway class and handle the customer return.
			$this->includes();
			$gateway = new BML_Gateway_Payment_Gateway();
			$gateway->return_handler();
			exit;
		}
	}
}

// Initialize the plugin.
BML_Gateway::get_instance();

/**
 * Plugin activation hook.
 */
register_activation_hook(__FILE__, function () {
	BML_Gateway::get_instance()->add_endpoint();
	flush_rewrite_rules();
});

/**
 * Plugin deactivation hook.
 */
register_deactivation_hook(__FILE__, function () {
	flush_rewrite_rules();
});
