<?php
/**
 * GoHighLevel API client.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\GHL;

use GHLContactSync\Security\Token_Encryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes GoHighLevel API communication.
 */
final class GHL_Client {

	const BASE_URL = 'https://services.leadconnectorhq.com/';
	const API_VERSION = '2021-07-28';
	const LOCATION_API_VERSION = 'v3';

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Test configured GHL credentials.
	 *
	 * @return array
	 */
	public function test_connection() {
		$checked_at  = current_time( 'timestamp' );
		$location_id = $this->get_location_id();
		$token       = $this->get_access_token();

		$result = array(
			'connected'           => false,
			'location_name'       => __( 'Unable to verify', 'ghl-contact-sync' ),
			'location_id'         => $location_id,
			'contacts_accessible' => false,
			'error'               => '',
			'checked_at'          => $checked_at,
		);

		if ( '' === $location_id ) {
			$result['error'] = __( 'Location ID is required.', 'ghl-contact-sync' );
			return $result;
		}

		if ( is_wp_error( $token ) ) {
			$result['error'] = __( 'Stored access token could not be decrypted.', 'ghl-contact-sync' );
			return $result;
		}

		if ( '' === $token ) {
			$result['error'] = __( 'Access Token is required.', 'ghl-contact-sync' );
			return $result;
		}

		$contacts_response = $this->request(
			'contacts/?locationId=' . rawurlencode( $location_id ) . '&limit=1',
			$token
		);

		if ( is_wp_error( $contacts_response ) ) {
			$result['error'] = $contacts_response->get_error_message();
			return $result;
		}

		if ( ! $contacts_response['success'] ) {
			$result['error'] = $contacts_response['message'];
			return $result;
		}

		$result['connected']           = true;
		$result['contacts_accessible'] = true;

		$location_response = $this->request( 'locations/' . rawurlencode( $location_id ), $token, self::LOCATION_API_VERSION );

		if ( is_array( $location_response ) && ! empty( $location_response['success'] ) ) {
			$result['location_name'] = $this->extract_location_name( $location_response['body'] );
		} else {
			$result['location_name'] = __( 'Verified', 'ghl-contact-sync' );
		}

		return $result;
	}

	/**
	 * Create a contact in GoHighLevel.
	 *
	 * @param array $contact Contact payload.
	 * @return array|\WP_Error
	 */
	public function create_contact( array $contact ) {
		$location_id = $this->get_location_id();
		$token       = $this->get_access_token();

		if ( '' === $location_id ) {
			return new \WP_Error( 'ghlcs_missing_location_id', __( 'Location ID is required.', 'ghl-contact-sync' ) );
		}

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		if ( '' === $token ) {
			return new \WP_Error( 'ghlcs_missing_access_token', __( 'Access Token is required.', 'ghl-contact-sync' ) );
		}

		$payload = array_merge(
			$contact,
			array(
				'locationId' => $location_id,
			)
		);

		return $this->request( 'contacts/', $token, self::LOCATION_API_VERSION, 'POST', $payload );
	}

	/**
	 * Upsert a contact using the current HighLevel Contacts API.
	 *
	 * @param array $contact Contact payload.
	 * @return array|\WP_Error
	 */
	public function upsert_contact( array $contact ) {
		$location_id = $this->get_location_id();
		$token       = $this->get_access_token();

		if ( '' === $location_id ) {
			return new \WP_Error( 'ghlcs_missing_location_id', __( 'Location ID is required.', 'ghl-contact-sync' ) );
		}

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		if ( '' === $token ) {
			return new \WP_Error( 'ghlcs_missing_access_token', __( 'Access Token is required.', 'ghl-contact-sync' ) );
		}

		unset( $contact['tags'] );

		$payload = array_merge(
			$contact,
			array(
				'locationId' => $location_id,
			)
		);

		return $this->request( 'contacts/upsert', $token, self::LOCATION_API_VERSION, 'POST', $payload );
	}

	/**
	 * Save a contact by exact email match only.
	 *
	 * @param array $contact Contact payload.
	 * @return array|\WP_Error
	 */
	public function save_contact_by_email( array $contact ) {
		$email = ! empty( $contact['email'] ) ? sanitize_email( $contact['email'] ) : '';

		if ( '' === $email || ! is_email( $email ) ) {
			return new \WP_Error( 'ghlcs_missing_contact_email', __( 'A valid email is required to sync a GHL contact.', 'ghl-contact-sync' ) );
		}

		$tags = array();
		if ( ! empty( $contact['tags'] ) && is_array( $contact['tags'] ) ) {
			$tags = array_values( array_filter( array_map( 'sanitize_text_field', $contact['tags'] ) ) );
		}

		$existing = $this->find_contact_by_email( $email );

		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		if ( $existing ) {
			$contact_id = $this->extract_contact_id( $existing );

			if ( '' === $contact_id ) {
				return new \WP_Error( 'ghlcs_missing_contact_id', __( 'Matched GHL contact did not include an ID.', 'ghl-contact-sync' ) );
			}

			unset( $contact['tags'] );

			$result = $this->update_contact( $contact_id, $contact );

			if ( ! empty( $contact['phone'] ) && $this->is_duplicate_contact_error( $result ) ) {
				unset( $contact['phone'] );
				$result = $this->update_contact( $contact_id, $contact );
			}

			if ( is_wp_error( $result ) || empty( $result['success'] ) ) {
				return $result;
			}

			if ( ! empty( $tags ) ) {
				$tag_result = $this->add_contact_tags( $contact_id, $tags );

				if ( is_wp_error( $tag_result ) || empty( $tag_result['success'] ) ) {
					return is_wp_error( $tag_result ) ? $tag_result : $tag_result;
				}
			}

			if ( empty( $result['body']['contact']['id'] ) && empty( $result['body']['id'] ) ) {
				$result['body']['contact']['id'] = $contact_id;
			}

			return $result;
		}

		$result = $this->create_contact( $contact );

		if ( ! empty( $contact['phone'] ) && $this->is_duplicate_contact_error( $result ) ) {
			unset( $contact['phone'] );
			$result = $this->create_contact( $contact );
		}

		return $result;
	}

	/**
	 * Update an existing GHL contact.
	 *
	 * @param string $contact_id GHL contact ID.
	 * @param array  $contact Contact payload.
	 * @return array|\WP_Error
	 */
	public function update_contact( $contact_id, array $contact ) {
		$token = $this->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		if ( '' === $token ) {
			return new \WP_Error( 'ghlcs_missing_access_token', __( 'Access Token is required.', 'ghl-contact-sync' ) );
		}

		$contact_id = sanitize_text_field( $contact_id );

		if ( '' === $contact_id ) {
			return new \WP_Error( 'ghlcs_missing_contact_id', __( 'Contact ID is required.', 'ghl-contact-sync' ) );
		}

		return $this->request( 'contacts/' . rawurlencode( $contact_id ), $token, self::LOCATION_API_VERSION, 'PUT', $contact );
	}

	/**
	 * Find an existing contact by exact email match.
	 *
	 * @param string $email Email address.
	 * @return array|null|\WP_Error
	 */
	public function find_contact_by_email( $email ) {
		$location_id = $this->get_location_id();
		$token       = $this->get_access_token();
		$email       = sanitize_email( $email );

		if ( '' === $location_id ) {
			return new \WP_Error( 'ghlcs_missing_location_id', __( 'Location ID is required.', 'ghl-contact-sync' ) );
		}

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		if ( '' === $token ) {
			return new \WP_Error( 'ghlcs_missing_access_token', __( 'Access Token is required.', 'ghl-contact-sync' ) );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			return new \WP_Error( 'ghlcs_missing_contact_email', __( 'A valid email is required to sync a GHL contact.', 'ghl-contact-sync' ) );
		}

		$response = $this->request(
			'contacts/lookup?' . http_build_query(
				array(
					'locationId' => $location_id,
					'email'      => $email,
					'limit'      => 20,
				),
				'',
				'&',
				PHP_QUERY_RFC3986
			),
			$token,
			self::LOCATION_API_VERSION
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['success'] ) ) {
			return new \WP_Error( 'ghlcs_contact_search_failed', $response['message'] ?? __( 'GHL contact search failed.', 'ghl-contact-sync' ) );
		}

		foreach ( $this->extract_contacts( $response['body'] ) as $contact ) {
			if ( $this->contact_has_email( $contact, $email ) ) {
				return $contact;
			}
		}

		return null;
	}

	/**
	 * Fetch contact custom fields for the configured location.
	 *
	 * @return array|\WP_Error
	 */
	public function get_contact_custom_fields() {
		$location_id = $this->get_location_id();
		$token       = $this->get_access_token();

		if ( '' === $location_id ) {
			return new \WP_Error( 'ghlcs_missing_location_id', __( 'Location ID is required.', 'ghl-contact-sync' ) );
		}

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		if ( '' === $token ) {
			return new \WP_Error( 'ghlcs_missing_access_token', __( 'Access Token is required.', 'ghl-contact-sync' ) );
		}

		return $this->request( 'locations/' . rawurlencode( $location_id ) . '/customFields?model=contact', $token, self::LOCATION_API_VERSION );
	}

	/**
	 * Create a contact custom field for the configured location.
	 *
	 * @param string $name Field name.
	 * @param string $data_type Field data type.
	 * @return array|\WP_Error
	 */
	public function create_contact_custom_field( $name, $data_type = 'TEXT' ) {
		$location_id = $this->get_location_id();
		$token       = $this->get_access_token();

		if ( '' === $location_id ) {
			return new \WP_Error( 'ghlcs_missing_location_id', __( 'Location ID is required.', 'ghl-contact-sync' ) );
		}

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		if ( '' === $token ) {
			return new \WP_Error( 'ghlcs_missing_access_token', __( 'Access Token is required.', 'ghl-contact-sync' ) );
		}

		return $this->request(
			'locations/' . rawurlencode( $location_id ) . '/customFields',
			$token,
			self::LOCATION_API_VERSION,
			'POST',
			array(
				'name'        => sanitize_text_field( $name ),
				'dataType'    => sanitize_text_field( $data_type ),
				'placeholder' => sanitize_text_field( $name ),
				'position'    => 0,
				'model'       => 'contact',
			)
		);
	}

	/**
	 * Find or create a contact custom field and return its ID.
	 *
	 * @param string $name Field name.
	 * @param string $data_type Field data type.
	 * @return string|\WP_Error
	 */
	public function get_or_create_contact_custom_field_id( $name, $data_type = 'TEXT' ) {
		$name = sanitize_text_field( $name );

		if ( '' === $name ) {
			return new \WP_Error( 'ghlcs_missing_custom_field_name', __( 'Custom field name is required.', 'ghl-contact-sync' ) );
		}

		$fields_response = $this->get_contact_custom_fields();

		if ( is_wp_error( $fields_response ) ) {
			return $fields_response;
		}

		if ( empty( $fields_response['success'] ) ) {
			return new \WP_Error( 'ghlcs_custom_fields_fetch_failed', $fields_response['message'] ?? __( 'Could not fetch GHL custom fields.', 'ghl-contact-sync' ) );
		}

		$field_id = $this->find_custom_field_id_by_name( $this->extract_custom_fields( $fields_response['body'] ), $name );

		if ( '' !== $field_id ) {
			return $field_id;
		}

		$created = $this->create_contact_custom_field( $name, $data_type );

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		if ( empty( $created['success'] ) ) {
			return new \WP_Error( 'ghlcs_custom_field_create_failed', $created['message'] ?? __( 'Could not create GHL custom field.', 'ghl-contact-sync' ) );
		}

		$field = $created['body']['customField'] ?? ( $created['body']['field'] ?? $created['body'] );
		if ( is_array( $field ) && ! empty( $field['id'] ) ) {
			return sanitize_text_field( $field['id'] );
		}

		return new \WP_Error( 'ghlcs_custom_field_id_missing', __( 'GHL custom field was created but no field ID was returned.', 'ghl-contact-sync' ) );
	}

	/**
	 * Add tags to one contact without replacing existing tags.
	 *
	 * @param string $contact_id GHL contact ID.
	 * @param array  $tags Tags to add.
	 * @return array|\WP_Error
	 */
	public function add_contact_tags( $contact_id, array $tags ) {
		return $this->contact_tags_request( $contact_id, $tags, 'POST' );
	}

	/**
	 * Remove tags from one contact without touching unrelated tags.
	 *
	 * @param string $contact_id GHL contact ID.
	 * @param array  $tags Tags to remove.
	 * @return array|\WP_Error
	 */
	public function remove_contact_tags( $contact_id, array $tags ) {
		return $this->contact_tags_request( $contact_id, $tags, 'DELETE' );
	}

	/**
	 * Send a contact tag request.
	 *
	 * @param string $contact_id GHL contact ID.
	 * @param array  $tags Tags.
	 * @param string $method HTTP method.
	 * @return array|\WP_Error
	 */
	private function contact_tags_request( $contact_id, array $tags, $method ) {
		$token = $this->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		if ( '' === $token ) {
			return new \WP_Error( 'ghlcs_missing_access_token', __( 'Access Token is required.', 'ghl-contact-sync' ) );
		}

		$contact_id = sanitize_text_field( $contact_id );
		$tags       = array_values( array_filter( array_map( 'sanitize_text_field', $tags ) ) );

		if ( '' === $contact_id || empty( $tags ) ) {
			return new \WP_Error( 'ghlcs_invalid_tag_request', __( 'Contact ID and tag are required.', 'ghl-contact-sync' ) );
		}

		return $this->request( 'contacts/' . rawurlencode( $contact_id ) . '/tags', $token, self::LOCATION_API_VERSION, $method, array( 'tags' => $tags ) );
	}

	/**
	 * Get configured Location ID.
	 *
	 * @return string
	 */
	private function get_location_id() {
		if ( defined( 'GHL_CONTACT_SYNC_LOCATION_ID' ) ) {
			return sanitize_text_field( GHL_CONTACT_SYNC_LOCATION_ID );
		}

		return ! empty( $this->settings['location_id'] ) ? sanitize_text_field( $this->settings['location_id'] ) : '';
	}

	/**
	 * Get configured Access Token.
	 *
	 * @return string|\WP_Error
	 */
	private function get_access_token() {
		if ( defined( 'GHL_CONTACT_SYNC_ACCESS_TOKEN' ) ) {
			return trim( (string) GHL_CONTACT_SYNC_ACCESS_TOKEN );
		}

		if ( empty( $this->settings['access_token_encrypted'] ) ) {
			return '';
		}

		$token = Token_Encryption::decrypt_token( $this->settings['access_token_encrypted'] );

		return is_wp_error( $token ) ? $token : trim( $token );
	}

	/**
	 * Perform a GHL API request.
	 *
	 * @param string $path API path relative to base URL.
	 * @param string $token Access token.
	 * @return array|\WP_Error
	 */
	private function request( $path, $token, $version = self::API_VERSION, $method = 'GET', array $body = array() ) {
		$args = array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
				'Version'       => $version,
			),
		);

		if ( in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			$args['method']                  = strtoupper( $method );
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request(
			trailingslashit( self::BASE_URL ) . ltrim( $path, '/' ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'ghlcs_connection_failed', __( 'GHL API connection timeout or network error.', 'ghl-contact-sync' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 ) {
			return array(
				'success' => true,
				'code'    => $code,
				'body'    => is_array( $body ) ? $body : array(),
				'message' => '',
			);
		}

		return array(
			'success' => false,
			'code'    => $code,
			'body'    => is_array( $body ) ? $body : array(),
			'message' => $this->friendly_error_message( $code, is_array( $body ) ? $body : array() ),
		);
	}

	/**
	 * Extract a readable location name from an API response.
	 *
	 * @param array $body Decoded response body.
	 * @return string
	 */
	private function extract_location_name( array $body ) {
		if ( ! empty( $body['location']['name'] ) ) {
			return sanitize_text_field( $body['location']['name'] );
		}

		if ( ! empty( $body['location']['business']['name'] ) ) {
			return sanitize_text_field( $body['location']['business']['name'] );
		}

		if ( ! empty( $body['name'] ) ) {
			return sanitize_text_field( $body['name'] );
		}

		return __( 'Verified', 'ghl-contact-sync' );
	}

	/**
	 * Extract contacts from a contacts response body.
	 *
	 * @param array $body Response body.
	 * @return array
	 */
	private function extract_contacts( array $body ) {
		if ( isset( $body['contacts'] ) && is_array( $body['contacts'] ) ) {
			return $body['contacts'];
		}

		if ( isset( $body['contact'] ) && is_array( $body['contact'] ) ) {
			return array( $body['contact'] );
		}

		if ( isset( $body[0] ) && is_array( $body[0] ) ) {
			return $body;
		}

		return array();
	}

	/**
	 * Extract contact custom fields from known response shapes.
	 *
	 * @param array $body Response body.
	 * @return array
	 */
	private function extract_custom_fields( array $body ) {
		$source = $body['customFields'] ?? ( $body['fields'] ?? array() );

		return is_array( $source ) ? $source : array();
	}

	/**
	 * Find a custom field ID by display name.
	 *
	 * @param array  $fields Fields.
	 * @param string $name Field name.
	 * @return string
	 */
	private function find_custom_field_id_by_name( array $fields, $name ) {
		$target = strtolower( trim( (string) $name ) );

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['id'] ) ) {
				continue;
			}

			$field_name = $field['name'] ?? ( $field['fieldKey'] ?? '' );

			if ( $target === strtolower( trim( (string) $field_name ) ) ) {
				return sanitize_text_field( $field['id'] );
			}
		}

		return '';
	}

	/**
	 * Extract a contact ID from known response shapes.
	 *
	 * @param array $contact Contact data.
	 * @return string
	 */
	private function extract_contact_id( array $contact ) {
		if ( ! empty( $contact['id'] ) ) {
			return sanitize_text_field( $contact['id'] );
		}

		if ( ! empty( $contact['contactId'] ) ) {
			return sanitize_text_field( $contact['contactId'] );
		}

		return '';
	}

	/**
	 * Check whether contact data contains an exact email match.
	 *
	 * @param array  $contact Contact data.
	 * @param string $email Email address.
	 * @return bool
	 */
	private function contact_has_email( array $contact, $email ) {
		$email = strtolower( sanitize_email( $email ) );

		foreach ( array( 'email', 'Email' ) as $key ) {
			if ( ! empty( $contact[ $key ] ) && is_string( $contact[ $key ] ) && strtolower( sanitize_email( $contact[ $key ] ) ) === $email ) {
				return true;
			}
		}

		if ( ! empty( $contact['additionalEmails'] ) && is_array( $contact['additionalEmails'] ) ) {
			foreach ( $contact['additionalEmails'] as $additional_email ) {
				if ( is_array( $additional_email ) ) {
					$additional_email = $additional_email['email'] ?? '';
				}

				if ( is_string( $additional_email ) && strtolower( sanitize_email( $additional_email ) ) === $email ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check whether a GHL response failed because duplicate contacts are blocked.
	 *
	 * @param mixed $result API result.
	 * @return bool
	 */
	private function is_duplicate_contact_error( $result ) {
		if ( is_wp_error( $result ) || ! is_array( $result ) || ! empty( $result['success'] ) ) {
			return false;
		}

		$message = strtolower( (string) ( $result['message'] ?? '' ) );
		$body    = ! empty( $result['body'] ) && is_array( $result['body'] ) ? strtolower( wp_json_encode( $result['body'] ) ) : '';
		$text    = $message . ' ' . $body;

		return false !== strpos( $text, 'duplicate' ) || false !== strpos( $text, 'duplicated contact' );
	}

	/**
	 * Convert API errors into safe admin-facing messages.
	 *
	 * @param int   $code HTTP status code.
	 * @param array $body Decoded response body.
	 * @return string
	 */
	private function friendly_error_message( $code, array $body ) {
		if ( 401 === $code ) {
			return __( 'Invalid Access Token. GHL API returned HTTP 401.', 'ghl-contact-sync' );
		}

		if ( 403 === $code ) {
			return __( 'Access Token does not have the required Contacts permission.', 'ghl-contact-sync' );
		}

		if ( 404 === $code ) {
			return __( 'Location ID not found or Contacts API endpoint is unavailable.', 'ghl-contact-sync' );
		}

		if ( 429 === $code ) {
			return __( 'GHL API rate limit reached. Please try again later.', 'ghl-contact-sync' );
		}

		if ( $code >= 500 ) {
			return __( 'GHL API is currently unavailable.', 'ghl-contact-sync' );
		}

		if ( ! empty( $body['message'] ) && is_string( $body['message'] ) ) {
			return sanitize_text_field( $body['message'] );
		}

		return sprintf(
			/* translators: %d: HTTP status code. */
			__( 'GHL API returned HTTP %d.', 'ghl-contact-sync' ),
			$code
		);
	}
}
