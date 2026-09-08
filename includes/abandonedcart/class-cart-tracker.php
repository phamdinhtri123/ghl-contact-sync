<?php
/**
 * WooCommerce cart tracker.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\AbandonedCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks one current cart snapshot per WooCommerce session.
 */
final class Cart_Tracker {

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
		add_action( 'woocommerce_add_to_cart', array( $this, 'track_current_cart' ), 20 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'track_current_cart' ), 20 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'track_current_cart' ), 20 );
		add_action( 'woocommerce_applied_coupon', array( $this, 'track_current_cart' ), 20 );
		add_action( 'woocommerce_removed_coupon', array( $this, 'track_current_cart' ), 20 );
		add_action( 'woocommerce_cart_emptied', array( $this, 'track_current_cart' ), 20 );
		add_action( 'woocommerce_cart_updated', array( $this, 'track_current_cart' ), 20 );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'capture_classic_checkout_identity' ), 20 );
		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( $this, 'capture_store_api_identity' ), 20, 2 );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( $this, 'capture_store_api_identity' ), 20, 2 );
		add_action( 'wp_ajax_ghlcs_capture_checkout_identity', array( $this, 'capture_checkout_identity' ) );
		add_action( 'wp_ajax_nopriv_ghlcs_capture_checkout_identity', array( $this, 'capture_checkout_identity' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Track current cart if enabled and WooCommerce is available.
	 *
	 * @return int|\WP_Error|null
	 */
	public function track_current_cart() {
		if ( ! $this->can_track() || ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) {
			return null;
		}

		$settings = Settings::get();
		$user_id  = get_current_user_id();

		if ( $user_id && empty( $settings['track_logged_in_carts'] ) ) {
			return null;
		}

		if ( ! $user_id && empty( $settings['track_guest_carts'] ) ) {
			return null;
		}

		$session_key = $this->session_key();
		$snapshot    = $this->snapshot();
		$identity    = $this->identity();

		$result = $this->repository->upsert_current( $session_key, $snapshot, $identity );

		if ( ! is_wp_error( $result ) && $result ) {
			$cart = $this->repository->get( $result );
			if ( $cart && ! empty( $cart['email'] ) && ! empty( Settings::get()['ghl_sync_enabled'] ) ) {
				if ( empty( $cart['ghl_contact_id'] ) || 'abandoned' !== $cart['status'] ) {
					Background_Jobs::enqueue_unique( 'ghlcs_ac_sync_status', array( (int) $cart['id'], $cart['status'] ), 120 );
				}
			}
			if ( $cart && in_array( $cart['status'], array( 'active', 'expired' ), true ) && ! empty( $cart['ghl_contact_id'] ) ) {
				Background_Jobs::enqueue_unique( 'ghlcs_ac_remove_tag', array( (int) $cart['id'], (int) $cart['abandonment_cycle'] ), 30 );
			}
		}

		return $result;
	}

	/**
	 * Capture a valid checkout email through debounced frontend AJAX.
	 *
	 * @return void
	 */
	public function capture_checkout_identity() {
		check_ajax_referer( 'ghlcs_capture_checkout_identity', 'nonce' );

		if ( ! $this->can_track() ) {
			wp_send_json_error( array( 'message' => __( 'Cart tracking is disabled.', 'ghl-contact-sync' ) ), 403 );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Valid email is required.', 'ghl-contact-sync' ) ), 400 );
		}

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

		$result = $this->repository->upsert_current(
			$this->session_key(),
			$this->snapshot(),
			array(
				'user_id'    => get_current_user_id(),
				'email'      => $email,
				'email_source' => 'checkout',
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'phone'      => $phone,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not capture cart identity.', 'ghl-contact-sync' ) ), 500 );
		}

		if ( ! empty( Settings::get()['ghl_sync_enabled'] ) ) {
			Background_Jobs::enqueue_unique( 'ghlcs_ac_sync_status', array( (int) $result, 'active' ), 120 );
		}

		wp_send_json_success( array( 'cart_id' => (int) $result ) );
	}

	/**
	 * Capture Classic Checkout identity from WooCommerce order review AJAX.
	 *
	 * @param string $post_data Serialized checkout data.
	 * @return void
	 */
	public function capture_classic_checkout_identity( $post_data ) {
		$data = array();
		parse_str( (string) $post_data, $data );

		$this->capture_identity_values(
			array(
				'email'      => $data['billing_email'] ?? '',
				'first_name' => $data['billing_first_name'] ?? '',
				'last_name'  => $data['billing_last_name'] ?? '',
				'phone'      => $data['billing_phone'] ?? '',
			)
		);
	}

	/**
	 * Capture Checkout Blocks identity from Store API requests.
	 *
	 * @param \WC_Customer      $customer Woo customer.
	 * @param \WP_REST_Request $request Request.
	 * @return void
	 */
	public function capture_store_api_identity( $customer, $request ) {
		$billing = is_object( $request ) ? $request->get_param( 'billing_address' ) : array();
		$billing = is_array( $billing ) ? $billing : array();

		$this->capture_identity_values(
			array(
				'email'      => $billing['email'] ?? ( is_object( $customer ) && method_exists( $customer, 'get_billing_email' ) ? $customer->get_billing_email() : '' ),
				'first_name' => $billing['first_name'] ?? ( is_object( $customer ) && method_exists( $customer, 'get_billing_first_name' ) ? $customer->get_billing_first_name() : '' ),
				'last_name'  => $billing['last_name'] ?? ( is_object( $customer ) && method_exists( $customer, 'get_billing_last_name' ) ? $customer->get_billing_last_name() : '' ),
				'phone'      => $billing['phone'] ?? ( is_object( $customer ) && method_exists( $customer, 'get_billing_phone' ) ? $customer->get_billing_phone() : '' ),
			)
		);
	}

	/**
	 * Capture checkout identity values as the authoritative cart contact.
	 *
	 * @param array $values Identity values.
	 * @return int|\WP_Error|null
	 */
	private function capture_identity_values( array $values ) {
		if ( ! $this->can_track() || ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) {
			return null;
		}

		$email = isset( $values['email'] ) ? sanitize_email( wp_unslash( $values['email'] ) ) : '';

		if ( ! is_email( $email ) ) {
			return null;
		}

		$result = $this->repository->upsert_current(
			$this->session_key(),
			$this->snapshot(),
			array(
				'user_id'      => get_current_user_id(),
				'email'        => $email,
				'email_source' => 'checkout',
				'first_name'   => isset( $values['first_name'] ) ? sanitize_text_field( wp_unslash( $values['first_name'] ) ) : '',
				'last_name'    => isset( $values['last_name'] ) ? sanitize_text_field( wp_unslash( $values['last_name'] ) ) : '',
				'phone'        => isset( $values['phone'] ) ? sanitize_text_field( wp_unslash( $values['phone'] ) ) : '',
			)
		);

		if ( ! is_wp_error( $result ) && $result && ! empty( Settings::get()['ghl_sync_enabled'] ) ) {
			Background_Jobs::enqueue_unique( 'ghlcs_ac_sync_status', array( (int) $result, 'active' ), 120 );
		}

		return $result;
	}

	/**
	 * Enqueue lightweight checkout identity capture.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->can_track() || ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		wp_enqueue_script(
			'ghlcs-abandoned-cart',
			GHLCS_PLUGIN_URL . 'assets/js/abandoned-cart.js',
			array(),
			GHLCS_VERSION,
			true
		);

		wp_localize_script(
			'ghlcs-abandoned-cart',
			'ghlcsAbandonedCart',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghlcs_capture_checkout_identity' ),
			)
		);
	}

	/**
	 * Whether tracking is enabled.
	 *
	 * @return bool
	 */
	private function can_track() {
		$settings = Settings::get();

		return ! empty( $settings['enabled'] ) && class_exists( 'WooCommerce' );
	}

	/**
	 * Build session key.
	 *
	 * @return string
	 */
	private function session_key() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			$customer_id = WC()->session->get_customer_id();
			if ( $customer_id ) {
				return wp_hash( (string) $customer_id );
			}
		}

		return wp_hash( wp_get_session_token() ? wp_get_session_token() : (string) get_current_user_id() );
	}

	/**
	 * Current cart snapshot.
	 *
	 * @return array
	 */
	private function snapshot() {
		$items = array();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ? $cart_item['data'] : null;

			$items[] = array(
				'product_id'   => isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0,
				'variation_id' => isset( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0,
				'name'         => $product ? $product->get_name() : '',
				'attributes'   => isset( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ? array_map( 'sanitize_text_field', $cart_item['variation'] ) : array(),
				'quantity'     => isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0,
				'price'        => $product ? (float) $product->get_price() : 0,
				'line_total'   => isset( $cart_item['line_total'] ) ? (float) $cart_item['line_total'] : 0,
			);
		}

		return array(
			'items'      => $items,
			'item_count' => (int) WC()->cart->get_cart_contents_count(),
			'subtotal'   => (float) WC()->cart->get_subtotal(),
			'total'      => (float) WC()->cart->get_total( 'edit' ),
			'currency'   => get_woocommerce_currency(),
			'coupons'    => array_values( WC()->cart->get_applied_coupons() ),
		);
	}

	/**
	 * Known customer identity.
	 *
	 * @return array
	 */
	private function identity() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return array();
		}

		$user = get_userdata( $user_id );

		return array(
			'user_id'    => $user_id,
			'email'      => $user ? $user->user_email : '',
			'first_name' => get_user_meta( $user_id, 'billing_first_name', true ) ?: get_user_meta( $user_id, 'first_name', true ),
			'last_name'  => get_user_meta( $user_id, 'billing_last_name', true ) ?: get_user_meta( $user_id, 'last_name', true ),
			'phone'      => get_user_meta( $user_id, 'billing_phone', true ),
		);
	}
}
