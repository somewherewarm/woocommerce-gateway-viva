<?php
/**
 * Plugin Name: WooCommerce Viva Wallet Gateway
 * Plugin URI: http://www.woothemes.com/
 * Description: Adds the Viva Wallet payment gateway to your WooCommerce website.
 * Author: SomewhereWarm
 * Author URI: http://www.somewherewarm.net/
 * Version: 1.0.0
 * Text Domain: woocommerce-gateway-viva
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2016 SomewhereWarm
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

add_action( 'plugins_loaded', 'wc_viva_gateway_init', 0 );

function wc_viva_gateway_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	load_plugin_textdomain( 'woocommerce-gateway-viva', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages' );

	class WC_Gateway_Viva extends WC_Payment_Gateway {

		/** Debug mode log to file */
		const DEBUG_MODE_LOG = 'log';

		/** Debug mode disabled */
		const DEBUG_MODE_OFF = 'off';

		/** @var string transction created message code */
		const IPN_CODE_TRANSACTION_CREATED = 1796;

		/** @var string transction reversed message code */
		const IPN_CODE_TRANSACTION_REVERSED = 1797;

		/** @var string created transaction types */
		private $ipn_transaction_types;

		/** @var string configuration option: 4 options for debug mode - off, checkout, log, both */
		private $debug_mode;

		/** @var WC_Logger instance */
		private $logger;

		function __construct() {

			$this->method_title = 'Viva Wallet';
			$this->id           = 'viva';
			$this->icon         = apply_filters( 'wc_viva_gateway_logo', plugins_url( basename( dirname(__FILE__) ) . '/assets/images/viva-logo.png' ) );

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
					'11'  => __( 'Wallet Refund Transaction', 'woocommerce-gateway-viva' ),
					'13'  => __( 'Refund Card Transaction from Claim', 'woocommerce-gateway-viva' ),
					'16' => __( 'Void Cash', 'woocommerce-gateway-viva' ),
				),
			);

			add_action( 'woocommerce_api_wc_gateway_viva', array( $this, 'wc_api_request_handler' ) );
			add_action( 'before_woocommerce_pay', array( $this, 'checkout_order_pay_notice' ) );

			if ( is_admin() ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			}
		}

		function init_form_fields() {
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
					'title'       => __( 'Viva Wallet Merchant ID', 'woocommerce-gateway-viva' ),
					'type'        => 'text',
					'description' => __( 'Your Viva Wallet Merchant ID.', 'woocommerce-gateway-viva' ),
					'default'     => ''
				),
				'api_key' => array(
					'title'       => __( 'Viva Wallet API Key', 'woocommerce-gateway-viva' ),
					'type'        => 'text',
					'description' => __( 'Your Viva Wallet API Key.', 'woocommerce-gateway-viva' ),
					'default'     => ''
				),
				'source_code' => array(
					'title'       => __( 'Source code', 'woocommerce-gateway-viva' ),
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
					'description' => sprintf( __( 'Save detailed error messages and API requests/responses to the debug log: %s', 'woocommerce-gateway-viva' ), '<strong class="nobr">' . wc_get_log_file_path( $this->get_id() ) . '</strong>' ),
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
		function payment_fields() {
			if ( $this->description ) {
				echo wpautop( $this->description );
			}
		}

		/**
		 * Process the payment with the "Redirect Checkout" method.
		 *
		 * First, use the Viva API to create a Viva order and save the generated Viva order code.
		 * Then, process the payment by redirecting to the Viva Wallet website.
		 *
		 * @param  string $order_id
		 * @return array
		 */
		function process_payment( $order_id ) {

			$order     = wc_get_order( $order_id );
			$viva_args = apply_filters( 'wc_viva_gateway_process_payment_args', array(
				'Email'           => $order->billing_email,
				'FullName'        => $order->billing_last_name . ' ' . $order->billing_first_name,
				'RequestLang'     => 'en-US',
				'Phone'           => preg_replace( '/\D/', '', $order->billing_phone ),
				'MerchantTrns'    => $order->id,
				'CustomerTrns'    => sprintf( __( 'Order #%s', 'woocommerce-gateway-viva' ), $order->id ),
				// Amount in cents.
				'Amount'          => number_format( $order->get_total() * 100, 0, '.', '' ),
				'SourceCode'      => $this->source_code,
			), $order, $this );

			if ( $this->debug_log() ) {
				$this->log( "Payment Request: " . print_r( $viva_args, true ) );
			}

			$args = array(
				'body' => $viva_args,
				'redirection' => 0,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . $this->api_key )
				),
			);

			$response = wp_safe_remote_post( $this->endpoint . '/api/orders', $args );
			$data     = (array) json_decode( wp_remote_retrieve_body( $response ) );
			$result   = '';

			if ( $this->debug_log() ) {
				$this->log( "Viva Response: " . print_r( $response, true ) );
			}

			if ( $data[ 'ErrorCode' ] > 0 ) {
				if ( $this->debug_log() ) {
					$this->log( 'Error Response: ' . print_r( $data, true ) );
				}
				$result = 'failure';
				wc_add_notice( sprintf( __( 'Payment with %1$s failed. Please try again later, or use a different payment method.', 'woocommerce-gateway-viva' ), $this->title ), 'error' );
				return;
			}

			$order_code = $data[ 'OrderCode' ];

			// Save the order code for reference.
			update_post_meta( $order->id, '_viva_order_code', $order_code );

			return array(
				'result'   => 'success',
				'redirect' => add_query_arg( 'ref', $order_code, $this->endpoint . '/web/checkout' ),
			);
		}

		/**
		 * Retrieve the raw request entity (body).
		 *
		 * @return string
		 */
		private function get_raw_data() {
			// $HTTP_RAW_POST_DATA is deprecated on PHP 5.6
			if ( function_exists( 'phpversion' ) && version_compare( phpversion(), '5.6', '>=' ) ) {
				return file_get_contents( 'php://input' );
			}

			global $HTTP_RAW_POST_DATA;

			// A bug in PHP < 5.2.2 makes $HTTP_RAW_POST_DATA not set by default,
			// but we can do it ourself.
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
					if ( $this->debug_log() ) {
						$this->log( "Processed Viva payment for order #" . $order->id . ". Waiting for IPN to complete order. Request details: " . print_r( $_GET, true ) );
					}
					$redirect = $this->get_return_url( $order );
				} else {
					if ( $this->debug_log() ) {
						$this->log( "Error: Processed Viva payment for unknown order. Possible fraudulent attempt. Request details: " . print_r( $_GET, true ) );
					}
					$redirect = $this->get_return_url();
				}

			} else {

				// Failed payment.
				if ( $order ) {
					if ( $this->debug_log() ) {
						$this->log( "Viva payment for order #" . $order->id . " failed. Redirecting user to checkout order. Request details: " . print_r( $_GET, true ) );
					}
					$redirect = esc_url( add_query_arg( array( 'result' => 'failure', 'failed_viva_order_code' => $order_code ), $order->get_checkout_payment_url() ) );
				} else {
					if ( $this->debug_log() ) {
						$this->log( "Error: Viva payment for an unknown order failed. Possible fraudulent attempt. Request details: " . print_r( $_GET, true ) );
					}
					$redirect = wc_get_checkout_url();
				}
			}

			wp_safe_redirect( $redirect );
			exit;
		}

		/**
		 * Respond to payment confirmation & IPN requests.
		 *
		 * @return void
		 */
		private function ipn_handler() {

			$request_content = $this->get_raw_data();

			if ( empty( $request_content ) ) {

				$args = array(
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . $this->api_key )
					),
				);

				$response = wp_safe_remote_get( $this->endpoint . '/api/messages/config/token', $args );

				if ( $this->debug_log() ) {
					$this->log( "Viva IPN Verification Response: " . print_r( $response, true ) );
				}

				$data = wp_remote_retrieve_body( $response );

				echo $data;
				exit;
			}

			$message_content = (array) json_decode( $request_content );
			$message_code    = (int) $message_content[ 'EventTypeId' ];
			$event_data      = (array) $message_content[ 'EventData' ];

			if ( $this->debug_log() ) {
				$this->log( "Viva IPN Received: " . print_r( $message_content, true ) );
			}

			if ( self::IPN_CODE_TRANSACTION_CREATED === $message_code ) {

				// Find the order.
				$order_id = $event_data[ 'MerchantTrns' ];
				$order    = wc_get_order( $order_id );

				if ( ! $order ) {
					if ( $this->debug_log() ) {
						$this->log( "Order not found." );
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
						$order->add_order_note( sprintf( __( 'Viva Wallet payment notification received via IPN (transaction type: &quot;%1$s&quot;, paid by: %2$s, transaction ID: %3$s).', 'woocommerce-gateway-viva' ), $this->ipn_transaction_types[ $message_code ][ $event_data[ 'TransactionTypeId' ] ], $event_data[ 'Email' ], $event_data[ 'TransactionId' ] ) );
						// Mark order as completed.
						$order->payment_complete();
					} else {
						$order->add_order_note( sprintf( __( 'Viva Wallet payment notification received via IPN. Payment code (%1$s) invalid (paid by: %2$s, transaction ID: %3$s).', 'woocommerce-gateway-viva' ), $event_data[ 'TransactionTypeId' ], $event_data[ 'Email' ], $event_data[ 'TransactionId' ] ) );
					}
				} else {
					$order->add_order_note( sprintf( __( 'Invalid Viva Wallet payment notification received via IPN - possible fraudulent order attempt (transaction ID: %s).', 'woocommerce-gateway-viva' ), $event_data[ 'TransactionId' ] ) );
				}

			} elseif ( self::IPN_CODE_TRANSACTION_REVERSED === $message_code ) {

				// Find the order.
				$order_id = $event_data[ 'MerchantTrns' ];
				$order    = $order_id ? wc_get_order( $order_id ) : $this->get_order_id_from_viva_code( $event_data[ 'OrderCode' ] );

				if ( ! $order ) {
					if ( $this->debug_log() ) {
						$this->log( "Order not found." );
					}
					return;
				}

				// Validate the event.
				$ipn_valid = $this->validate_ipn( $message_content );

				if ( $ipn_valid ) {
					// Only handle full refunds, not partial.
					if ( $order->get_total() == ( $event_data[ 'Amount' ] * -1 ) ) {
						$order->add_order_note( sprintf( __( 'Viva Wallet refund notification received via IPN (transaction type: &quot;%1$s&quot;, transaction ID: %2$s).', 'woocommerce-gateway-viva' ), $this->ipn_transaction_types[ $message_code ][ $event_data[ 'TransactionTypeId' ] ], $event_data[ 'TransactionId' ] ) );
						// Mark order as refunded.
						$order->update_status( 'refunded' );
					} else {
						$order->add_order_note( sprintf( __( 'Viva Wallet partial refund notification received via IPN (amount: %1$s, transaction type: %2$s, transaction ID: %3$s).', 'woocommerce-gateway-viva' ), $event_data[ 'Amount' ], $this->ipn_transaction_types[ $event_data[ 'TransactionTypeId' ] ], $event_data[ 'TransactionId' ] ) );
					}
				} else {
					$order->add_order_note( sprintf( __( 'Invalid Viva Wallet refund notification received via IPN - possible fraudulent attempt (transaction ID: %s).', 'woocommerce-gateway-viva' ), $event_data[ 'TransactionId' ] ) );
				}
			}
		}

		/**
		 * Double-check IPN validity by making a Viva API call.
		 *
		 * @since  1.0.0
		 * @param  array $message
		 */
		private function validate_ipn( $message ) {

			return true;
		}

		/**
		 * Get the order id that corresponds to a given viva order number.
		 *
		 * @since  1.0.0
		 * @param  string $code
		 * @return string
		 */
		public function get_order_id_from_viva_code( $code ) {

			// Find the order from the saved viva order code.
			$args = array(
				'post_type'            => 'shop_order',
				'post_status'          => wc_get_order_statuses(),
				'fields'               => 'ids',
				'meta_query'           => array( array(
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
		 * @since  1.0.0
		 * @return string
		 */
		public function get_id() {
			return $this->id;
		}

		/**
		 * True if debug logging is enabled.
		 *
		 * @since 1.0.0
		 * @return boolean
		 */
		public function debug_log() {
			return self::DEBUG_MODE_LOG === $this->debug_mode;
		}

		/**
		 * Saves errors or messages to WooCommerce Log (woocommerce/logs/plugin-id-xxx.txt).
		 *
		 * @since 1.0.0
		 * @param string $message
		 * @param string $log_id
		 */
		public function log( $message, $log_id = null ) {

			if ( is_null( $log_id ) ) {
				$log_id = $this->get_id();
			}

			if ( ! is_object( $this->logger ) ) {
				$this->logger = new WC_Logger();
			}

			$this->logger->add( $log_id, $message );
		}
	}

	// Add Gateway to WooCommerce.
	function wc_viva_add_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Viva';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'wc_viva_add_gateway' );
}
