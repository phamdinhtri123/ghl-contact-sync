<?php
/**
 * Abandoned cart background jobs.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\AbandonedCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedules idempotent background work with Action Scheduler when available.
 */
final class Background_Jobs {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'init', array( $this, 'ensure_schedules' ) );
		add_action( 'ghlcs_abandoned_cart_detect', array( $this, 'detect' ) );
		add_action( 'ghlcs_abandoned_cart_cleanup', array( $this, 'cleanup' ) );
		add_action( 'ghlcs_ac_sync_abandoned', array( $this, 'sync_abandoned' ), 10, 3 );
		add_action( 'ghlcs_ac_remove_tag', array( $this, 'remove_tag' ), 10, 2 );
		add_action( 'ghlcs_ac_sync_status', array( $this, 'sync_status' ), 10, 2 );
	}

	/**
	 * Ensure recurring schedules exist.
	 *
	 * @return void
	 */
	public function ensure_schedules() {
		$settings = Settings::get();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			if ( ! as_next_scheduled_action( 'ghlcs_abandoned_cart_detect' ) ) {
				as_schedule_recurring_action( time() + 60, 60, 'ghlcs_abandoned_cart_detect', array(), 'ghl-contact-sync' );
			}
			if ( ! as_next_scheduled_action( 'ghlcs_abandoned_cart_cleanup' ) ) {
				as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, 'ghlcs_abandoned_cart_cleanup', array(), 'ghl-contact-sync' );
			}
			return;
		}

		if ( 'ghlcs_every_minute' !== wp_get_schedule( 'ghlcs_abandoned_cart_detect' ) ) {
			wp_clear_scheduled_hook( 'ghlcs_abandoned_cart_detect' );
			wp_schedule_event( time() + 60, 'ghlcs_every_minute', 'ghlcs_abandoned_cart_detect' );
		}

		if ( ! wp_next_scheduled( 'ghlcs_abandoned_cart_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ghlcs_abandoned_cart_cleanup' );
		}
	}

	/**
	 * Add a minute interval for abandoned cart detection.
	 *
	 * @param array $schedules Cron schedules.
	 * @return array
	 */
	public function cron_schedules( $schedules ) {
		$schedules['ghlcs_every_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute', 'ghl-contact-sync' ),
		);

		return $schedules;
	}

	/**
	 * Enqueue a unique-ish async action.
	 *
	 * @param string $hook Hook.
	 * @param array  $args Args.
	 * @param int    $delay Delay seconds.
	 * @return void
	 */
	public static function enqueue_unique( $hook, array $args, $delay = 0 ) {
		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, $args, 'ghl-contact-sync' ) ) {
			return;
		}

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + max( 0, (int) $delay ), $hook, $args, 'ghl-contact-sync' );
			return;
		}

		if ( ! wp_next_scheduled( $hook, $args ) ) {
			wp_schedule_single_event( time() + max( 0, (int) $delay ), $hook, $args );
		}
	}

	/**
	 * Detect newly abandoned carts.
	 *
	 * @return void
	 */
	public function detect() {
		$settings   = Settings::get();
		$repository = new Cart_Repository();
		$service    = new Abandonment_Service( $repository );

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		foreach ( $repository->due_for_abandonment( $settings['abandoned_after_minutes'], $settings['minimum_cart_total'] ) as $cart ) {
			$service->mark_abandoned( $cart );
		}
	}

	/**
	 * Cleanup old data.
	 *
	 * @return void
	 */
	public function cleanup() {
		( new Cart_Repository() )->cleanup( Settings::get() );
	}

	/**
	 * Sync abandoned cart data then add tag.
	 *
	 * @param int    $cart_id Cart ID.
	 * @param int    $cycle Cycle number.
	 * @param string $recovery_token Raw recovery token.
	 * @return void
	 */
	public function sync_abandoned( $cart_id, $cycle, $recovery_token ) {
		( new GHL_Cart_Sync_Service( new Cart_Repository() ) )->sync_abandoned( (int) $cart_id, (int) $cycle, (string) $recovery_token );
	}

	/**
	 * Remove abandoned cart tag.
	 *
	 * @param int $cart_id Cart ID.
	 * @param int $cycle Cycle.
	 * @return void
	 */
	public function remove_tag( $cart_id, $cycle ) {
		( new GHL_Cart_Sync_Service( new Cart_Repository() ) )->remove_abandoned_tag( (int) $cart_id, (int) $cycle );
	}

	/**
	 * Sync current status/custom fields without adding the automation tag.
	 *
	 * @param int    $cart_id Cart ID.
	 * @param string $status Status.
	 * @return void
	 */
	public function sync_status( $cart_id, $status ) {
		( new GHL_Cart_Sync_Service( new Cart_Repository() ) )->sync_status( (int) $cart_id, sanitize_key( $status ) );
	}
}
