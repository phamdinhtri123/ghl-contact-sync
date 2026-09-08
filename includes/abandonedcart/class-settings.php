<?php
/**
 * Abandoned cart settings helper.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\AbandonedCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads normalized abandoned cart settings from the shared plugin option.
 */
final class Settings {

	/**
	 * Default module settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'                  => 0,
			'abandoned_after_minutes'  => 60,
			'track_guest_carts'        => 1,
			'track_logged_in_carts'    => 1,
			'minimum_cart_total'       => 0,
			'ghl_sync_enabled'         => 1,
			'abandoned_cart_tag'       => 'abandoned-cart',
			'field_mapping'            => array(),
			'recovery_expires_days'    => 7,
			'recovery_redirect'        => 'checkout',
			'recovery_cart_behavior'   => 'merge',
			'anonymous_retention_days' => 14,
			'abandoned_retention_days' => 90,
			'recovered_retention_days' => 90,
			'logs_retention_days'      => 30,
		);
	}

	/**
	 * Get normalized settings.
	 *
	 * @return array
	 */
	public static function get() {
		$settings = get_option( 'ghlcs_settings', array() );
		$cart     = isset( $settings['abandoned_cart'] ) && is_array( $settings['abandoned_cart'] ) ? $settings['abandoned_cart'] : array();

		return wp_parse_args( $cart, self::defaults() );
	}

	/**
	 * Save module settings into the shared plugin option.
	 *
	 * @param array $cart_settings Module settings.
	 * @return void
	 */
	public static function save( array $cart_settings ) {
		$settings                   = get_option( 'ghlcs_settings', array() );
		$settings['abandoned_cart'] = wp_parse_args( $cart_settings, self::defaults() );

		update_option( 'ghlcs_settings', $settings, false );
	}
}
