<?php
/**
 * Cart recovery service.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\AbandonedCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates recovery links and restores carts through WooCommerce APIs.
 */
final class Recovery_Service {

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
		add_action( 'template_redirect', array( $this, 'maybe_recover' ) );
	}

	/**
	 * Build public recovery URL.
	 *
	 * @param string $token Recovery token.
	 * @return string
	 */
	public static function recovery_url( $token ) {
		return add_query_arg( 'ghlcs_cart_recovery', rawurlencode( $token ), home_url( '/' ) );
	}

	/**
	 * Handle recovery request.
	 *
	 * @return void
	 */
	public function maybe_recover() {
		if ( empty( $_GET['ghlcs_cart_recovery'] ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) ) {
			wp_die( esc_html__( 'WooCommerce is required to recover this cart.', 'ghl-contact-sync' ) );
		}

		$token = sanitize_text_field( wp_unslash( $_GET['ghlcs_cart_recovery'] ) );
		$cart  = $this->find_by_token( $token );

		if ( ! $cart ) {
			wp_die( esc_html__( 'This recovery link is invalid or has expired.', 'ghl-contact-sync' ) );
		}

		if ( ! WC()->cart ) {
			wc_load_cart();
		}

		$settings = Settings::get();
		if ( 'replace' === $settings['recovery_cart_behavior'] ) {
			WC()->cart->empty_cart();
		}

		$restored = 0;
		$items    = isset( $cart['cart_data']['items'] ) && is_array( $cart['cart_data']['items'] ) ? $cart['cart_data']['items'] : array();

		foreach ( $items as $item ) {
			$product_id   = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$variation_id = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
			$quantity     = max( 1, isset( $item['quantity'] ) ? (int) $item['quantity'] : 1 );
			$product      = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );

			if ( ! $product || ! $product->exists() || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				continue;
			}

			$variation = isset( $item['attributes'] ) && is_array( $item['attributes'] ) ? $item['attributes'] : array();
			$added     = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );

			if ( $added ) {
				$restored++;
			}
		}

		WC()->cart->calculate_totals();

		$this->repository->update(
			$cart['id'],
			array(
				'status'           => 'active',
				'last_activity_at' => current_time( 'mysql' ),
				'last_error'       => '',
			)
		);
		$this->repository->add_log( $cart['id'], 'info', 'cart_restored', sprintf( 'Recovery link restored %d item groups.', $restored ) );

		Background_Jobs::enqueue_unique( 'ghlcs_ac_remove_tag', array( (int) $cart['id'], (int) $cart['abandonment_cycle'] ), 0 );
		Background_Jobs::enqueue_unique( 'ghlcs_ac_sync_status', array( (int) $cart['id'], 'active' ), 60 );

		$redirect = 'cart' === $settings['recovery_redirect'] && function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : wc_get_checkout_url();
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Find a cart using password hash verification.
	 *
	 * @param string $token Raw token.
	 * @return array|null
	 */
	private function find_by_token( $token ) {
		global $wpdb;

		$parts = explode( '.', $token, 2 );

		if ( 2 !== count( $parts ) || ! wp_is_uuid( $parts[0] ) ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->repository->table_name()} WHERE cart_uuid = %s AND recovery_expires_at >= %s AND recovery_token_hash <> '' LIMIT 1",
				sanitize_text_field( $parts[0] ),
				current_time( 'mysql' )
			),
			ARRAY_A
		);

		if ( $row && wp_check_password( $token, $row['recovery_token_hash'] ) ) {
			return $this->repository->get( (int) $row['id'] );
		}

		return null;
	}
}
