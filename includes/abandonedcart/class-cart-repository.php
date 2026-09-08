<?php
/**
 * Abandoned cart repository.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\AbandonedCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes cart table queries.
 */
final class Cart_Repository {

	/**
	 * Cart table name.
	 *
	 * @return string
	 */
	public function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'ghl_contact_sync_carts';
	}

	/**
	 * Log table name.
	 *
	 * @return string
	 */
	public function logs_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'ghl_contact_sync_cart_sync_logs';
	}

	/**
	 * Find cart by ID.
	 *
	 * @param int $cart_id Cart ID.
	 * @return array|null
	 */
	public function get( $cart_id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name()} WHERE id = %d", (int) $cart_id ), ARRAY_A );

		return $row ? $this->decode_row( $row ) : null;
	}

	/**
	 * Find the current cart by Woo session key.
	 *
	 * @param string $session_key Session key.
	 * @return array|null
	 */
	public function get_by_session( $session_key ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} WHERE session_key = %s AND status IN ('active','abandoned','expired') ORDER BY id DESC LIMIT 1",
				(string) $session_key
			),
			ARRAY_A
		);

		return $row ? $this->decode_row( $row ) : null;
	}

	/**
	 * Find a recoverable cart by token hash.
	 *
	 * @param string $token_hash Token hash.
	 * @return array|null
	 */
	public function get_by_token_hash( $token_hash ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} WHERE recovery_token_hash = %s AND recovery_expires_at >= %s LIMIT 1",
				(string) $token_hash,
				current_time( 'mysql' )
			),
			ARRAY_A
		);

		return $row ? $this->decode_row( $row ) : null;
	}

	/**
	 * Create or update the one current row for a browser/Woo session.
	 *
	 * @param string $session_key Session key.
	 * @param array  $snapshot Cart snapshot.
	 * @param array  $identity Customer identity.
	 * @return int|\WP_Error
	 */
	public function upsert_current( $session_key, array $snapshot, array $identity ) {
		global $wpdb;

		$existing = $this->get_by_session( $session_key );
		$now      = current_time( 'mysql' );
		$email    = ! empty( $identity['email'] ) && is_email( $identity['email'] ) ? sanitize_email( $identity['email'] ) : ( $existing['email'] ?? '' );
		$status   = empty( $snapshot['item_count'] ) ? 'expired' : 'active';

		if ( ! $existing && empty( $snapshot['item_count'] ) ) {
			return null;
		}

		if ( $existing && 'abandoned' === $existing['status'] && ! empty( $snapshot['item_count'] ) ) {
			$status = 'active';
		}

		$data = array(
			'session_key'      => sanitize_text_field( $session_key ),
			'user_id'          => ! empty( $identity['user_id'] ) ? (int) $identity['user_id'] : null,
			'email'            => $email,
			'first_name'       => isset( $identity['first_name'] ) ? sanitize_text_field( $identity['first_name'] ) : ( $existing['first_name'] ?? '' ),
			'last_name'        => isset( $identity['last_name'] ) ? sanitize_text_field( $identity['last_name'] ) : ( $existing['last_name'] ?? '' ),
			'phone'            => isset( $identity['phone'] ) ? sanitize_text_field( $identity['phone'] ) : ( $existing['phone'] ?? '' ),
			'cart_data'        => wp_json_encode( $snapshot ),
			'item_count'       => (int) $snapshot['item_count'],
			'subtotal'         => (float) $snapshot['subtotal'],
			'total'            => (float) $snapshot['total'],
			'currency'         => sanitize_text_field( $snapshot['currency'] ),
			'status'           => $status,
			'updated_at'       => $now,
			'last_activity_at' => $now,
		);

		if ( $existing ) {
			$updated = $wpdb->update(
				$this->table_name(),
				$data,
				array( 'id' => (int) $existing['id'] ),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return false === $updated ? new \WP_Error( 'ghlcs_cart_update_failed', __( 'Could not update cart.', 'ghl-contact-sync' ) ) : (int) $existing['id'];
		}

		$data['cart_uuid']  = wp_generate_uuid4();
		$data['created_at'] = $now;

		$inserted = $wpdb->insert(
			$this->table_name(),
			$data,
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? new \WP_Error( 'ghlcs_cart_insert_failed', __( 'Could not save cart.', 'ghl-contact-sync' ) ) : (int) $wpdb->insert_id;
	}

	/**
	 * Update selected cart fields.
	 *
	 * @param int   $cart_id Cart ID.
	 * @param array $data Fields.
	 * @return bool
	 */
	public function update( $cart_id, array $data ) {
		global $wpdb;

		$allowed = array(
			'contact_id', 'user_id', 'ghl_contact_id', 'email', 'first_name', 'last_name', 'phone', 'status',
			'order_id', 'abandonment_cycle', 'recovery_token_hash', 'recovery_expires_at', 'ghl_sync_status',
			'ghl_synced_at', 'last_error', 'updated_at', 'last_activity_at', 'abandoned_at', 'recovered_at',
		);
		$clean   = array();

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed, true ) ) {
				$clean[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		}

		if ( empty( $clean ) ) {
			return true;
		}

		$clean['updated_at'] = current_time( 'mysql' );

		return false !== $wpdb->update( $this->table_name(), $clean, array( 'id' => (int) $cart_id ) );
	}

	/**
	 * Get carts ready to become abandoned.
	 *
	 * @param int   $threshold_minutes Threshold in minutes.
	 * @param float $minimum_total Minimum total.
	 * @param int   $limit Limit.
	 * @return array
	 */
	public function due_for_abandonment( $threshold_minutes, $minimum_total, $limit = 20 ) {
		global $wpdb;

		$cutoff = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( max( 1, (int) $threshold_minutes ) * MINUTE_IN_SECONDS ) );
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} WHERE status = 'active' AND item_count > 0 AND email <> '' AND total >= %f AND last_activity_at <= %s ORDER BY last_activity_at ASC LIMIT %d",
				(float) $minimum_total,
				$cutoff,
				(int) $limit
			),
			ARRAY_A
		);

		return array_map( array( $this, 'decode_row' ), $rows ? $rows : array() );
	}

	/**
	 * List carts for admin.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function list_carts( array $args ) {
		global $wpdb;

		$status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
		$search = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';
		$page   = max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 );
		$limit  = max( 1, min( 100, isset( $args['limit'] ) ? (int) $args['limit'] : 20 ) );
		$offset = ( $page - 1 ) * $limit;
		$where  = 'WHERE 1=1';
		$params = array();

		if ( '' !== $status ) {
			$where    .= ' AND status = %s';
			$params[] = $status;
		}

		if ( '' !== $search ) {
			$where    .= ' AND (email LIKE %s OR cart_uuid LIKE %s OR order_id = %d)';
			$like      = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = absint( $search );
		}

		$count_sql = "SELECT COUNT(*) FROM {$this->table_name()} {$where}";
		$rows_sql  = "SELECT * FROM {$this->table_name()} {$where} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );
		$params[]  = $limit;
		$params[]  = $offset;
		$rows      = $wpdb->get_results( $wpdb->prepare( $rows_sql, $params ), ARRAY_A );

		return array(
			'total' => $total,
			'rows'  => array_map( array( $this, 'decode_row' ), $rows ? $rows : array() ),
		);
	}

	/**
	 * Lightweight admin stats.
	 *
	 * @return array
	 */
	public function stats() {
		global $wpdb;

		$table = $this->table_name();

		return array(
			'active'            => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" ),
			'anonymous'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE email = '' OR email IS NULL" ),
			'abandoned'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'abandoned'" ),
			'recovered'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'recovered'" ),
			'abandoned_value'   => (float) $wpdb->get_var( "SELECT COALESCE(SUM(total),0) FROM {$table} WHERE status = 'abandoned'" ),
			'recovered_revenue' => (float) $wpdb->get_var( "SELECT COALESCE(SUM(total),0) FROM {$table} WHERE status = 'recovered'" ),
		);
	}

	/**
	 * Insert an operational sync log.
	 *
	 * @param int    $cart_id Cart ID.
	 * @param string $level Log level.
	 * @param string $event Event key.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public function add_log( $cart_id, $level, $event, $message, array $context = array() ) {
		global $wpdb;

		$wpdb->insert(
			$this->logs_table_name(),
			array(
				'cart_id'    => (int) $cart_id,
				'level'      => sanitize_key( $level ),
				'event'      => sanitize_key( $event ),
				'message'    => sanitize_textarea_field( $message ),
				'context'    => empty( $context ) ? '' : wp_json_encode( $context ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * List logs.
	 *
	 * @param int $cart_id Optional cart ID.
	 * @param int $limit Limit.
	 * @return array
	 */
	public function logs( $cart_id = 0, $limit = 100 ) {
		global $wpdb;

		if ( $cart_id ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$this->logs_table_name()} WHERE cart_id = %d ORDER BY created_at DESC LIMIT %d", (int) $cart_id, (int) $limit ),
				ARRAY_A
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$this->logs_table_name()} ORDER BY created_at DESC LIMIT %d", (int) $limit ),
			ARRAY_A
		);
	}

	/**
	 * Cleanup old rows in bounded batches.
	 *
	 * @param array $settings Module settings.
	 * @return void
	 */
	public function cleanup( array $settings ) {
		global $wpdb;

		$now = current_time( 'timestamp' );
		$map = array(
			'expired'   => (int) $settings['anonymous_retention_days'],
			'abandoned' => (int) $settings['abandoned_retention_days'],
			'recovered' => (int) $settings['recovered_retention_days'],
		);

		foreach ( $map as $status => $days ) {
			$cutoff = date( 'Y-m-d H:i:s', $now - ( max( 1, $days ) * DAY_IN_SECONDS ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table_name()} WHERE status = %s AND updated_at < %s LIMIT 100", $status, $cutoff ) );
		}

		$log_cutoff = date( 'Y-m-d H:i:s', $now - ( max( 1, (int) $settings['logs_retention_days'] ) * DAY_IN_SECONDS ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->logs_table_name()} WHERE created_at < %s LIMIT 250", $log_cutoff ) );
	}

	/**
	 * Decode row JSON.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private function decode_row( array $row ) {
		$row['cart_data'] = ! empty( $row['cart_data'] ) ? json_decode( $row['cart_data'], true ) : array();
		if ( ! is_array( $row['cart_data'] ) ) {
			$row['cart_data'] = array();
		}

		return $row;
	}
}
