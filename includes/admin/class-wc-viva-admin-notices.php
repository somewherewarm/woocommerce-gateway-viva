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
	 * Add admin notices hooks.
	 */
	public static function init() {

		// Show dismissible notice if the gateway is enabled and the shop base currency is other than EUR.
		add_filter( 'admin_init', array( __CLASS__, 'check_shop_currency' ) );

		// Act upon clicking on a 'dismiss notice' link.
		add_action( 'wp_loaded', array( __CLASS__, 'dismiss_notice_handler' ) );
	}

	/**
	 * Show dismissible notice if the gateway is enabled and the shop base currency is other than EUR:
	 * Viva Wallet only supports EUR.
	 */
	public static function check_shop_currency() {

		$gateway_settings = get_option( 'woocommerce_viva_settings', false );

		if ( $gateway_settings && isset( $gateway_settings[ 'enabled' ] ) && $gateway_settings[ 'enabled' ] === 'yes' ) {
			if ( ! self::is_notice_dismissed( 'unsupported_currency_notice' ) ) {
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
			$dismiss     = '<a class="dismiss-permanently" href="' . $dismiss_url . '">' . __( 'dimiss this message permanently', 'woocommerce-gateway-viva' ) . '</a>';
			$notice      = sprintf( __( 'The <strong>WooCommerce Viva Wallet</strong> gateway only supports payments in <strong>Euros (%1$s)</strong>. The gateway will be unavailable when checking out in any other currency. If you are using a WooCommerce plugin that enables checkout in multiple currencies, you can %2$s.', 'woocommerce-gateway-viva' ), get_woocommerce_currency_symbol( 'EUR' ), $dismiss );
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
	public static function is_notice_dismissed( $notice_name ) {
		$viva_notices_status = get_option( 'wc_viva_notices_status', array() );
		$notice_hidden       = isset( $viva_notices_status[ $notice_name ] ) && 'hidden' === $viva_notices_status[ $notice_name ];
		return $notice_hidden;
	}
}

WC_Viva_Admin_Notices::init();
