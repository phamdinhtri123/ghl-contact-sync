<?php
/**
 * GoHighLevel cart sync.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\AbandonedCart;

use GHLContactSync\GHL\GHL_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Syncs identified carts to GHL and manages the automation tag.
 */
final class GHL_Cart_Sync_Service {

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
	 * Sync abandoned cart data and add the configured tag last.
	 *
	 * @param int    $cart_id Cart ID.
	 * @param int    $cycle Abandonment cycle.
	 * @param string $recovery_token Raw recovery token for URL generation.
	 * @return void
	 */
	public function sync_abandoned( $cart_id, $cycle, $recovery_token ) {
		$cart = $this->repository->get( $cart_id );

		if ( ! $cart || 'abandoned' !== $cart['status'] || (int) $cart['abandonment_cycle'] !== (int) $cycle ) {
			return;
		}

		$result = $this->sync_fields( $cart, $recovery_token );

		if ( is_wp_error( $result ) ) {
			$this->mark_failed( $cart_id, $result->get_error_message(), 'sync_failed' );
			$this->retry( 'ghlcs_ac_sync_abandoned', array( (int) $cart_id, (int) $cycle, (string) $recovery_token ), $cart );
			return;
		}

		$cart = $this->repository->get( $cart_id );
		$tag  = trim( Settings::get()['abandoned_cart_tag'] );

		if ( '' !== $tag && ! empty( $cart['ghl_contact_id'] ) ) {
			$client = new GHL_Client( get_option( 'ghlcs_settings', array() ) );
			$added  = $client->add_contact_tags( $cart['ghl_contact_id'], array( $tag ) );

			if ( is_wp_error( $added ) || empty( $added['success'] ) ) {
				$message = is_wp_error( $added ) ? $added->get_error_message() : ( $added['message'] ?? __( 'Could not add abandoned cart tag.', 'ghl-contact-sync' ) );
				$this->mark_failed( $cart_id, $message, 'tag_add_failed' );
				$this->retry( 'ghlcs_ac_sync_abandoned', array( (int) $cart_id, (int) $cycle, (string) $recovery_token ), $cart );
				return;
			}

			$this->repository->add_log( $cart_id, 'info', 'tag_added', sprintf( 'Tag "%s" added.', sanitize_text_field( $tag ) ) );
		}

		$this->repository->update(
			$cart_id,
			array(
				'ghl_sync_status' => 'synced',
				'ghl_synced_at'   => current_time( 'mysql' ),
				'last_error'      => '',
			)
		);
	}

	/**
	 * Sync a status change without adding the abandoned tag.
	 *
	 * @param int    $cart_id Cart ID.
	 * @param string $status Status.
	 * @return void
	 */
	public function sync_status( $cart_id, $status ) {
		$cart = $this->repository->get( $cart_id );

		if ( ! $cart || empty( $cart['email'] ) ) {
			return;
		}

		$cart['status'] = $status;
		$result         = $this->sync_fields( $cart, '' );

		if ( is_wp_error( $result ) ) {
			$this->mark_failed( $cart_id, $result->get_error_message(), 'status_sync_failed' );
		}
	}

	/**
	 * Remove only the configured abandoned cart tag.
	 *
	 * @param int $cart_id Cart ID.
	 * @param int $cycle Cycle.
	 * @return void
	 */
	public function remove_abandoned_tag( $cart_id, $cycle ) {
		$cart = $this->repository->get( $cart_id );

		if ( ! $cart || empty( $cart['ghl_contact_id'] ) ) {
			return;
		}

		$tag = trim( Settings::get()['abandoned_cart_tag'] );

		if ( '' === $tag ) {
			return;
		}

		$client  = new GHL_Client( get_option( 'ghlcs_settings', array() ) );
		$removed = $client->remove_contact_tags( $cart['ghl_contact_id'], array( $tag ) );

		if ( is_wp_error( $removed ) || empty( $removed['success'] ) ) {
			$message = is_wp_error( $removed ) ? $removed->get_error_message() : ( $removed['message'] ?? __( 'Could not remove abandoned cart tag.', 'ghl-contact-sync' ) );
			$this->mark_failed( $cart_id, $message, 'tag_remove_failed' );
			$this->retry( 'ghlcs_ac_remove_tag', array( (int) $cart_id, (int) $cycle ), $cart, true );
			return;
		}

		$this->repository->add_log( $cart_id, 'info', 'tag_removed', sprintf( 'Tag "%s" removed.', sanitize_text_field( $tag ) ) );
	}

	/**
	 * Upsert contact and mapped custom fields.
	 *
	 * @param array  $cart Cart.
	 * @param string $recovery_token Recovery token.
	 * @return true|\WP_Error
	 */
	private function sync_fields( array $cart, $recovery_token ) {
		if ( empty( $cart['email'] ) || ! is_email( $cart['email'] ) ) {
			return new \WP_Error( 'ghlcs_missing_cart_email', __( 'Cart has no valid email.', 'ghl-contact-sync' ) );
		}

		$client  = new GHL_Client( get_option( 'ghlcs_settings', array() ) );
		$payload = array(
			'email'  => sanitize_email( $cart['email'] ),
			'source' => 'WooCommerce / Website',
		);

		if ( ! empty( $cart['first_name'] ) ) {
			$payload['firstName'] = sanitize_text_field( $cart['first_name'] );
		}
		if ( ! empty( $cart['last_name'] ) ) {
			$payload['lastName'] = sanitize_text_field( $cart['last_name'] );
		}
		if ( ! empty( $cart['phone'] ) ) {
			$payload['phone'] = sanitize_text_field( $cart['phone'] );
		}

		$custom_fields = $this->mapped_custom_fields( $cart, $recovery_token );
		if ( ! empty( $custom_fields ) ) {
			$payload['customFields'] = $custom_fields;
		}

		$result = $client->upsert_contact( $payload );

		if ( is_wp_error( $result ) || empty( $result['success'] ) ) {
			return is_wp_error( $result ) ? $result : new \WP_Error( 'ghlcs_upsert_failed', $result['message'] ?? __( 'GHL contact upsert failed.', 'ghl-contact-sync' ) );
		}

		$contact_id = $result['body']['contact']['id'] ?? ( $result['body']['id'] ?? ( $result['body']['contactId'] ?? '' ) );

		$this->repository->update(
			$cart['id'],
			array(
				'ghl_contact_id'  => $contact_id,
				'ghl_sync_status' => 'synced',
				'ghl_synced_at'   => current_time( 'mysql' ),
				'last_error'      => '',
			)
		);
		$this->repository->add_log( $cart['id'], 'info', 'fields_synced', __( 'GHL contact and cart fields synced.', 'ghl-contact-sync' ) );

		return true;
	}

	/**
	 * Build mapped custom fields.
	 *
	 * @param array  $cart Cart.
	 * @param string $recovery_token Token.
	 * @return array
	 */
	private function mapped_custom_fields( array $cart, $recovery_token ) {
		$mapping = Settings::get()['field_mapping'];
		$values  = array(
			'cart_id'        => $cart['cart_uuid'],
			'cart_status'    => $cart['status'],
			'cart_total'     => function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $cart['total'], 2 ) : number_format( (float) $cart['total'], 2, '.', '' ),
			'cart_currency'  => $cart['currency'],
			'item_count'     => (string) $cart['item_count'],
			'products'       => $this->products_summary( $cart ),
			'recovery_url'   => '' !== $recovery_token ? Recovery_Service::recovery_url( $recovery_token ) : '',
			'last_activity'  => $cart['last_activity_at'],
			'abandoned_at'   => $cart['abandoned_at'],
			'order_id'       => ! empty( $cart['order_id'] ) ? (string) $cart['order_id'] : '',
		);
		$fields  = array();

		foreach ( $values as $key => $value ) {
			if ( empty( $mapping[ $key ] ) || '' === (string) $value ) {
				continue;
			}

			$fields[] = array(
				'id'    => sanitize_text_field( $mapping[ $key ] ),
				'value' => sanitize_text_field( (string) $value ),
			);
		}

		return $fields;
	}

	/**
	 * Products summary for GHL fields.
	 *
	 * @param array $cart Cart.
	 * @return string
	 */
	private function products_summary( array $cart ) {
		$lines = array();
		$items = isset( $cart['cart_data']['items'] ) && is_array( $cart['cart_data']['items'] ) ? $cart['cart_data']['items'] : array();

		foreach ( $items as $item ) {
			$lines[] = trim( ( $item['name'] ?? '' ) . ' x ' . (int) ( $item['quantity'] ?? 0 ) );
		}

		return implode( "\n", array_filter( $lines ) );
	}

	/**
	 * Mark sync failed without leaking tokens.
	 *
	 * @param int    $cart_id Cart ID.
	 * @param string $message Error.
	 * @param string $event Event.
	 * @return void
	 */
	private function mark_failed( $cart_id, $message, $event ) {
		$message = preg_replace( '/Bearer\s+[A-Za-z0-9._-]+/i', 'Bearer [redacted]', (string) $message );
		$this->repository->update( $cart_id, array( 'ghl_sync_status' => 'failed', 'last_error' => $message ) );
		$this->repository->add_log( $cart_id, 'error', $event, $message );
	}

	/**
	 * Schedule limited retries.
	 *
	 * @param string $hook Hook.
	 * @param array  $args Args.
	 * @param array  $cart Cart.
	 * @param bool   $urgent Urgent retry.
	 * @return void
	 */
	private function retry( $hook, array $args, array $cart, $urgent = false ) {
		$attempts = (int) get_transient( 'ghlcs_ac_retry_' . md5( $hook . wp_json_encode( $args ) ) );

		if ( $attempts >= 3 ) {
			return;
		}

		set_transient( 'ghlcs_ac_retry_' . md5( $hook . wp_json_encode( $args ) ), $attempts + 1, DAY_IN_SECONDS );
		Background_Jobs::enqueue_unique( $hook, $args, $urgent ? 120 : ( 300 * ( $attempts + 1 ) ) );
	}
}
