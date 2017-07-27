<?php
/**
 * WCS_Viva_Core_Compatibility class
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
 * WC Core compatibility functions.
 *
 * @class    WCS_ATT_Core_Compatibility
 * @version  1.0.0
 */
class WCS_Viva_Core_Compatibility {

	/**
	 * Cache 'gte' comparison results.
	 * @var array
	 */
	private static $is_wc_version_gte = array();

	/**
	 * Cache 'gt' comparison results.
	 * @var array
	 */
	private static $is_wc_version_gt = array();

	/*
	|--------------------------------------------------------------------------
	| WC version getters.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Helper method to get the version of the currently installed WooCommerce
	 *
	 * @since  1.0.0
	 * @return string woocommerce version number or null
	 */
	private static function get_wc_version() {
		return defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;
	}

	/**
	 * Returns true if the installed version of WooCommerce is greater than or equal to $version.
	 *
	 * @since  1.0.0
	 *
	 * @param  string  $version
	 * @return boolean
	 */
	public static function is_wc_version_gte( $version ) {
		if ( ! isset( self::$is_wc_version_gte[ $version ] ) ) {
			self::$is_wc_version_gte[ $version ] = self::get_wc_version() && version_compare( self::get_wc_version(), $version, '>=' );
		}
		return self::$is_wc_version_gte[ $version ];
	}

	/**
	 * Returns true if the installed version of WooCommerce is greater than $version.
	 *
	 * @since  1.0.0
	 *
	 * @param  string  $version
	 * @return boolean
	 */
	public static function is_wc_version_gt( $version ) {
		if ( ! isset( self::$is_wc_version_gt[ $version ] ) ) {
			self::$is_wc_version_gt[ $version ] = self::get_wc_version() && version_compare( self::get_wc_version(), $version, '>' );
		}
		return self::$is_wc_version_gt[ $version ];
	}

	/**
	 * Backwards compatible logging using 'WC_Logger' class.
	 *
	 * @since  1.0.0
	 *
	 * @param  string  $message
	 * @param  string  $level
	 * @param  string  $context
	 */
	public static function log( $message, $level, $context ) {
		if ( self::is_wc_version_gte( '2.7' ) ) {
			$logger = wc_get_logger();
			$logger->log( $level, $message, array( 'source' => $context ) );
		} else {
			$logger = new WC_Logger();
			$logger->add( $context, $message );
		}
	}
}
