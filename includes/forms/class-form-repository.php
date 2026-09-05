<?php
/**
 * Form repository.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and retrieves GHL form configurations.
 */
final class Form_Repository {

	const POST_TYPE = 'ghlcs_form';
	const META_KEY = '_ghlcs_form_config';

	/**
	 * Get a form by ID.
	 *
	 * @param int $form_id Form ID.
	 * @return array|null
	 */
	public function get( $form_id ) {
		$post = get_post( (int) $form_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$config = get_post_meta( $post->ID, self::META_KEY, true );
		$config = is_array( $config ) ? $config : array();

		return $this->normalize_config( $config, $post );
	}

	/**
	 * Get all forms.
	 *
	 * @return array
	 */
	public function all() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$forms = array();

		foreach ( $posts as $post ) {
			$forms[] = $this->normalize_config( get_post_meta( $post->ID, self::META_KEY, true ), $post );
		}

		return $forms;
	}

	/**
	 * Get active external form configurations.
	 *
	 * @return array
	 */
	public function active_external() {
		$forms = $this->all();

		return array_values(
			array_filter(
				$forms,
				static function ( $form ) {
					return 'active' === $form['status'] && 'external' === $form['render_mode'] && ! empty( $form['external_container'] );
				}
			)
		);
	}

	/**
	 * Save a form.
	 *
	 * @param array $config Form config.
	 * @param int   $form_id Existing form ID, or 0 for new.
	 * @return int|\WP_Error
	 */
	public function save( array $config, $form_id = 0 ) {
		$config = $this->sanitize_config( $config );

		$post_data = array(
			'post_title'  => $config['name'],
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
		);

		if ( $form_id > 0 ) {
			$post_data['ID'] = (int) $form_id;
			$post_id         = wp_update_post( wp_slash( $post_data ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $post_data ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, self::META_KEY, $config );

		return (int) $post_id;
	}

	/**
	 * Duplicate a form.
	 *
	 * @param int $form_id Source form ID.
	 * @return int|\WP_Error
	 */
	public function duplicate( $form_id ) {
		$form = $this->get( $form_id );

		if ( ! $form ) {
			return new \WP_Error( 'ghlcs_form_not_found', __( 'Form not found.', 'ghl-contact-sync' ) );
		}

		unset( $form['id'], $form['created_at'] );
		$form['name'] = sprintf(
			/* translators: %s: form name. */
			__( '%s Copy', 'ghl-contact-sync' ),
			$form['name']
		);

		return $this->save( $form );
	}

	/**
	 * Delete a form.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public function delete( $form_id ) {
		$post = get_post( (int) $form_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		return (bool) wp_delete_post( $post->ID, true );
	}

	/**
	 * Count submissions for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return int
	 */
	public function count_submissions( $form_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ghl_contact_sync_submissions';

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE form_id = %d", (int) $form_id )
		);
	}

	/**
	 * Get default config for a form type.
	 *
	 * @param string $type Form type.
	 * @return array
	 */
	public function defaults( $type = 'newsletter' ) {
		$type = 'contact' === $type ? 'contact' : 'newsletter';

		$base = array(
			'name'             => '',
			'render_mode'      => 'plugin',
			'type'             => $type,
			'status'           => 'active',
			'layout'           => 'newsletter' === $type ? 'inline' : 'grid',
			'theme'            => 'theme-1',
			'custom_class'     => '',
			'submit_text'      => 'newsletter' === $type ? __( 'Subscribe', 'ghl-contact-sync' ) : __( 'Submit', 'ghl-contact-sync' ),
			'loading_text'     => __( 'Submitting...', 'ghl-contact-sync' ),
			'success_behavior' => 'message',
			'success_message'  => 'newsletter' === $type ? __( 'Thanks for subscribing!', 'ghl-contact-sync' ) : __( "Thanks! We've received your message.", 'ghl-contact-sync' ),
			'error_message'    => __( 'Something went wrong. Please try again.', 'ghl-contact-sync' ),
			'redirect_url'     => '',
			'ghl_enabled'      => 1,
			'tags'             => 'newsletter' === $type ? 'newsletter' : 'website-lead',
			'source'           => 'newsletter' === $type ? __( 'Website Newsletter', 'ghl-contact-sync' ) : __( 'Website Contact Form', 'ghl-contact-sync' ),
			'fields'           => array(),
			'external_container' => '',
			'external_submit'  => '',
			'external_is_popup' => 0,
			'external_fields'  => array(),
		);

		if ( 'newsletter' === $type ) {
			$base['fields'] = array(
				array(
					'id'          => 'email',
					'type'        => 'email',
					'label'       => __( 'Email', 'ghl-contact-sync' ),
					'placeholder' => __( 'Enter your email address', 'ghl-contact-sync' ),
					'required'    => true,
					'width'       => 2,
					'ghl_mapping' => 'email',
				),
			);
		} else {
			$base['fields'] = array(
				array( 'id' => 'first_name', 'type' => 'text', 'label' => __( 'First Name', 'ghl-contact-sync' ), 'placeholder' => __( 'First Name', 'ghl-contact-sync' ), 'required' => false, 'width' => 1, 'ghl_mapping' => 'firstName' ),
				array( 'id' => 'last_name', 'type' => 'text', 'label' => __( 'Last Name', 'ghl-contact-sync' ), 'placeholder' => __( 'Last Name', 'ghl-contact-sync' ), 'required' => false, 'width' => 1, 'ghl_mapping' => 'lastName' ),
				array( 'id' => 'email', 'type' => 'email', 'label' => __( 'Email', 'ghl-contact-sync' ), 'placeholder' => __( 'Email Address', 'ghl-contact-sync' ), 'required' => true, 'width' => 2, 'ghl_mapping' => 'email' ),
				array( 'id' => 'phone', 'type' => 'phone', 'label' => __( 'Phone', 'ghl-contact-sync' ), 'placeholder' => __( 'Phone Number', 'ghl-contact-sync' ), 'required' => false, 'width' => 2, 'ghl_mapping' => 'phone' ),
				array( 'id' => 'message', 'type' => 'textarea', 'label' => __( 'Message', 'ghl-contact-sync' ), 'placeholder' => __( 'How can we help?', 'ghl-contact-sync' ), 'required' => false, 'width' => 2, 'ghl_mapping' => '' ),
			);
		}

		return $base;
	}

	/**
	 * Normalize stored config.
	 *
	 * @param array|mixed $config Stored config.
	 * @param \WP_Post   $post Form post.
	 * @return array
	 */
	private function normalize_config( $config, \WP_Post $post ) {
		$config = is_array( $config ) ? $config : array();
		$type   = ! empty( $config['type'] ) && 'contact' === $config['type'] ? 'contact' : 'newsletter';
		$config = wp_parse_args( $config, $this->defaults( $type ) );
		$config['theme'] = 'theme-1';
		$config['render_mode'] = ! empty( $config['render_mode'] ) && 'external' === $config['render_mode'] ? 'external' : 'plugin';
		$config['external_fields'] = $this->normalize_external_fields( $config['external_fields'] ?? array() );

		$config['id']         = (int) $post->ID;
		$config['name']       = $post->post_title;
		$config['created_at'] = $post->post_date;

		return $config;
	}

	/**
	 * Sanitize posted config.
	 *
	 * @param array $config Raw config.
	 * @return array
	 */
	private function sanitize_config( array $config ) {
		$type = ! empty( $config['type'] ) && 'contact' === $config['type'] ? 'contact' : 'newsletter';

		$clean = wp_parse_args( array(), $this->defaults( $type ) );

		$clean['name']             = ! empty( $config['name'] ) ? sanitize_text_field( $config['name'] ) : __( 'Untitled Form', 'ghl-contact-sync' );
		$clean['render_mode']      = ! empty( $config['render_mode'] ) && 'external' === $config['render_mode'] ? 'external' : 'plugin';
		$clean['type']             = $type;
		$clean['status']           = ! empty( $config['status'] ) && 'inactive' === $config['status'] ? 'inactive' : 'active';
		$clean['layout']           = ! empty( $config['layout'] ) ? sanitize_key( $config['layout'] ) : $clean['layout'];
		$clean['theme']            = 'theme-1';
		$clean['custom_class']     = ! empty( $config['custom_class'] ) ? sanitize_html_class( $config['custom_class'] ) : '';
		$clean['submit_text']      = ! empty( $config['submit_text'] ) ? sanitize_text_field( $config['submit_text'] ) : $clean['submit_text'];
		$clean['loading_text']     = ! empty( $config['loading_text'] ) ? sanitize_text_field( $config['loading_text'] ) : $clean['loading_text'];
		$clean['success_behavior'] = ! empty( $config['success_behavior'] ) && 'redirect' === $config['success_behavior'] ? 'redirect' : 'message';
		$clean['success_message']  = ! empty( $config['success_message'] ) ? sanitize_text_field( $config['success_message'] ) : $clean['success_message'];
		$clean['error_message']    = ! empty( $config['error_message'] ) ? sanitize_text_field( $config['error_message'] ) : $clean['error_message'];
		$clean['redirect_url']     = ! empty( $config['redirect_url'] ) ? esc_url_raw( $config['redirect_url'] ) : '';
		$clean['ghl_enabled']      = empty( $config['ghl_enabled'] ) ? 0 : 1;
		$clean['tags']             = ! empty( $config['tags'] ) ? sanitize_text_field( $config['tags'] ) : '';
		$clean['source']           = ! empty( $config['source'] ) ? sanitize_text_field( $config['source'] ) : '';
		$clean['fields']           = $this->defaults( $type )['fields'];
		$clean['external_container'] = ! empty( $config['external_container'] ) ? sanitize_text_field( $config['external_container'] ) : '';
		$clean['external_submit']  = ! empty( $config['external_submit'] ) ? sanitize_text_field( $config['external_submit'] ) : '';
		$clean['external_is_popup'] = empty( $config['external_is_popup'] ) ? 0 : 1;
		$clean['external_fields']  = $this->normalize_external_fields( $config['external_fields'] ?? array() );

		return $clean;
	}

	/**
	 * Normalize external selector fields.
	 *
	 * @param array $fields External field config.
	 * @return array
	 */
	private function normalize_external_fields( array $fields ) {
		$normalized = array();
		$allowed    = array(
			'email'      => 'email',
			'phone'      => 'phone',
			'first_name' => 'firstName',
			'last_name'  => 'lastName',
			'message'    => '',
			'custom'     => '',
		);

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$key      = ! empty( $field['key'] ) ? sanitize_key( $field['key'] ) : 'custom';
			$key      = isset( $allowed[ $key ] ) ? $key : 'custom';
			$selector = ! empty( $field['selector'] ) ? sanitize_text_field( $field['selector'] ) : '';

			if ( '' === $selector ) {
				continue;
			}

			$mapping = isset( $field['ghl_mapping'] ) ? sanitize_text_field( $field['ghl_mapping'] ) : $allowed[ $key ];

			$normalized[] = array(
				'key'         => $key,
				'label'       => ! empty( $field['label'] ) ? sanitize_text_field( $field['label'] ) : ucfirst( str_replace( '_', ' ', $key ) ),
				'selector'    => $selector,
				'required'    => ! empty( $field['required'] ),
				'ghl_mapping' => $mapping,
			);
		}

		if ( empty( $normalized ) ) {
			$normalized[] = array(
				'key'         => 'email',
				'label'       => __( 'Email', 'ghl-contact-sync' ),
				'selector'    => '',
				'required'    => true,
				'ghl_mapping' => 'email',
			);
		}

		return $normalized;
	}
}
