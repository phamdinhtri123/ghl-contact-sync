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
		$already_recovered = $this->is_recovered_cart_in_current_session( $cart );
		$this->bind_recovered_cart( $cart );

		if ( 'replace' === $settings['recovery_cart_behavior'] ) {
			WC()->cart->empty_cart();
			$already_recovered = false;
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

			$added = $already_recovered
				? $this->ensure_cart_item_quantity( $product_id, $variation_id, $quantity, $item )
				: WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $this->item_variation( $item ) );

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

	/**
	 * Bind the current Woo session to the recovered cart record.
	 *
	 * @param array $cart Cart row.
	 * @return void
	 */
	private function bind_recovered_cart( array $cart ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->set( 'ghlcs_recovered_cart_id', (int) $cart['id'] );
		WC()->session->set( 'ghlcs_recovered_cart_email', $cart['email'] );

		$session_key = $this->current_session_key();
		if ( '' !== $session_key ) {
			$this->repository->update(
				$cart['id'],
				array(
					'session_key' => $session_key,
				)
			);
		}
	}

	/**
	 * Whether this session has already restored this cart.
	 *
	 * @param array $cart Cart row.
	 * @return bool
	 */
	private function is_recovered_cart_in_current_session( array $cart ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return false;
		}

		return (int) WC()->session->get( 'ghlcs_recovered_cart_id' ) === (int) $cart['id'];
	}

	/**
	 * Ensure a recovered item exists without doubling it on repeated link clicks.
	 *
	 * @param int   $product_id Product ID.
	 * @param int   $variation_id Variation ID.
	 * @param int   $quantity Desired quantity.
	 * @param array $item Stored cart item.
	 * @return bool
	 */
	private function ensure_cart_item_quantity( $product_id, $variation_id, $quantity, array $item ) {
		$cart_item_key = $this->find_cart_item_key( $product_id, $variation_id, $this->item_variation( $item ) );

		if ( $cart_item_key ) {
			$current_quantity = isset( WC()->cart->cart_contents[ $cart_item_key ]['quantity'] ) ? (int) WC()->cart->cart_contents[ $cart_item_key ]['quantity'] : 0;
			if ( $current_quantity < $quantity ) {
				WC()->cart->set_quantity( $cart_item_key, $quantity, false );
			}

			return true;
		}

		return (bool) WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $this->item_variation( $item ) );
	}

	/**
	 * Find a matching Woo cart item.
	 *
	 * @param int   $product_id Product ID.
	 * @param int   $variation_id Variation ID.
	 * @param array $variation Variation attributes.
	 * @return string
	 */
	private function find_cart_item_key( $product_id, $variation_id, array $variation ) {
		$target_variation = $this->normalize_variation( $variation );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$current_product_id   = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
			$current_variation_id = isset( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0;
			$current_variation    = isset( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ? $cart_item['variation'] : array();

			if ( $current_product_id === $product_id && $current_variation_id === $variation_id && $this->normalize_variation( $current_variation ) === $target_variation ) {
				return (string) $cart_item_key;
			}
		}

		return '';
	}

	/**
	 * Get stored variation attributes.
	 *
	 * @param array $item Stored cart item.
	 * @return array
	 */
	private function item_variation( array $item ) {
		return isset( $item['attributes'] ) && is_array( $item['attributes'] ) ? $item['attributes'] : array();
	}

	/**
	 * Normalize variation attributes for comparison.
	 *
	 * @param array $variation Variation attributes.
	 * @return array
	 */
	private function normalize_variation( array $variation ) {
		$normalized = array();

		foreach ( $variation as $key => $value ) {
			$normalized[ sanitize_key( $key ) ] = sanitize_text_field( $value );
		}

		ksort( $normalized );

		return $normalized;
	}

	/**
	 * Current Woo session key using the same hash as the tracker.
	 *
	 * @return string
	 */
	private function current_session_key() {
		$customer_id = WC()->session->get_customer_id();
		if ( $customer_id ) {
			return wp_hash( (string) $customer_id );
		}

		return wp_hash( wp_get_session_token() ? wp_get_session_token() : (string) get_current_user_id() );
	}
}
