<?php
/**
 * Abandonment state transitions.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\AbandonedCart;

use GHLContactSync\Security\Token_Encryption;

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
	 * @param bool  $enqueue_sync Whether to enqueue GHL sync.
	 * @return array|null
	 */
	public function mark_abandoned( array $cart, $enqueue_sync = true ) {
		if ( 'active' !== $cart['status'] || empty( $cart['email'] ) || empty( $cart['item_count'] ) ) {
			return null;
		}

		$settings = Settings::get();
		$token    = $this->recovery_token( $cart );
		$cycle    = (int) $cart['abandonment_cycle'] + 1;
		$expires  = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( max( 1, (int) $settings['recovery_expires_days'] ) * DAY_IN_SECONDS ) );
		$encrypted_token = Token_Encryption::encrypt_token( $token );

		$this->repository->update(
			$cart['id'],
			array(
				'status'               => 'abandoned',
				'abandoned_at'         => current_time( 'mysql' ),
				'abandonment_cycle'    => $cycle,
				'recovery_token_hash'  => wp_hash_password( $token ),
				'recovery_token_encrypted' => is_wp_error( $encrypted_token ) ? '' : $encrypted_token,
				'recovery_expires_at'  => $expires,
				'ghl_sync_status'      => 'pending',
				'last_error'           => '',
			)
		);

		$this->repository->add_log( $cart['id'], 'info', 'cart_abandoned', __( 'Cart marked abandoned.', 'ghl-contact-sync' ) );

		if ( $enqueue_sync && ! empty( $settings['ghl_sync_enabled'] ) ) {
			Background_Jobs::enqueue_unique( 'ghlcs_ac_sync_abandoned', array( (int) $cart['id'], $cycle, $token ), 0 );
		}

		return array(
			'cart_id' => (int) $cart['id'],
			'cycle'   => $cycle,
			'token'   => $token,
		);
	}

	/**
	 * Reuse an unexpired recovery token for the same cart when available.
	 *
	 * @param array $cart Cart row.
	 * @return string
	 */
	private function recovery_token( array $cart ) {
		if ( ! empty( $cart['recovery_token_encrypted'] ) && ! empty( $cart['recovery_expires_at'] ) && $cart['recovery_expires_at'] >= current_time( 'mysql' ) ) {
			$token = Token_Encryption::decrypt_token( $cart['recovery_token_encrypted'] );

			if ( ! is_wp_error( $token ) && '' !== $token ) {
				return $token;
			}
		}

		return $cart['cart_uuid'] . '.' . wp_generate_password( 48, false, false );
	}
}
