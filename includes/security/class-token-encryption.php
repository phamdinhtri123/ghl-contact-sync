<?php
/**
 * Access token encryption helper.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encrypts and decrypts sensitive API tokens using OpenSSL and WordPress salts.
 */
final class Token_Encryption {

	const CIPHER = 'aes-256-cbc';

	/**
	 * Encrypt an access token.
	 *
	 * @param string $token Raw token.
	 * @return string|\WP_Error
	 */
	public static function encrypt_token( $token ) {
		if ( '' === $token ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) || ! in_array( self::CIPHER, openssl_get_cipher_methods(), true ) ) {
			return new \WP_Error( 'ghlcs_openssl_unavailable', __( 'OpenSSL encryption is unavailable.', 'ghl-contact-sync' ) );
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		$iv        = random_bytes( $iv_length );
		$encrypted = openssl_encrypt( $token, self::CIPHER, self::key(), 0, $iv );

		if ( false === $encrypted ) {
			return new \WP_Error( 'ghlcs_encrypt_failed', __( 'Could not encrypt token.', 'ghl-contact-sync' ) );
		}

		return base64_encode(
			wp_json_encode(
				array(
					'iv'    => base64_encode( $iv ),
					'value' => $encrypted,
				)
			)
		);
	}

	/**
	 * Decrypt an access token.
	 *
	 * @param string $encrypted_token Encrypted token payload.
	 * @return string|\WP_Error
	 */
	public static function decrypt_token( $encrypted_token ) {
		if ( '' === $encrypted_token ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return new \WP_Error( 'ghlcs_openssl_unavailable', __( 'OpenSSL decryption is unavailable.', 'ghl-contact-sync' ) );
		}

		$decoded = json_decode( base64_decode( $encrypted_token ), true );

		if ( empty( $decoded['iv'] ) || empty( $decoded['value'] ) ) {
			return new \WP_Error( 'ghlcs_invalid_token_payload', __( 'Stored token payload is invalid.', 'ghl-contact-sync' ) );
		}

		$token = openssl_decrypt( $decoded['value'], self::CIPHER, self::key(), 0, base64_decode( $decoded['iv'] ) );

		if ( false === $token ) {
			return new \WP_Error( 'ghlcs_decrypt_failed', __( 'Could not decrypt token.', 'ghl-contact-sync' ) );
		}

		return $token;
	}

	/**
	 * Build a stable encryption key from WordPress salts.
	 *
	 * @return string
	 */
	private static function key() {
		$material = '';

		if ( defined( 'AUTH_KEY' ) ) {
			$material .= AUTH_KEY;
		}

		if ( defined( 'SECURE_AUTH_KEY' ) ) {
			$material .= SECURE_AUTH_KEY;
		}

		if ( '' === $material && function_exists( 'wp_salt' ) ) {
			$material = wp_salt( 'auth' );
		}

		return hash( 'sha256', $material . 'ghl-contact-sync-token-key', true );
	}
}
