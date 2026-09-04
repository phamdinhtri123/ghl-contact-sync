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
	private function request( $path, $token, $version = self::API_VERSION ) {
		$response = wp_remote_get(
			trailingslashit( self::BASE_URL ) . ltrim( $path, '/' ),
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
					'Version'       => $version,
				),
			)
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
