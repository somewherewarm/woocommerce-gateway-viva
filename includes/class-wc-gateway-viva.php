<?php
/**
 * WC_Gateway_Viva class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Viva Wallet Payment Gateway
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Viva class.
 *
 * @class WC_Gateway_Viva
 */
class WC_Gateway_Viva extends WC_Payment_Gateway {

	/** Debug mode log to file. */
	const DEBUG_MODE_LOG = 'log';

	/** Debug mode disabled. */
	const DEBUG_MODE_OFF = 'off';

	/** @var string transction created message code. */
	const IPN_CODE_TRANSACTION_CREATED = 1796;

	/** @var string transction reversed message code. */
	const IPN_CODE_TRANSACTION_REVERSED = 1797;

	/**
	 * Created transaction types.
	 * @var string
	 */
	private $ipn_transaction_types;

	/**
	 * Configuration options for debugging - 'off', 'log'.
	 * @var string
	 */
	private $debug_mode;

	/**
	 * Supported functionality.
	 * @var array
	 */
	public $supports = array(
		'products',
		'refunds'
	);

	/**
	 * __construct method.
	 */
	public function __construct() {

		$this->method_title = 'Viva Wallet';
		$this->id           = 'viva';
		$this->icon         = apply_filters( 'wc_viva_gateway_logo', WC_Viva::plugin_url() . '/assets/images/viva-logo.png' );

		// Load form fields.
		$this->init_form_fields();

		// Load settings.
		$this->init_settings();

		// User variables.
		$this->title        = $this->settings[ 'title' ];
		$this->description  = $this->settings[ 'description' ];
		$this->endpoint     = $this->settings[ 'sandbox' ] === 'yes' ? 'http://demo.vivapayments.com' : 'https://www.vivapayments.com';
		$this->merchant_id  = $this->settings[ 'merchant_id' ];
		$this->source_code  = $this->settings[ 'source_code' ];
		$this->api_key      = $this->settings[ 'api_key' ];
		$this->instructions = $this->settings[ 'instructions' ];
		$this->debug_mode   = isset( $this->settings[ 'debug_mode' ] ) ? $this->settings[ 'debug_mode' ] : self::DEBUG_MODE_OFF;

		$this->ipn_transaction_types = array(
			self::IPN_CODE_TRANSACTION_CREATED => array(
				'0'  => __( 'Capture from Preauth', 'woocommerce-gateway-viva' ),
				'5'  => __( 'Charge Card', 'woocommerce-gateway-viva' ),
				'6'  => __( 'Charge Card w. Installments', 'woocommerce-gateway-viva' ),
				'9'  => __( 'Wallet Charge', 'woocommerce-gateway-viva' ),
				'15' => __( 'Dias Payment', 'woocommerce-gateway-viva' ),
				'16' => __( 'Cash Payment', 'woocommerce-gateway-viva' ),
			),
			self::IPN_CODE_TRANSACTION_REVERSED => array(
				'4'  => __( 'Refund Card Transaction', 'woocommerce-gateway-viva' ),
				'7'  => __( 'Void Card Transaction', 'woocommerce-gateway-viva' ),
				'11' => __( 'Wallet Refund Transaction', 'woocommerce-gateway-viva' ),
				'13' => __( 'Refund Card Transaction from Claim', 'woocommerce-gateway-viva' ),
				'16' => __( 'Void Cash', 'woocommerce-gateway-viva' ),
			),
		);

		/*
		 * Admin hooks.
		 */

		// Process admin gateway options.
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		// Add WC api 'wc_gateway_viva' request endpoint handler.
		add_action( 'woocommerce_api_wc_gateway_viva', array( $this, 'wc_api_request_handler' ) );

		/*
		 * Front-end hooks.
		 */

		// Add failure notice when redirected to the checkout->pay page after an unsuccessful attempt.
		add_action( 'before_woocommerce_pay', array( $this, 'checkout_order_pay_notice' ) );

		// Ensure checkout currency is EUR - Viva does not support other currencies.
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'check_order_currency' ) );
	}

	/**
	 * Show an error/message when the gateway is enabled but the merchant_id, api_key or source_code fields are empty or misconfigured.
	 */
	public function process_admin_options() {

		if ( 1 === WC_Viva_Admin_Notices::$gateway_configuration_error_code ) {
			$error = __( 'Viva Wallet requires a valid Merchant ID, API Key and Payment Source in order to accept payments.', 'woocommerce-gateway-viva' );
			WC_Admin_Settings::add_error( $error );
		} elseif ( 2 === WC_Viva_Admin_Notices::$gateway_configuration_error_code ) {
			$error = __( 'Invalid Merchant ID or API Key.', 'woocommerce-gateway-viva' );
			WC_Admin_Settings::add_error( $error );
		} elseif ( 0 === WC_Viva_Admin_Notices::$gateway_configuration_error_code ) {
			$message = __( 'Merchant ID and API Key validation successful.', 'woocommerce-gateway-viva' );
			WC_Admin_Settings::add_message( $message );
		}

		parent::process_admin_options();
	}

	/**
	 * Gateway settings form fields.
	 */
	public function init_form_fields() {

		$log_file_exists = false;

		if ( is_admin() && isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] === 'wc-settings' && isset( $_GET[ 'tab' ] ) && $_GET[ 'tab' ] === 'checkout' && isset( $_GET[ 'section' ] ) && $_GET[ 'section' ] === 'viva' ) {
			$log_file_name   = $this->get_log_file_name() . '-' . sanitize_file_name( wp_hash( $this->get_log_file_name() ) ) . '.log';
			$log_file_exists = file_exists( WC_LOG_DIR . '/' . $log_file_name );
		}

		$debug_mode_file_description = $log_file_exists ? sprintf( __( '<a href="%1$s">log file</a> at %2$s', 'woocommerce-gateway-viva' ), esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . sanitize_title( $log_file_name ) ) ), '<strong class="nobr">' . wc_get_log_file_path( $this->get_log_file_name() ) . '</strong>' ) : __( 'log file', 'woocommerce-gateway-viva' );
		$debug_mode_description      = sprintf( __( 'Save detailed error messages and API requests/responses to a %s.', 'woocommerce-gateway-viva' ), $debug_mode_file_description );

		$this->form_fields 	= array(
			'enabled' => array(
				'title'       => __( 'Enable Viva Wallet', 'woocommerce-gateway-viva' ),
				'label'       => __( 'Turn on Viva Wallet for WooCommerce', 'woocommerce-gateway-viva' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'sandbox' => array(
				'title'       => __( 'Use Sandbox', 'woocommerce-gateway-viva' ),
				'label'       => __( 'Use the sandbox for development', 'woocommerce-gateway-viva' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'merchant_id' => array(
				'title'       => __( 'Merchant ID', 'woocommerce-gateway-viva' ),
				'type'        => 'text',
				'description' => __( 'Your Viva Wallet Merchant ID.', 'woocommerce-gateway-viva' ),
				'default'     => ''
			),
			'api_key' => array(
				'title'       => __( 'API Key', 'woocommerce-gateway-viva' ),
				'type'        => 'text',
				'description' => __( 'Your Viva Wallet API Key.', 'woocommerce-gateway-viva' ),
				'default'     => ''
			),
			'source_code' => array(
				'title'       => __( 'Payment Source', 'woocommerce-gateway-viva' ),
				'type'        => 'text',
				'description' => __( 'The code of this Payment Source in your Viva Wallet account.', 'woocommerce-gateway-viva' ),
				'default'     => ''
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-gateway-viva' ),
				'type'        => 'text',
				'description' => __( 'Payment method title that the customer will see on your website.', 'woocommerce-gateway-viva' ),
				'default'     => __( 'Viva Wallet', 'woocommerce-gateway-viva' )
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-gateway-viva' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your website.', 'woocommerce-gateway-viva' ),
				'default'     => 'Pay with Viva Wallet.'
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'woocommerce-gateway-viva' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions visitors will see on the receipt page to make payment.', 'woocommerce-gateway-viva' ),
				'default'     => 'Click the button below to pay with Viva Wallet.'
			),
			'debug_mode' => array(
				'title'       => __( 'Debug Mode', 'woocommerce-gateway-viva' ),
				'type'        => 'select',
				'description' => $debug_mode_description,
				'default'     => self::DEBUG_MODE_OFF,
				'options'     => array(
					self::DEBUG_MODE_OFF => __( 'Off', 'woocommerce-gateway-viva' ),
					self::DEBUG_MODE_LOG => __( 'On', 'woocommerce-gateway-viva' ),
				),
			),
		);
	}

	/**
	 * No payment fields to show for the "Redirect Checkout" method - just show the description.
	 *
	 * @return string
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wpautop( $this->description );
		}
	}

	/**
	 * Override 'is_available' to account for the EUR currency limitation of Viva.
	 * Use the 'wc_viva_gateway_non_eur_currency_disable' filter if you wish to show the gateway when checking out with other currencies (return false).
	 * The order will still not go through - @see check_order_currency.
	 * Also, the gateway will be unavailable if the cofiguration creds have not been validated to work.
	 *
	 * @return boolean
	 */
	public function is_available() {

		$is_gateway_configuration_valid = 'yes' === get_option( 'wc_viva_settings_validated', false );

		$is_available = parent::is_available() && $is_gateway_configuration_valid;

		if ( $is_available && apply_filters( 'wc_viva_gateway_non_eur_currency_disable', true ) ) {
			$currency = get_woocommerce_currency();
			if ( 'EUR' !== $currency ) {
				$is_available = false;
			}
		}

		return $is_available;
	}

	/**
	 * If the gateway is not disabled for non-EUR currencies, then at least validate the currency during checkout:
	 * If the order currency is not EUR, show a notice and prompt user to select a different gateway.
	 * Do this before creating any orders.
	 *
	 * @param  array  $posted_data
	 * @return void
	 */
	public function check_order_currency( $posted_data ) {

		$chosen_gateway = WC()->session->get( 'chosen_payment_method' );

		if ( 'viva' === $chosen_gateway ) {
			$currency = get_woocommerce_currency();
			if ( 'EUR' !== $currency ) {
				wc_add_notice( sprintf( __( 'Your order could not be processed. The %1$s gateway only supports payments in <strong>Euros (%2$s)</strong>.', 'woocommerce-gateway-viva' ), $this->title, get_woocommerce_currency_symbol( 'EUR' ) ), 'error' );
			}
		}
	}

	/**
	 * Process the payment with the "Redirect Checkout" method.
	 *
	 * First, use the Viva API to create a Viva order and save the generated Viva order code.
	 * Then, process the payment by redirecting to the Viva Wallet website.
	 *
	 * @param  string  $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order      = wc_get_order( $order_id );
		$locale     = substr( get_locale(), 0, 2 );
		$lang       = 'el' === $locale ? 'el-GR' : 'en-US';

		$email      = WC_Viva_Core_Compatibility::get_order_prop( $order, 'billing_email' );
		$last_name  = WC_Viva_Core_Compatibility::get_order_prop( $order, 'billing_first_name' );
		$first_name = WC_Viva_Core_Compatibility::get_order_prop( $order, 'billing_last_name' );
		$phone      = WC_Viva_Core_Compatibility::get_order_prop( $order, 'billing_phone' );

		$payment_args = apply_filters( 'wc_viva_gateway_process_payment_args', array(
			'Email'        => $email,
			'FullName'     => $last_name . ' ' . $first_name,
			'RequestLang'  => 'en-US',
			'Phone'        => preg_replace( '/\D/', '', $phone ),
			'MerchantTrns' => $order_id,
			'CustomerTrns' => sprintf( __( 'Order #%s', 'woocommerce-gateway-viva' ), $order_id ),
			'Amount'       => number_format( $order->get_total() * 100, 0, '.', '' ), // Important: Amount in cents.
			'SourceCode'   => $this->source_code,
		), $order, $this );

		if ( $this->logging_enabled() ) {
			$this->log( 'Payment Request: ' . print_r( $payment_args, true ) );
		}

		$args = array(
			'body'        => $payment_args,
			'redirection' => 0,
			'headers'     => array(
				'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . $this->api_key )
			),
		);

		$response = wp_safe_remote_post( $this->endpoint . '/api/orders', $args );
		$data     = (array) json_decode( wp_remote_retrieve_body( $response ) );
		$result   = '';

		if ( $this->logging_enabled() ) {
			$this->log( 'Viva response: ' . print_r( $response, true ) );
		}

		if ( $data[ 'ErrorCode' ] > 0 ) {
			if ( $this->logging_enabled() ) {
				$this->log( 'Error Response: ' . print_r( $data, true ), 'error' );
			}
			$result = 'failure';
			wc_add_notice( sprintf( __( 'Payment with %1$s failed. Please try again later, or use a different payment method.', 'woocommerce-gateway-viva' ), $this->title ), 'error' );
			return;
		}

		$order_code = $data[ 'OrderCode' ];

		// Save the order code for reference.
		if ( WC_Viva_Core_Compatibility::is_wc_version_gte( '2.7' ) ) {
			$order->add_meta_data( '_viva_order_code', $order_code, true );
			$order->save();
		} else {
			update_post_meta( $order_id, '_viva_order_code', $order_code );
		}

		return array(
			'result'   => 'success',
			'redirect' => esc_url_raw( add_query_arg( array( 'ref' => $order_code, 'lang' => $lang ), $this->endpoint . '/web/checkout' ) ),
		);
	}

	/**
	 * Process refunds.
	 *
	 * @param  string  $order_id
	 * @param  mixed   $amount
	 * @param  string  $reason
	 * @return boolean|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$order          = wc_get_order( $order_id );
		$transaction_id = $order ? $order->get_transaction_id() : false;
		$refund_amount  = number_format( $amount * 100, 0, '.', '' ); // Amount must be in cents.

		if ( $this->logging_enabled() ) {
			$this->log( 'Processing refund for Order #' . $order_id . '.' );
		}

		if ( ! $transaction_id ) {
			$this->log( 'Refund Failed: Transaction ID not found.', 'error' );
			return new WP_Error( 'error', __( 'Refund failed: Transaction ID not found.', 'woocommerce-gateway-viva' ) );
		}

		if ( ! $refund_amount ) {
			$this->log( 'Refund Failed: Amount invalid.', 'error' );
			return new WP_Error( 'error', __( 'Refund failed: Amount invalid.', 'woocommerce-gateway-viva' ) );
		}

		$args = array(
			'method'  => 'DELETE',
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . $this->api_key )
			)
		);

		if ( $this->logging_enabled() ) {
			$this->log( 'Refund Transaction ID: ' . $transaction_id . '. Amount to refund: ' . $amount . '.' );
		}

		$response = wp_safe_remote_request( $this->endpoint . '/api/transactions/' . $transaction_id . '/?Amount=' . $refund_amount, $args );

		if ( is_wp_error( $response ) ) {

			$this->log( 'Refund Failed: ' . $response->get_error_message(), 'error' );
			return new WP_Error( 'error', $response->get_error_message() );

		} else {

			$data = (array) json_decode( wp_remote_retrieve_body( $response ) );

			if ( $this->logging_enabled() ) {
				$this->log( 'Viva response: ' . print_r( $response, true ) );
			}

			if ( isset( $data[ 'ErrorCode' ] ) && absint( $data[ 'ErrorCode' ] ) > 0 ) {
				if ( $this->logging_enabled() ) {
					$this->log( 'Error Response: ' . print_r( $data, true ), 'error' );
				}
				return new WP_Error( 'error', __( 'Refund failed. Reason: ' . $data[ 'ErrorText' ] . '.' , 'woocommerce-gateway-viva' ) );
			}

			if ( isset( $data[ 'StatusId' ] ) ) {
				return true;
			}

			return false;
		}
	}

	/**
	 * Retrieve the raw request entity (body).
	 *
	 * @return string
	 */
	private function get_raw_data() {
		// $HTTP_RAW_POST_DATA is deprecated on PHP 5.6.
		if ( function_exists( 'phpversion' ) && version_compare( phpversion(), '5.6', '>=' ) ) {
			return file_get_contents( 'php://input' );
		}

		global $HTTP_RAW_POST_DATA;

		// A bug in PHP < 5.2.2 makes $HTTP_RAW_POST_DATA not set by default.
		if ( ! isset( $HTTP_RAW_POST_DATA ) ) {
			$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
		}

		return $HTTP_RAW_POST_DATA;
	}

	/**
	 * Respond to payment confirmation & IPN requests intercepted by the 'wc_gateway_viva' WooCommerce API endpoint.
	 *
	 * @return void
	 */
	public function wc_api_request_handler() {

		if ( isset( $_GET[ 'result' ] ) ) {
			$this->processed_payment_handler();
		} else {
			$this->ipn_handler();
		}
	}

	/**
	 * Respond to payment confirmation & IPN requests.
	 *
	 * @return void
	 */
	private function processed_payment_handler() {

		$order    = false;
		$redirect = false;

		if ( isset( $_GET[ 's' ] ) ) {

			$order_code = $_GET[ 's' ];
			$order      = $this->get_order_id_from_viva_code( $order_code );
		}

		if ( $_GET[ 'result' ] === 'success' && isset( $_GET[ 's' ] ) ) {

			// Sucessful payment.
			if ( $order ) {
				if ( $this->logging_enabled() ) {
					$this->log( 'Processed payment for Order #' . WC_Viva_Core_Compatibility::get_order_id( $order ) . '. Waiting for IPN to complete order. Request details: ' . print_r( $_GET, true ) );
				}
				$redirect = $this->get_return_url( $order );
			} else {
				if ( $this->logging_enabled() ) {
					$this->log( 'Error: Processed payment for an unknown order. Possible fraudulent attempt. Request details: ' . print_r( $_GET, true ), 'error' );
				}
				$redirect = $this->get_return_url();
			}

		} else {

			// Failed payment.
			if ( $order ) {
				if ( $this->logging_enabled() ) {
					$this->log( 'Payment for Order #' . WC_Viva_Core_Compatibility::get_order_id( $order ) . ' failed. Redirecting user to checkout order. Request details: ' . print_r( $_GET, true ), 'error' );
				}
				$redirect = esc_url( add_query_arg( array( 'result' => 'failure', 'failed_viva_order_code' => $order_code ), $order->get_checkout_payment_url() ) );
			} else {
				if ( $this->logging_enabled() ) {
					$this->log( 'Error: Payment for an unknown order failed. The order may have been deleted, or this could indicate a possible fraudulent attempt. Request details: ' . print_r( $_GET, true ), 'error' );
				}
				$redirect = wc_get_checkout_url();
			}
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Request config token.
	 *
	 * @param  mixed  $merchant_id
	 * @param  mixed  $api_key
	 * @return string
	 */
	public function request_config_token( $merchant_id = false, $api_key = false ) {

		$merchant_id = false === $merchant_id ? $this->merchant_id : $merchant_id;
		$api_key     = false === $api_key ? $this->api_key : $api_key;

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $merchant_id . ':' . $api_key )
			),
		);

		$response = wp_safe_remote_get( $this->endpoint . '/api/messages/config/token', $args );

		if ( $this->logging_enabled() ) {
			$this->log( 'IPN Verification Response: ' . print_r( $response, true ) );
		}

		$data = wp_remote_retrieve_body( $response );

		return $data;
	}

	/**
	 * Respond to payment confirmation & IPN requests.
	 *
	 * @return void
	 */
	private function ipn_handler() {

		$request_content = $this->get_raw_data();

		if ( empty( $request_content ) ) {

			$data = $this->request_config_token();

			if ( $data ) {
				$token_data = (array) json_decode( $data );
				if ( isset( $token_data[ 'Key' ] ) ) {
					update_option( 'wc_viva_ipn_validated', md5( $token_data[ 'Key' ] ) );
				}
			}

			echo $data;
			exit;
		}

		$message_content = (array) json_decode( $request_content );
		$message_code    = (int) $message_content[ 'EventTypeId' ];
		$event_data      = (array) $message_content[ 'EventData' ];

		if ( $this->logging_enabled() ) {
			$this->log( 'IPN Received: ' . print_r( $message_content, true ) );
		}

		if ( self::IPN_CODE_TRANSACTION_CREATED === $message_code ) {

			// Find the order.
			$order_id = $event_data[ 'MerchantTrns' ];
			$order    = wc_get_order( $order_id );

			if ( ! $order ) {
				if ( $this->logging_enabled() ) {
					$this->log( 'Order not found.', 'error' );
				}
				return;
			}

			// Check order status.
			if ( ! $order->has_status( array( 'pending', 'on-hold' ) ) ) {
				return;
			}

			// Validate the event.
			$ipn_valid = $this->validate_ipn( $message_content );

			if ( $ipn_valid ) {
				if ( array_key_exists( $event_data[ 'TransactionTypeId' ], $this->ipn_transaction_types[ $message_code ] ) ) {
					$order->add_order_note( sprintf( __( 'Viva Wallet payment notification received: &quot;%1$s&quot; transaction with ID %2$s successful, paid by %3$s.', 'woocommerce-gateway-viva' ), $this->ipn_transaction_types[ $message_code ][ $event_data[ 'TransactionTypeId' ] ], $event_data[ 'TransactionId' ], $event_data[ 'Email' ] ) );
					// Mark order as completed.
					$order->payment_complete( $event_data[ 'TransactionId' ] );
				} else {
					$order->add_order_note( sprintf( __( 'Viva Wallet payment notification received: Unrecognized &quot;%1$s&quot; transaction with ID %2$s, paid by %3$s.', 'woocommerce-gateway-viva' ), $event_data[ 'TransactionTypeId' ], $event_data[ 'TransactionId' ], $event_data[ 'Email' ] ) );
				}
			} else {
				$order->add_order_note( sprintf( __( 'Invalid Viva Wallet payment notification received: Possible fraudulent order attempt with transaction ID %s.', 'woocommerce-gateway-viva' ), $event_data[ 'TransactionId' ] ) );
			}

		} elseif ( self::IPN_CODE_TRANSACTION_REVERSED === $message_code ) {

			// Find the order.
			$order_id = $event_data[ 'MerchantTrns' ];
			$order    = $order_id ? wc_get_order( $order_id ) : $this->get_order_id_from_viva_code( $event_data[ 'OrderCode' ] );

			if ( ! $order ) {
				if ( $this->logging_enabled() ) {
					$this->log( 'Order not found.' );
				}
				return;
			}

			// Validate the event.
			$ipn_valid = $this->validate_ipn( $message_content );

			if ( $ipn_valid ) {
				// Only handle full refunds, not partial.
				if ( $order->get_total() == ( $event_data[ 'Amount' ] * -1 ) ) {
					$order->add_order_note( sprintf( __( 'Viva Wallet refund notification received: &quot;%1$s&quot; transaction with ID %2$s.', 'woocommerce-gateway-viva' ), $this->ipn_transaction_types[ $message_code ][ $event_data[ 'TransactionTypeId' ] ], $event_data[ 'TransactionId' ] ) );
					// Mark order as refunded.
					$order->update_status( 'refunded' );
				} else {
					$order->add_order_note( sprintf( __( 'Viva Wallet partial refund notification received: &quot;%1$s&quot; transaction with ID %2$s. Refunded amount: %3$s.', 'woocommerce-gateway-viva' ), $this->ipn_transaction_types[ $event_data[ 'TransactionTypeId' ] ], $event_data[ 'TransactionId' ], $event_data[ 'Amount' ] ) );
				}
			} else {
				$order->add_order_note( sprintf( __( 'Invalid Viva Wallet refund notification received: Possible fraudulent attempt with transaction ID %s.', 'woocommerce-gateway-viva' ), $event_data[ 'TransactionId' ] ) );
			}
		}
	}

	/**
	 * Validate that the received IPN mesage was genuine by making a Viva API call to double check the transaction ID and status.
	 *
	 * @param  array  $message
	 */
	private function validate_ipn( $message ) {

		$valid                     = false;
		$event_data                = (array) $message[ 'EventData' ];
		$transaction_id            = isset( $event_data[ 'TransactionId' ] ) ? $event_data[ 'TransactionId' ] : false;
		$ipn_transaction_status_id = isset( $event_data[ 'StatusId' ] ) ?  $event_data[ 'StatusId' ] : false;

		if ( ! $transaction_id || ! $ipn_transaction_status_id ) {
			return $valid;
		}

		if ( $this->logging_enabled() ) {
			$this->log( 'Validating IPN - checking status of Transaction ID: ' . $transaction_id . "..." );
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . $this->api_key )
			),
		);

		$response = wp_safe_remote_get( $this->endpoint . '/api/transactions/' . $transaction_id, $args );

		if ( $this->logging_enabled() ) {
			$this->log( 'Viva response: ' . print_r( $response, true ) );
		}

		$data       = (array) json_decode( wp_remote_retrieve_body( $response ) );
		$error_code = $data[ 'ErrorCode' ];

		if ( isset( $data[ 'ErrorCode' ] ) && 0 === absint( $data[ 'ErrorCode' ] ) && isset( $data[ 'Transactions' ] ) ) {

			$transaction_data      = (array) current( $data[ 'Transactions' ] );
			$transaction_status_id = isset( $transaction_data[ 'StatusId' ] ) ? $transaction_data[ 'StatusId' ] : false;

			if ( $ipn_transaction_status_id === $transaction_status_id ) {
				$valid = true;
			}
		}

		if ( $this->logging_enabled() ) {
			if ( $valid ) {
				$this->log( 'IPN is valid. Transaction existence and status confirmed.' );
			} else {
				$this->log( sprintf( 'IPN Validation failed (Transaction Status ID: %s, expected %s ).', $transaction_status_id ? $transaction_status_id : 'unknown', $ipn_transaction_status_id ) );
			}
		}

		return $valid;
	}

	/**
	 * Get the order id that corresponds to a given viva order number.
	 * Still relies on 'WP_Query'.
	 *
	 * @param  string  $code
	 * @return string
	 */
	public function get_order_id_from_viva_code( $code ) {

		// Find the order from the saved viva order code.
		$args = array(
			'post_type'   => 'shop_order',
			'post_status' => wc_get_order_statuses(),
			'fields'      => 'ids',
			'meta_query'  => array(
				array(
					'key'     => '_viva_order_code',
					'value'   => $code,
					'compare' => '='
				)
			)
		);

		$query    = new WP_Query( $args );
		$order_id = ! empty( $query->posts ) ? current( $query->posts ) : false;

		return wc_get_order( $order_id );
	}

	/**
	 * Show notice when redirecting to the order pay page after a failed attempt.
	 *
	 * @return void
	 */
	public function checkout_order_pay_notice() {

		if ( is_checkout_pay_page() && isset( $_GET[ 'result' ] ) && isset( $_GET[ 'failed_viva_order_code' ] ) ) {
			wc_add_notice( sprintf( __( 'Payment with %1$s failed. Please try again later, or use a different payment method.', 'woocommerce-gateway-viva' ), $this->title ), 'error' );
		}
	}

	/**
	 * Returns the plugin gateway id.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * True if debug logging is enabled.
	 *
	 * @return boolean
	 */
	public function logging_enabled() {
		return self::DEBUG_MODE_LOG === $this->debug_mode;
	}

	/**
	 * Saves errors or messages to a log.
	 *
	 * @param string $message
	 * @param string $level
	 */
	public function log( $message, $level = 'info' ) {
		WC_Viva_Core_Compatibility::log( $message, $level, $this->get_log_file_name() );
	}

	/**
	 * Returns the log file name prefix.
	 *
	 * @param string $message
	 * @param string $level
	 */
	private function get_log_file_name() {
		return 'wc_' . $this->get_id();
	}
}

