<?php
/**
 * BML Payment Gateway Class.
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

defined('ABSPATH') || exit;

/**
 * BML Gateway Payment Gateway Class.
 */
class BML_Gateway_Payment_Gateway extends WC_Payment_Gateway
{

	/**
	 * API endpoints.
	 */
	const API_URL_PRODUCTION = 'https://api.merchants.bankofmaldives.com.mv/public';
	const API_URL_TESTING = 'https://api.uat.merchants.bankofmaldives.com.mv/public';

	/**
	 * Gateway features supported.
	 *
	 * @var array
	 */
	public $supports = array('products', 'refunds');

	/**
	 * Test mode flag.
	 *
	 * @var bool
	 */
	public $testmode = false;

	/**
	 * BML App ID (client_id).
	 *
	 * @var string
	 */
	public $app_id = '';

	/**
	 * BML API Key (client_secret).
	 *
	 * @var string
	 */
	public $api_key = '';

	/**
	 * Transaction currency.
	 *
	 * @var string
	 */
	public $currency = 'MVR';

	/**
	 * Disable redirect for testing.
	 *
	 * @var bool
	 */
	public $disable_redirect = false;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->id = 'bml_gateway';
		$this->icon = BML_GATEWAY_PLUGIN_URL . 'assets/images/bml.webp';
		$this->has_fields = false; // Redirect method doesn't need payment fields
		$this->method_title = __('BML Gateway by WDM', 'bml-gateway');
		$this->method_description = __('Pay securely using Bank of Maldives payment gateway via redirect method.', 'bml-gateway');

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get setting values.
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->enabled = $this->get_option('enabled');
		$this->testmode = 'yes' === $this->get_option('testmode');
		$this->app_id = $this->get_option('app_id');
		$this->api_key = $this->get_option('api_key');
		$this->currency = $this->get_option('currency');
		$this->disable_redirect = 'yes' === $this->get_option('disable_redirect');

		// Save settings hook.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available()
	{
		$is_available = 'yes' === $this->enabled;

		// Allow other plugins to filter availability.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WooCommerce hook.
		return apply_filters('woocommerce_payment_gateway_is_available', $is_available, $this);
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'bml-gateway'),
				'type' => 'checkbox',
				'label' => __('Enable Bank of Maldives Payment Gateway', 'bml-gateway'),
				'default' => 'yes',
			),
			'title' => array(
				'title' => __('Title', 'bml-gateway'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'bml-gateway'),
				'default' => __('Bank of Maldives', 'bml-gateway'),
				'desc_tip' => true,
			),
			'description' => array(
				'title' => __('Description', 'bml-gateway'),
				'type' => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.', 'bml-gateway'),
				'default' => __('Pay securely using your BML account.', 'bml-gateway'),
				'desc_tip' => true,
			),
			'testmode' => array(
				'title' => __('Test Mode', 'bml-gateway'),
				'type' => 'checkbox',
				'label' => __('Enable Test Mode', 'bml-gateway'),
				'default' => 'yes',
				'description' => __('Use the testing environment instead of production.', 'bml-gateway'),
			),
			'connection_details' => array(
				'title' => __('Connection Details', 'bml-gateway'),
				'type' => 'title',
				'description' => __('Enter your BML Connect API credentials. Get these from the <a href="https://dashboard.merchants.bankofmaldives.com.mv" target="_blank">BML Merchant Portal</a>.', 'bml-gateway'),
			),
			'app_id' => array(
				'title' => __('App ID', 'bml-gateway'),
				'type' => 'text',
				'description' => __('Enter your App ID (client_id) from BML Connect.', 'bml-gateway'),
				'default' => '',
				'desc_tip' => true,
			),
			'api_key' => array(
				'title' => __('API Key', 'bml-gateway'),
				'type' => 'password',
				'description' => __('Enter your API Key (client_secret) from BML Connect.', 'bml-gateway'),
				'default' => '',
				'desc_tip' => true,
			),
			'currency' => array(
				'title' => __('Transaction Currency', 'bml-gateway'),
				'type' => 'select',
				'description' => __('Select the currency for transactions.', 'bml-gateway'),
				'default' => 'MVR',
				'desc_tip' => true,
				'options' => array(
					'MVR' => __('MVR - Maldivian Rufiyaa', 'bml-gateway'),
					'USD' => __('USD - US Dollar', 'bml-gateway'),
				),
			),
			'webhook_info' => array(
				'title' => __('Webhook Configuration', 'bml-gateway'),
				'type' => 'title',
				'description' => $this->get_webhook_description_safe(),
			),
			'endpoint_info' => array(
				'title' => __('Endpoint Information', 'bml-gateway'),
				'type' => 'title',
				'description' => $this->get_endpoint_description_safe(),
			),
			'disable_redirect' => array(
				'title' => __('Disable Redirect (Testing)', 'bml-gateway'),
				'type' => 'checkbox',
				'label' => __('Disable customer redirect after payment (for webhook testing only)', 'bml-gateway'),
				'default' => 'no',
				'description' => __('When enabled, customers will not be redirected after payment. The webhook will still be processed. This is useful for testing webhooks without customer redirects.', 'bml-gateway'),
				'desc_tip' => true,
			),
		);
	}

	/**
	 * Get webhook description with the webhook URL (safe for early loading).
	 *
	 * @return string
	 */
	protected function get_webhook_description_safe()
	{
		if (!function_exists('home_url')) {
			return __('Configure your webhook URL in the <a href="https://dashboard.merchants.bankofmaldives.com.mv" target="_blank">BML Merchant Portal</a> to receive server-to-server payment notifications.', 'bml-gateway');
		}

		$webhook_url = home_url('/bml-gateway/webhook');

		return sprintf(
			/* translators: %s: webhook URL */
			__('Configure your webhook URL in the <a href="https://dashboard.merchants.bankofmaldives.com.mv" target="_blank">BML Merchant Portal</a> to receive server-to-server payment notifications (POST).<br><strong>Webhook URL:</strong> <code style="background:#f0f0f1;padding:4px 8px;border-radius:3px;font-size:12px;">%s</code>', 'bml-gateway'),
			esc_html($webhook_url)
		);
	}

	/**
	 * Get endpoint description showing both webhook and return URLs (safe for early loading).
	 *
	 * @return string
	 */
	protected function get_endpoint_description_safe()
	{
		if (!function_exists('home_url')) {
			return __('Webhook and return endpoints are automatically configured.', 'bml-gateway');
		}

		$webhook_url = home_url('/bml-gateway/webhook');
		$return_url = home_url('/bml-gateway/return');

		return sprintf(
			/* translators: 1: webhook URL, 2: return URL */
			__('<strong>Webhook Endpoint</strong> (for BML server-to-server notifications):<br><code style="background:#f0f0f1;padding:4px 8px;border-radius:3px;font-size:12px;">%1$s</code><br><br><strong>Return Endpoint</strong> (for customer redirects after payment):<br><code style="background:#f0f0f1;padding:4px 8px;border-radius:3px;font-size:12px;">%2$s</code>', 'bml-gateway'),
			esc_html($webhook_url),
			esc_html($return_url)
		);
	}

	/**
	 * Get API base URL.
	 *
	 * @return string
	 */
	protected function get_api_url()
	{
		return $this->testmode ? self::API_URL_TESTING : self::API_URL_PRODUCTION;
	}

	/**
	 * Generate transaction signature.
	 *
	 * @param int    $amount    Amount in cents.
	 * @param string $currency  Currency code.
	 * @return string
	 */
	protected function generate_signature($amount, $currency)
	{
		$string_to_sign = 'amount=' . $amount . '&currency=' . $currency . '&apiKey=' . $this->api_key;
		return sha1($string_to_sign);
	}

	/**
	 * Create a transaction with BML API.
	 *
	 * @param WC_Order $order Order object.
	 * @return array|WP_Error
	 */
	protected function create_transaction($order)
	{
		// Ensure settings are loaded before making API call.
		$this->ensure_settings_loaded();

		$api_url = $this->get_api_url() . '/transactions';

		// Use configured currency.
		$currency = $this->currency;

		// Convert amount to cents.
		$amount = (int) round($order->get_total() * 100);

		// Generate return URL for customer redirect after payment.
		$return_url = add_query_arg(
			array(
				'order_id' => $order->get_id(),
				'order_key' => $order->get_order_key(),
			),
			home_url('/bml-gateway/return')
		);

		// Generate signature for debug.
		$signature = $this->generate_signature($amount, $currency);

		// Build request body.
		$body = array(
			'localId' => $order->get_id(),
			'customerReference' => $order->get_order_number(),
			'signature' => $signature,
			'amount' => $amount,
			'currency' => $currency,
			'redirectUrl' => $return_url,
			'appVersion' => BML_GATEWAY_VERSION,
			'apiVersion' => '2.0',
			'deviceId' => $this->app_id,
			'signMethod' => 'sha1',
		);

		// Make API request.
		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => $this->api_key,
					'Accept' => 'application/json',
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode($body),
				'timeout' => 45,
				'data_format' => 'body',
			)
		);

		// Check for errors.
		if (is_wp_error($response)) {
			return new WP_Error('api_error', $response->get_error_message());
		}

		$response_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);

		$response_body_decoded = json_decode($response_body, true);

		if (201 !== $response_code && 200 !== $response_code) {
			// Handle 401 Unauthorized specifically with more details.
			if (401 === $response_code) {
				$error_message = isset($response_body_decoded['message']) ? $response_body_decoded['message'] : __('Unauthorized - Please check your App ID and API Key', 'bml-gateway');
				return new WP_Error('api_error', $error_message, $response_body_decoded);
			}

			$error_message = isset($response_body_decoded['message']) ? $response_body_decoded['message'] : __('Unknown API error', 'bml-gateway');
			return new WP_Error('api_error', $error_message, $response_body_decoded);
		}

		// Log transaction creation.
		$this->log(sprintf('Transaction created. Order ID: %s, Transaction ID: %s', $order->get_id(), $response_body_decoded['id'] ?? 'unknown'));

		return $response_body_decoded;
	}

	/**
	 * Log a message to the WooCommerce log.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	protected function log($message)
	{
		if (function_exists('wc_get_logger')) {
			$logger = wc_get_logger();
			$logger->info($message, array('source' => 'bml_gateway'));
		}
	}

	/**
	 * Ensure settings are loaded (fix for WooCommerce Blocks/Store API).
	 *
	 * @return void
	 */
	protected function ensure_settings_loaded()
	{
		$was_empty_app_id = empty($this->app_id);
		$was_empty_api_key = empty($this->api_key);

		if ($was_empty_app_id || $was_empty_api_key) {
			// Reload settings from database.
			$settings = get_option('woocommerce_' . $this->id . '_settings', array());

			if (is_array($settings)) {
				$this->app_id = isset($settings['app_id']) ? $settings['app_id'] : '';
				$this->api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
				$this->testmode = isset($settings['testmode']) && 'yes' === $settings['testmode'];
				$this->currency = isset($settings['currency']) ? $settings['currency'] : 'MVR';
				$this->disable_redirect = isset($settings['disable_redirect']) && 'yes' === $settings['disable_redirect'];
			}
		}
	}

	/**
	 * Process payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment($order_id)
	{
		// Clear any previous error notices to prevent them from affecting this payment attempt.
		// This is especially important for WooCommerce Blocks/Store API where notices persist.
		if (function_exists('wc_clear_notices')) {
			wc_clear_notices();
		}

		// Ensure settings are loaded (fix for WooCommerce Blocks/Store API).
		$this->ensure_settings_loaded();

		$order = wc_get_order($order_id);

		// Check if credentials are configured.
		if (empty($this->app_id) || empty($this->api_key)) {
			wc_add_notice(__('Payment gateway is not configured properly. Please contact the site administrator.', 'bml-gateway'), 'error');
			return array(
				'result' => 'failure',
				'message' => __('Payment gateway configuration error.', 'bml-gateway'),
			);
		}

		// Create transaction.
		$transaction = $this->create_transaction($order);

		if (is_wp_error($transaction)) {
			/* translators: %s: error message */
			wc_add_notice(sprintf(__('Payment error: %s', 'bml-gateway'), $transaction->get_error_message()), 'error');
			return array(
				'result' => 'failure',
				'message' => $transaction->get_error_message(),
			);
		}

		// Store transaction ID in order.
		if (isset($transaction['id'])) {
			$order->update_meta_data('_bml_transaction_id', $transaction['id']);
		}

		// Mark order as pending payment.
		$order->update_status('pending', __('Waiting for BML payment confirmation.', 'bml-gateway'));
		$order->save();

		// Redirect to BML payment page.
		if (isset($transaction['url'])) {
			return array(
				'result' => 'success',
				'redirect' => $transaction['url'],
			);
		}

		// Fallback if no URL returned.
		wc_add_notice(__('Payment error: No redirect URL received from BML.', 'bml-gateway'), 'error');
		return array(
			'result' => 'failure',
			'message' => __('Unable to redirect to payment page.', 'bml-gateway'),
		);
	}

	/**
	 * Webhook handler for BML server-to-server notifications.
	 *
	 * Handles POST requests from BML servers with transaction state updates.
	 */
	public function webhook_handler()
	{
		// Log webhook received.
		$this->log('Webhook received from BML server.');

		// Get raw POST data.
		$raw_input = file_get_contents('php://input');
		$data = json_decode($raw_input, true);

		// Log the received webhook data.
		$this->log('Webhook data: ' . $raw_input);

		// Validate JSON data.
		if (empty($data) || !is_array($data)) {
			$this->log('Webhook error: Invalid JSON data received.');
			status_header(400);
			echo json_encode(array('error' => 'Invalid JSON'));
			exit;
		}

		// Verify webhook signature.
		$signature = isset($data['signature']) ? $data['signature'] : '';
		if (!$this->verify_webhook_signature($data, $signature)) {
			$this->log('Webhook error: Signature verification failed.');
			status_header(401);
			echo json_encode(array('error' => 'Invalid signature'));
			exit;
		}

		// Get transaction ID from webhook.
		$transaction_id = isset($data['transactionId']) ? $data['transactionId'] : '';
		$local_id = isset($data['localId']) ? $data['localId'] : '';
		$state = isset($data['state']) ? $data['state'] : '';

		$this->log(sprintf('Webhook - Transaction ID: %s, Local ID: %s, State: %s', $transaction_id, $local_id, $state));

		// Find order by local ID (which is our order ID).
		$order = wc_get_order($local_id);

		if (!$order) {
			$this->log(sprintf('Webhook error: Order not found for Local ID: %s', $local_id));
			status_header(404);
			echo json_encode(array('error' => 'Order not found'));
			exit;
		}

		// Verify the transaction ID matches what we have stored.
		$stored_transaction_id = $order->get_meta('_bml_transaction_id', true);
		if ($stored_transaction_id && $stored_transaction_id !== $transaction_id) {
			$this->log(sprintf('Webhook warning: Transaction ID mismatch. Stored: %s, Received: %s', $stored_transaction_id, $transaction_id));
		}

		// Process the transaction state.
		$this->process_webhook_state($order, $data);

		// Return 200 OK to acknowledge the webhook.
		status_header(200);
		echo json_encode(array('status' => 'success'));
		exit;
	}

	/**
	 * Process webhook transaction state and update order.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Webhook data from BML.
	 */
	protected function process_webhook_state($order, $data)
	{
		$state = isset($data['state']) ? $data['state'] : '';
		$transaction_id = isset($data['transactionId']) ? $data['transactionId'] : '';

		$this->log(sprintf('Processing webhook state: %s for Order ID: %s', $state, $order->get_id()));

		switch ($state) {
			case 'CONFIRMED':
				if (!$order->is_paid()) {
					$order->payment_complete($transaction_id);
					/* translators: %s: transaction ID */
					$order->add_order_note(sprintf(__('BML payment confirmed via webhook. Transaction ID: %s', 'bml-gateway'), $transaction_id));
					$this->log(sprintf('Order %s marked as paid via webhook', $order->get_id()));
				}
				break;

			case 'CANCELLED':
				$order->update_status('cancelled', __('Payment was cancelled via webhook.', 'bml-gateway'));
				$this->log(sprintf('Order %s cancelled via webhook', $order->get_id()));
				break;

			case 'REFUNDED':
				$order->update_status('refunded', __('Payment was refunded via webhook.', 'bml-gateway'));
				$this->log(sprintf('Order %s refunded via webhook', $order->get_id()));
				break;

			case 'REFUND_REQUESTED':
				$order->update_status('on-hold', __('Refund requested via webhook.', 'bml-gateway'));
				$this->log(sprintf('Order %s - refund requested via webhook', $order->get_id()));
				break;

			case 'QR_CODE_GENERATED':
			default:
				// Payment still pending - no action needed.
				$this->log(sprintf('Order %s - payment pending (state: %s)', $order->get_id(), $state));
				break;
		}
	}

	/**
	 * Return handler for customer redirects from BML payment page.
	 *
	 * Handles GET requests when customers return from BML after payment.
	 *
	 * Note: WordPress nonce verification is not used here as this endpoint
	 * receives redirects from an external payment gateway where traditional
	 * nonces cannot be validated. Security is provided by:
	 * 1. Order key validation with hash_equals()
	 * 2. Transaction verification via BML API
	 */
	public function return_handler()
	{
		// Ensure settings are loaded.
		$this->ensure_settings_loaded();

		// Log customer return received.
		$this->log('Customer return from BML payment page. Processing and verifying transaction.');

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Order key validation is used instead for payment gateway returns.
		$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Order key validation is used instead for payment gateway returns.
		$order_key = isset($_GET['order_key']) ? sanitize_text_field(wp_unslash($_GET['order_key'])) : '';

		// Validate order.
		$order = wc_get_order($order_id);

		if (!$order) {
			$this->log('Callback error: Invalid order.');
			wc_add_notice(__('Invalid order.', 'bml-gateway'), 'error');
			wp_safe_redirect(wc_get_page_permalink('shop'));
			exit;
		}

		// Validate order key.
		if (!hash_equals($order->get_order_key(), $order_key)) {
			$this->log('Callback error: Invalid order key.');
			wc_add_notice(__('Invalid order key.', 'bml-gateway'), 'error');
			wp_safe_redirect(wc_get_page_permalink('shop'));
			exit;
		}

		// Get transaction ID from order.
		$transaction_id = $order->get_meta('_bml_transaction_id', true);

		if (empty($transaction_id)) {
			$this->log('Callback error: Transaction not found.');
			wc_add_notice(__('Transaction not found.', 'bml-gateway'), 'error');
			wp_safe_redirect($this->get_return_url($order));
			exit;
		}

		// Query transaction status from BML.
		$status = $this->query_transaction($transaction_id);

		if (is_wp_error($status)) {
			$this->log('Callback error: Could not verify payment status - ' . $status->get_error_message());
			/* translators: %s: error message */
			wc_add_notice(sprintf(__('Could not verify payment status: %s', 'bml-gateway'), $status->get_error_message()), 'error');
			wp_safe_redirect($this->get_return_url($order));
			exit;
		}

		// Process based on transaction state.
		$this->process_transaction_state($order, $status);

		// Check if redirect is disabled for testing.
		if ($this->disable_redirect) {
			$this->log('Redirect disabled for testing. Returning JSON response.');
			status_header(200);
			echo json_encode(array(
				'status' => 'success',
				'order_id' => $order->get_id(),
				'transaction_state' => isset($status['state']) ? $status['state'] : 'unknown',
				'message' => 'Callback processed successfully (redirect disabled for testing)',
			));
			exit;
		}

		// Redirect to thank you page.
		wp_safe_redirect($this->get_return_url($order));
		exit;
	}

	/**
	 * Query transaction status from BML API.
	 *
	 * @param string $transaction_id Transaction ID.
	 * @return array|WP_Error
	 */
	protected function query_transaction($transaction_id)
	{
		// Ensure settings are loaded before making API call.
		$this->ensure_settings_loaded();

		$api_url = $this->get_api_url() . '/transactions/' . $transaction_id;

		$response = wp_remote_get(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => $this->api_key,
					'Accept' => 'application/json',
				),
				'timeout' => 45,
			)
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code($response);
		$response_body = json_decode(wp_remote_retrieve_body($response), true);

		if (200 !== $response_code) {
			return new WP_Error('api_error', __('Failed to query transaction status.', 'bml-gateway'));
		}

		return $response_body;
	}

	/**
	 * Process transaction state and update order.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $status Transaction status from BML.
	 */
	protected function process_transaction_state($order, $status)
	{
		$state = isset($status['state']) ? $status['state'] : '';

		switch ($state) {
			case 'CONFIRMED':
				if (!$order->is_paid()) {
					$transaction_id = $status['id'] ?? '';
					$order->payment_complete($transaction_id);
					/* translators: %s: transaction ID */
					$order->add_order_note(sprintf(__('BML payment completed. Transaction ID: %s', 'bml-gateway'), $transaction_id));
				}
				break;

			case 'CANCELLED':
				$order->update_status('cancelled', __('Payment was cancelled by the customer.', 'bml-gateway'));
				wc_add_notice(__('Your payment has been cancelled.', 'bml-gateway'), 'notice');
				break;

			case 'QR_CODE_GENERATED':
			default:
				// Payment still pending.
				$order->update_status('pending', __('Waiting for BML payment confirmation.', 'bml-gateway'));
				wc_add_notice(__('Your payment is being processed. You will receive a confirmation shortly.', 'bml-gateway'), 'notice');
				break;
		}
	}

	/**
	 * Verify webhook signature.
	 *
	 * @param array $data   Webhook data.
	 * @param string $signature Signature from webhook.
	 * @return bool
	 */
	protected function verify_webhook_signature($data, $signature)
	{
		if (!isset($data['amount']) || !isset($data['currency'])) {
			return false;
		}

		$expected_signature = sha1('amount=' . $data['amount'] . '&currency=' . $data['currency'] . '&apiKey=' . $this->api_key);
		return hash_equals($expected_signature, $signature);
	}
}
