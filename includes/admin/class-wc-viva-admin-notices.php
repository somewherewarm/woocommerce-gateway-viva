<?php
/**
 * WC_Viva_Admin_Notices class
 *
 * @author   SomewhereWarm <sw@somewherewarm.net>
 * @package  WooCommerce Viva Wallet Payment Gateway
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Viva_Admin_Notices class.
 *
 * @class WC_Viva_Admin_Notices
 */
class WC_Viva_Admin_Notices {

	/**
	 * Gateway settings.
	 * @var mixed
	 */
	private static $gateway_settings;

	/**
	 * Flag to indicate if the gateway configuration is valid.
	 * @var boolean
	 */
	public static $is_gateway_configuration_valid;

	/**
	 * Gateway configuration error code - @see WC_Gateway_Viva::process_admin_options.
	 * @var int
	 */
	public static $gateway_configuration_error_code;

	/**
	 * Add admin notices hooks.
	 */
	public static function init() {

		// Check gateway/shop settings and show notices.
		add_filter( 'admin_init', array( __CLASS__, 'check_settings' ) );

		// Act upon clicking on a 'dismiss notice' link.
		add_action( 'wp_loaded', array( __CLASS__, 'dismiss_notice_handler' ) );
	}

	/**
	 * Check gateway/shop settings and show notices if required.
	 */
	public static function check_settings() {

		self::$gateway_settings               = get_option( 'woocommerce_viva_settings', false );
		self::$is_gateway_configuration_valid = 'yes' === get_option( 'wc_viva_settings_validated', false );

		// Show dismissible notice if the gateway is enabled and the shop base currency is other than EUR.
		self::check_shop_currency();
		// Show dismissible notice if the gateway settings are not configured correctly.
		self::check_gateway_settings();
	}

	/**
	 * Check gateway settings before WC_GatewayViva::process_admin_options has run, validate creds and show notices if the gateway is enabled.
	 */
	private static function check_gateway_settings() {

		// When viewing the Viva Wallet gateway settings...
		if ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] === 'wc-settings' && isset( $_GET[ 'tab' ] ) && $_GET[ 'tab' ] === 'checkout' && isset( $_GET[ 'section' ] ) && $_GET[ 'section' ] === 'viva' ) {

			// When posting data...
			if ( ! empty( $_POST ) ) {

				// If the gateway is enabled...
				if ( ! empty( $_POST[ 'woocommerce_viva_enabled' ] ) ) {

					// Error condition: creds not set.
					if ( empty( $_POST[ 'woocommerce_viva_merchant_id' ] ) || empty( $_POST[ 'woocommerce_viva_api_key' ] ) || empty( $_POST[ 'woocommerce_viva_source_code' ] ) ) {

						self::$gateway_configuration_error_code = 1;

					// Otherwise, validate by issuing a token request.
					} else {

						$gateways = WC()->payment_gateways->payment_gateways();
						$gateway  = $gateways[ 'viva' ];

						$merchant_id = wc_clean( $_POST[ 'woocommerce_viva_merchant_id' ] );
						$api_key     = wc_clean( $_POST[ 'woocommerce_viva_api_key' ] );

						if ( 'log' === wc_clean( $_POST[ 'woocommerce_viva_debug_mode' ] ) ) {
							$gateway->log( "Validating Viva settings..." );
						}

						// Send token request with the new creds.
						$data = (array) json_decode( $gateway->request_config_token( $merchant_id, $api_key ) );

						// Error condition: creds invalid.
						if ( ! isset( $data[ 'Key' ] ) ) {
							self::$gateway_configuration_error_code = 2;
						// Creds valid.
						} else {
							self::$gateway_configuration_error_code = 0;
						}
					}

					if ( self::$gateway_configuration_error_code !== 0 ) {
						WC_Viva_Admin_Notices::$is_gateway_configuration_valid = false;
						delete_option( 'wc_viva_settings_validated' );
						add_action( 'admin_notices', array( __CLASS__, 'output_configuration_notice' ) );
					} else {
						WC_Viva_Admin_Notices::$is_gateway_configuration_valid = true;
						update_option( 'wc_viva_settings_validated', 'yes' );
						// Show notice if the gateway is enabled and configured but IPN functionality has not been validated in the Viva Webhooks settings.
						self::check_gateway_ipn_validated( $data[ 'Key' ] );
					}
				}
			}

		// Otherwise show an admin notice if the gateway is enabled but not set up correctly.
		} elseif ( ! self::is_notice_dismissed( 'gateway_configuration_notice' ) ) {
			if ( self::is_gateway_enabled() ) {
				if ( ! self::$is_gateway_configuration_valid ) {
					add_action( 'admin_notices', array( __CLASS__, 'output_configuration_notice' ) );
				}
			}
		}
	}

	/**
	 * Show notice if the gateway is enabled and configured but IPN functionality has not been validated in the Viva Webhooks settings.
	 * If validated, a Viva token will be present and equal to the one produced by a token obtained with the saved creds.
	 */
	private static function check_gateway_ipn_validated( $token ) {
		$ipn_validated = get_option( 'wc_viva_ipn_validated', false );

		if ( md5( $token ) !== $ipn_validated ) {
			add_action( 'admin_notices', array( __CLASS__, 'output_ipn_validation_notice' ) );
		}
	}

	/**
	 * Show dismissible notice if the gateway is enabled and the shop base currency is other than EUR:
	 * Viva Wallet only supports EUR.
	 */
	private static function check_shop_currency() {
		if ( ! self::is_notice_dismissed( 'unsupported_currency_notice' ) ) {
			if ( self::is_gateway_enabled() ) {
				$currency = isset( $_POST[ 'woocommerce_currency' ] ) ? wc_clean( $_POST[ 'woocommerce_currency' ] ) : get_woocommerce_currency();
				if ( 'EUR' !== $currency ) {
					add_action( 'admin_notices', array( __CLASS__, 'output_unsupported_currency_notice' ) );
				}
			}
		}
	}

	/**
	 * Output a notice to indicate that a currency other than EUR is selected:
	 * Viva Wallet only supports EUR.
	 */
	public static function output_unsupported_currency_notice() {
		?><div id="message" class="notice notice-warning wc-viva-notice is-dismissible">
			<?php
			$dismiss_url = esc_url( wp_nonce_url( add_query_arg( 'wc_viva_dismiss_notice', 'unsupported_currency_notice' ), 'wc_viva_dismiss_notice_nonce', '_wc_notice_nonce' ) );
			$dismiss     = '<a class="dismiss-permanently" href="' . $dismiss_url . '">' . __( 'dimiss this message', 'woocommerce-gateway-viva' ) . '</a>';
			$notice      = sprintf( __( 'The <strong>WooCommerce Viva Wallet</strong> gateway only supports payments in <strong>Euros (%1$s)</strong>. The gateway will be unavailable when checking out in any other currency. If you are using a WooCommerce plugin that enables checkout in multiple currencies, you may %2$s.', 'woocommerce-gateway-viva' ), get_woocommerce_currency_symbol( 'EUR' ), $dismiss );
			echo wp_kses_post( wpautop( $notice ) );
			?>
		</div><?php
	}

	/**
	 * Output a notice to indicate that the gateway is not fully configured.
	 */
	public static function output_configuration_notice() {
		?><div id="message" class="notice notice-warning wc-viva-notice is-dismissible">
			<?php
			$settings_url = esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=viva' ) );
			$docs_url     = WC_Viva::DOCS_URL;
			$notice       = sprintf( __( 'The <strong>WooCommerce Viva Wallet</strong> gateway requires a valid <strong>Merchant ID</strong>, <strong>API Key</strong> and <strong>Payment Source</strong> in order to process payments sucessfully. Please review your Viva Wallet <a href="%s">gateway settings</a>. For instructions, please refer to the Viva Wallet <a href="%s">documentation</a>.', 'woocommerce-gateway-viva' ), $settings_url, $docs_url );
			echo wp_kses_post( wpautop( $notice ) );
			?>
		</div><?php
	}

	/**
	 * Output a notice to indicate that our IPN endpoint has never been validated.
	 */
	public static function output_ipn_validation_notice() {
		?><div id="message" class="notice notice-warning wc-viva-notice is-dismissible">
			<?php
			$docs_url     = WC_Viva::DOCS_URL;
			$notice       = sprintf( __( 'Please ensure that Viva Wallet <strong>Webhooks</strong> have been set up correctly by logging into your Viva merchant account and navigating to <strong>Settings > API Access > Webhooks</strong>. Webhooks must be configured to allow your store to receive notifications in response to various events, such as completed order payments and refunds. For instructions, please refer to the Viva Wallet <a href="%s">documentation</a>.', 'woocommerce-gateway-viva' ), $docs_url );
			echo wp_kses_post( wpautop( $notice ) );
			?>
		</div><?php
	}

	/**
	 * Act upon clicking on a 'dismiss notice' link.
	 */
	public static function dismiss_notice_handler() {
		if ( isset( $_GET[ 'wc_viva_dismiss_notice' ] ) && isset( $_GET[ '_wc_notice_nonce' ] ) ) {
			if ( ! wp_verify_nonce( $_GET[ '_wc_notice_nonce' ], 'wc_viva_dismiss_notice_nonce' ) ) {
				wp_die( __( 'Action failed. Please refresh the page and retry.', 'woocommerce' ) );
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce' ) );
			}

			$notice = sanitize_text_field( $_GET[ 'wc_viva_dismiss_notice' ] );
			self::dismiss_notice( $notice );
		}
	}

	/**
	 * Set a notice status as hidden.
	 *
	 * @param  string $notice_name
	 * @return void
	 */
	public static function dismiss_notice( $notice_name ) {
		$viva_notices_status = get_option( 'wc_viva_notices_status', array() );
		if ( ! isset( $viva_notices_status[ $notice_name ] ) ) {
			$viva_notices_status[ $notice_name ] = 'hidden';
		}
		update_option( 'wc_viva_notices_status', $viva_notices_status );
	}

	/**
	 * Checks whether a notice is dismissed.
	 *
	 * @param  string  $notice_name
	 * @return boolean
	 */
	private static function is_notice_dismissed( $notice_name ) {
		$viva_notices_status = get_option( 'wc_viva_notices_status', array() );
		$notice_hidden       = isset( $viva_notices_status[ $notice_name ] ) && 'hidden' === $viva_notices_status[ $notice_name ];
		return $notice_hidden;
	}

	/**
	 * Check if the gateway is enabled without instantiating its class.
	 *
	 * @return boolean
	 */
	private static function is_gateway_enabled() {
		return self::$gateway_settings && isset( self::$gateway_settings[ 'enabled' ] ) && self::$gateway_settings[ 'enabled' ] === 'yes';
	}
}

WC_Viva_Admin_Notices::init();
