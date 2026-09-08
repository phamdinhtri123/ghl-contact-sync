<?php
/**
 * Deactivation routines.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin deactivation.
 */
final class Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'ghlcs_abandoned_cart_detect' );
		wp_clear_scheduled_hook( 'ghlcs_abandoned_cart_cleanup' );
		// Keep forms, submissions, logs, and settings for reactivation.
	}
}
