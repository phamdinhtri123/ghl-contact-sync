<?php
/**
 * Abandonment state transitions.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\AbandonedCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns cart abandonment transitions.
 */
final class Abandonment_Service {

	/**
	 * Repository.
	 *
	 * @var Cart_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param Cart_Repository $repository Repository.
	 */
	public function __construct( Cart_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Mark one cart abandoned once for the current cycle.
	 *
	 * @param array $cart Cart row.
	 * @return void
	 */
	public function mark_abandoned( array $cart ) {
		if ( 'active' !== $cart['status'] || empty( $cart['email'] ) || empty( $cart['item_count'] ) ) {
			return;
		}

		$settings = Settings::get();
		$token    = $cart['cart_uuid'] . '.' . wp_generate_password( 48, false, false );
		$cycle    = (int) $cart['abandonment_cycle'] + 1;
		$expires  = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( max( 1, (int) $settings['recovery_expires_days'] ) * DAY_IN_SECONDS ) );

		$this->repository->update(
			$cart['id'],
			array(
				'status'               => 'abandoned',
				'abandoned_at'         => current_time( 'mysql' ),
				'abandonment_cycle'    => $cycle,
				'recovery_token_hash'  => wp_hash_password( $token ),
				'recovery_expires_at'  => $expires,
				'ghl_sync_status'      => 'pending',
				'last_error'           => '',
			)
		);

		$this->repository->add_log( $cart['id'], 'info', 'cart_abandoned', __( 'Cart marked abandoned.', 'ghl-contact-sync' ) );

		if ( ! empty( $settings['ghl_sync_enabled'] ) ) {
			Background_Jobs::enqueue_unique( 'ghlcs_ac_sync_abandoned', array( (int) $cart['id'], $cycle, $token ), 0 );
		}
	}
}
