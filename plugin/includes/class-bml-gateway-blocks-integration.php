<?php
/**
 * BML Gateway Blocks Integration
 *
 * @package BML_Gateway
 *
 * @copyright 2025 WDM
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPLv2 or later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined( 'ABSPATH' ) || exit;

// Only proceed if WooCommerce Blocks is available.
if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
	return;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * BML Gateway Blocks Integration.
 */
class BML_Gateway_Blocks_Integration extends AbstractPaymentMethodType {

	/**
	 * Payment method name/id.
	 *
	 * @var string
	 */
	protected $name = 'bml_gateway';

	/**
	 * Settings.
	 *
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Initialize the payment gateway.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_bml_gateway_settings', array() );
	}

	/**
	 * Returns if this payment method should be active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return isset( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Get the payment method script handles for the blocks context.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_url = plugins_url( '/assets/js/bml-gateway-blocks.js', BML_GATEWAY_PLUGIN_BASENAME );
		$version    = defined( 'BML_GATEWAY_VERSION' ) ? BML_GATEWAY_VERSION : '1.0.0';

		wp_register_script(
			'bml-gateway-blocks',
			$script_url,
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n' ),
			$version,
			true
		);

		wp_localize_script(
			'bml-gateway-blocks',
			'bmlGatewayData',
			array(
				'title'       => isset( $this->settings['title'] ) ? $this->settings['title'] : 'Bank of Maldives',
				'description' => isset( $this->settings['description'] ) ? $this->settings['description'] : 'Pay securely using your BML account.',
				'icon'        => plugins_url( '/assets/images/bml.webp', BML_GATEWAY_PLUGIN_BASENAME ),
				'supports'    => array( 'products' ),
				'enabled'     => $this->is_active(),
			)
		);

		return array( 'bml-gateway-blocks' );
	}

	/**
	 * Get the payment method data to load into the frontend.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => isset( $this->settings['title'] ) ? $this->settings['title'] : 'Bank of Maldives',
			'description' => isset( $this->settings['description'] ) ? $this->settings['description'] : 'Pay securely using your BML account.',
			'icon'        => plugins_url( '/assets/images/bml.webp', BML_GATEWAY_PLUGIN_BASENAME ),
			'supports'    => array( 'products' ),
		);
	}
}

// Register the payment method with WooCommerce Blocks.
add_action(
	'woocommerce_blocks_payment_method_type_registration',
	function( $registry ) {
		$registry->register( new BML_Gateway_Blocks_Integration() );
	}
);

/**
 * Clear error notices before Store API checkout processing.
 *
 * This prevents error notices from previous payment attempts (like BML "Unauthorized")
 * from affecting other payment methods in the block-based checkout.
 *
 * The Store API checks for notices during validation and returns them as errors.
 * Since notices persist in the session, we need to clear them before the API processes the request.
 */
add_action(
	'rest_api_init',
	function() {
		// Check if this is a Store API checkout request.
		$is_store_api_request = false;

		// Check query string route (pretty permalinks disabled).
		if ( isset( $_GET['rest_route'] ) ) {
			$route = sanitize_text_field( wp_unslash( $_GET['rest_route'] ) );
			// Match /wc/store/v1/checkout, /wc/store/v2/checkout, /wc/store/checkout, etc.
			if ( preg_match( '#^/wc/store((/v[12])?)?/checkout#', $route ) ) {
				$is_store_api_request = true;
			}
		}

		// Check rewrite rule (pretty permalinks enabled).
		if ( ! $is_store_api_request && isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			// Match wp-json/wc/store/... checkout endpoints.
			if ( strpos( $uri, '/wc/store/' ) !== false && strpos( $uri, 'checkout' ) !== false ) {
				$is_store_api_request = true;
			}
		}

		// Clear notices for Store API checkout requests.
		if ( $is_store_api_request && function_exists( 'wc_clear_notices' ) && isset( WC()->session ) ) {
			wc_clear_notices();
		}
	},
	5 // Run early.
);
