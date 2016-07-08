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
	 * Make the WC_Gateway_Viva class available.
	 */
	public static function includes() {

		// Sanity check.
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		require_once( 'includes/class-wc-gateway-viva.php' );
	}

	/**
	 * Load domain translations.
	 */
	public static function load_translations() {
		load_plugin_textdomain( 'woocommerce-gateway-viva', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages' );
	}
}

WC_Viva::init();
