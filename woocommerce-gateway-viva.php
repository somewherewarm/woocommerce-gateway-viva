<?php
/**
 * Plugin Name: WooCommerce Viva Wallet Gateway
 * Plugin URI: http://somewherewarm.gr/
 * Description: Adds the Viva Wallet payment gateway to your WooCommerce website. A secure, clean implementation of the Redirect Checkout method.
 * Version: 1.0.0
 *
 * Author: SomewhereWarm
 * Author URI: http://somewherewarm.gr/
 *
 * Text Domain: woocommerce-gateway-viva
 * Domain Path: /i18n/languages/
 *
 * Requires at least: 4.2
 * Tested up to: 4.8
 *
 * Copyright: © 2009-2017 SomewhereWarm SMPC.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Required functions.
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

/*
 * Plugin updates.
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '', '' );

/*
 * WC active check.
 */
if ( ! is_woocommerce_active() ) {
	return;
}

/**
 * WC Viva Wallet gateway plugin class.
 *
 * @class WC_Viva
 */
class WC_Viva {

	/* Plugin version. */
	const VERSION = '1.0.0';

	/* Required WC version. */
	const REQ_WC_VERSION = '2.3.0';

	/* Docs URL. */
	const DOCS_URL = 'https://github.com/somewherewarm/woocommerce-gateway-viva/';

	/**
	 * Plugin bootstrapping.
	 */
	public static function init() {

		// Viva Wallet gateway class.
		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 0 );

		// Plugin localization.
		add_action( 'init', array( __CLASS__, 'load_translations' ) );

		// Make the Viva Wallet gateway available to WC.
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );

		// Clean up.
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate_plugin' ) );
	}

	/**
	 * Add the Viva Wallet gateway to the list of available gateways.
	 *
	 * @param array
	 */
	public static function add_gateway( $gateways ) {
		$gateways[] = 'WC_Gateway_Viva';
		return $gateways;
	}

	/**
	 * Plugin includes.
	 */
	public static function includes() {

		// Compatibility functions.
		require_once( 'includes/class-wc-viva-compatibility.php' );

		if ( WC_Viva_Core_Compatibility::is_wc_version_gte( self::REQ_WC_VERSION ) ) {

			// Make the WC_Gateway_Viva class available.
			if ( class_exists( 'WC_Payment_Gateway' ) ) {
				require_once( 'includes/class-wc-gateway-viva.php' );
			}

			// Admin notices.
			if ( is_admin() ) {
				require_once( 'includes/admin/class-wc-viva-admin-notices.php' );
			}
		}
	}

	/**
	 * Load domain translations.
	 */
	public static function load_translations() {
		load_plugin_textdomain( 'woocommerce-gateway-viva', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages' );
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Delete plugin options on deactivation.
	 */
	public static function deactivate_plugin() {
		delete_option( 'wc_viva_notices_status' );
	}
}

WC_Viva::init();
