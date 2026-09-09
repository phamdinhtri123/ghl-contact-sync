<?php
/**
 * WooCommerce order lifecycle integration.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\AbandonedCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Suppresses abandoned messaging when checkout creates an order and marks successful purchases recovered.
 */
final class Order_Lifecycle_Service {

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
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'woocommerce_checkout_order_created', array( $this, 'order_created' ), 20 );
		add_action( 'woocommerce_payment_complete', array( $this, 'order_recovered' ), 20 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'order_recovered' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'order_recovered' ), 20 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'order_cancelled_or_failed' ), 20 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'order_cancelled_or_failed' ), 20 );
	}

	/**
	 * Move current cart into order_pending.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public function order_created( $order ) {
		$cart = $this->current_cart();

		if ( ! $cart || ! is_object( $order ) ) {
			return;
		}

		$this->repository->update(
			$cart['id'],
			array(
				'status'     => 'order_pending',
				'order_id'   => $order->get_id(),
				'last_error' => '',
			)
		);
		$this->repository->add_log( $cart['id'], 'info', 'order_created', sprintf( 'Order #%d created; abandoned workflow suppressed.', $order->get_id() ) );

		Background_Jobs::enqueue_unique( 'ghlcs_ac_remove_tag', array( (int) $cart['id'], (int) $cart['abandonment_cycle'] ), 0 );
		Background_Jobs::enqueue_unique( 'ghlcs_ac_sync_status', array( (int) $cart['id'], 'order_pending' ), 60 );
	}

	/**
	 * Mark cart recovered after successful order conversion.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function order_recovered( $order_id ) {
		global $wpdb;

		$order_id = is_object( $order_id ) && method_exists( $order_id, 'get_id' ) ? $order_id->get_id() : (int) $order_id;
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT id, abandonment_cycle FROM {$this->repository->table_name()} WHERE order_id = %d ORDER BY id DESC LIMIT 1", $order_id ), ARRAY_A );

		if ( ! $row ) {
			return;
		}

		$this->repository->update(
			$row['id'],
			array(
				'status'                   => 'recovered',
				'recovered_at'             => current_time( 'mysql' ),
				'recovery_token_hash'      => '',
				'recovery_token_encrypted' => '',
				'recovery_expires_at'      => null,
				'last_error'               => '',
			)
		);
		$this->repository->add_log( $row['id'], 'info', 'cart_recovered', sprintf( 'Order #%d recovered the cart.', $order_id ) );

		Background_Jobs::enqueue_unique( 'ghlcs_ac_remove_tag', array( (int) $row['id'], (int) $row['abandonment_cycle'] ), 0 );
		Background_Jobs::enqueue_unique( 'ghlcs_ac_sync_status', array( (int) $row['id'], 'recovered' ), 60 );
		$this->clear_recovery_context();
	}

	/**
	 * Failed/cancelled orders stay suppressed until shopper modifies the cart again.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function order_cancelled_or_failed( $order_id ) {
		global $wpdb;

		$order_id = (int) $order_id;
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$this->repository->table_name()} WHERE order_id = %d ORDER BY id DESC LIMIT 1", $order_id ), ARRAY_A );

		if ( $row ) {
			$this->repository->add_log( $row['id'], 'info', 'order_not_recovered', sprintf( 'Order #%d failed or was cancelled; cart is not marked recovered.', $order_id ) );
		}
	}

	/**
	 * Current session cart.
	 *
	 * @return array|null
	 */
	private function current_cart() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return null;
		}

		$recovered_cart_id = absint( WC()->session->get( 'ghlcs_recovered_cart_id' ) );
		if ( $recovered_cart_id ) {
			$cart = $this->repository->get( $recovered_cart_id );
			if ( $cart && ! in_array( $cart['status'], array( 'recovered', 'expired' ), true ) ) {
				return $cart;
			}
		}

		return $this->repository->get_by_session( wp_hash( (string) WC()->session->get_customer_id() ) );
	}

	/**
	 * Clear the session marker after a successful purchase.
	 *
	 * @return void
	 */
	private function clear_recovery_context() {
		if ( ! function_exists( 'WC' ) || ! WC()->session || ! method_exists( WC()->session, '__unset' ) ) {
			return;
		}

		WC()->session->__unset( 'ghlcs_recovered_cart_id' );
		WC()->session->__unset( 'ghlcs_recovered_cart_email' );
	}
}
