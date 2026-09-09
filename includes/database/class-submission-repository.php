<?php
/**
 * Submission repository.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes access to the submissions table.
 */
final class Submission_Repository {

	/**
	 * Get the submissions table name.
	 *
	 * @return string
	 */
	public function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'ghl_contact_sync_submissions';
	}

	/**
	 * Find a submission by ID.
	 *
	 * @param int $submission_id Submission ID.
	 * @return array|null
	 */
	public function get( $submission_id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name()} WHERE id = %d", (int) $submission_id ), ARRAY_A );

		return $row ? $this->decode_row( $row ) : null;
	}

	/**
	 * List saved submissions for admin.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function list_submissions( array $args = array() ) {
		global $wpdb;

		$form_id = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		$status  = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
		$search  = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';
		$page    = max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 );
		$limit   = max( 1, min( 100, isset( $args['limit'] ) ? (int) $args['limit'] : 20 ) );
		$offset  = ( $page - 1 ) * $limit;
		$where   = 'WHERE 1=1';
		$params  = array();

		if ( $form_id ) {
			$where    .= ' AND form_id = %d';
			$params[] = $form_id;
		}

		if ( '' !== $status ) {
			$where    .= ' AND sync_status = %s';
			$params[] = $status;
		}

		if ( '' !== $search ) {
			$where    .= ' AND (email LIKE %s OR phone LIKE %s OR ghl_contact_id LIKE %s)';
			$like      = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$count_sql = "SELECT COUNT(*) FROM {$this->table_name()} {$where}";
		$rows_sql  = "SELECT * FROM {$this->table_name()} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
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
	 * Insert a frontend submission.
	 *
	 * @param array $submission Submission data.
	 * @return int|\WP_Error
	 */
	public function insert( array $submission ) {
		global $wpdb;

		$now   = current_time( 'mysql' );
		$table = $this->table_name();

		$inserted = $wpdb->insert(
			$table,
			array(
				'form_id'         => isset( $submission['form_id'] ) ? (int) $submission['form_id'] : 0,
				'submission_data' => wp_json_encode( isset( $submission['data'] ) && is_array( $submission['data'] ) ? $submission['data'] : array() ),
				'email'           => isset( $submission['email'] ) ? sanitize_email( $submission['email'] ) : '',
				'phone'           => isset( $submission['phone'] ) ? sanitize_text_field( $submission['phone'] ) : '',
				'sync_status'     => isset( $submission['sync_status'] ) ? sanitize_key( $submission['sync_status'] ) : 'pending',
				'ip_hash'         => isset( $submission['ip_hash'] ) ? sanitize_text_field( $submission['ip_hash'] ) : '',
				'user_agent'      => isset( $submission['user_agent'] ) ? sanitize_textarea_field( $submission['user_agent'] ) : '',
				'landing_page'    => isset( $submission['landing_page'] ) ? esc_url_raw( $submission['landing_page'] ) : '',
				'referrer'        => isset( $submission['referrer'] ) ? esc_url_raw( $submission['referrer'] ) : '',
				'utm_source'      => isset( $submission['utm_source'] ) ? sanitize_text_field( $submission['utm_source'] ) : '',
				'utm_medium'      => isset( $submission['utm_medium'] ) ? sanitize_text_field( $submission['utm_medium'] ) : '',
				'utm_campaign'    => isset( $submission['utm_campaign'] ) ? sanitize_text_field( $submission['utm_campaign'] ) : '',
				'utm_term'        => isset( $submission['utm_term'] ) ? sanitize_text_field( $submission['utm_term'] ) : '',
				'utm_content'     => isset( $submission['utm_content'] ) ? sanitize_text_field( $submission['utm_content'] ) : '',
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new \WP_Error( 'ghlcs_submission_insert_failed', __( 'Could not save form submission.', 'ghl-contact-sync' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update sync details for a submission.
	 *
	 * @param int   $submission_id Submission ID.
	 * @param array $sync          Sync details.
	 * @return bool
	 */
	public function update_sync( $submission_id, array $sync ) {
		global $wpdb;

		$data = array(
			'sync_status'   => isset( $sync['sync_status'] ) ? sanitize_key( $sync['sync_status'] ) : 'pending',
			'sync_attempts' => isset( $sync['sync_attempts'] ) ? (int) $sync['sync_attempts'] : 0,
			'last_error'    => isset( $sync['last_error'] ) ? sanitize_textarea_field( $sync['last_error'] ) : '',
			'updated_at'    => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%d', '%s', '%s' );

		if ( isset( $sync['ghl_contact_id'] ) ) {
			$data['ghl_contact_id'] = sanitize_text_field( $sync['ghl_contact_id'] );
			$formats[]              = '%s';
		}

		return false !== $wpdb->update(
			$this->table_name(),
			$data,
			array( 'id' => (int) $submission_id ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Decode JSON fields.
	 *
	 * @param array $row Submission row.
	 * @return array
	 */
	private function decode_row( array $row ) {
		$row['submission_data'] = ! empty( $row['submission_data'] ) ? json_decode( $row['submission_data'], true ) : array();
		if ( ! is_array( $row['submission_data'] ) ) {
			$row['submission_data'] = array();
		}

		return $row;
	}
}
