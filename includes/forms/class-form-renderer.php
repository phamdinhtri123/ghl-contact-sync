<?php
/**
 * Frontend form renderer.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Forms;

use GHLContactSync\Database\Submission_Repository;
use GHLContactSync\GHL\GHL_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders GHL forms on the frontend.
 */
final class Form_Renderer {

	/**
	 * Form repository.
	 *
	 * @var Form_Repository
	 */
	private $forms;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->forms = new Form_Repository();
	}

	/**
	 * Register shortcode hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_shortcode( 'ghl_form', array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_ghlcs_submit_form', array( $this, 'submit_form' ) );
		add_action( 'wp_ajax_nopriv_ghlcs_submit_form', array( $this, 'submit_form' ) );
	}

	/**
	 * Render shortcode output.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			(array) $atts,
			'ghl_form'
		);

		$form_id = absint( $atts['id'] );

		if ( ! $form_id ) {
			return current_user_can( 'manage_options' ) ? esc_html__( 'GHL form ID is required.', 'ghl-contact-sync' ) : '';
		}

		$form = $this->forms->get( $form_id );

		if ( ! $form ) {
			return current_user_can( 'manage_options' ) ? esc_html__( 'GHL form not found.', 'ghl-contact-sync' ) : '';
		}

		if ( 'active' !== $form['status'] ) {
			return current_user_can( 'manage_options' ) ? esc_html__( 'This GHL form is inactive.', 'ghl-contact-sync' ) : '';
		}

		$this->enqueue_assets();

		$classes = array(
			'ghlcs-form',
			'ghlcs-' . sanitize_html_class( $form['type'] ),
			'ghlcs-layout-' . sanitize_html_class( $form['layout'] ),
			'ghlcs-' . sanitize_html_class( $form['theme'] ),
		);

		if ( ! empty( $form['custom_class'] ) ) {
			$classes[] = sanitize_html_class( $form['custom_class'] );
		}

		ob_start();
		?>
		<form class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" action="" method="post" data-form-id="<?php echo esc_attr( $form_id ); ?>" data-loading-text="<?php echo esc_attr( $form['loading_text'] ); ?>" data-success-behavior="<?php echo esc_attr( $form['success_behavior'] ); ?>" data-redirect-url="<?php echo esc_url( $form['redirect_url'] ); ?>">
			<input type="hidden" name="ghlcs_form_id" value="<?php echo esc_attr( $form_id ); ?>">
			<?php wp_nonce_field( 'ghlcs_submit_form_' . $form_id, 'ghlcs_nonce' ); ?>
			<div class="ghlcs-fields">
				<?php foreach ( $form['fields'] as $field ) : ?>
					<?php $this->render_field( $field, 'newsletter' === $form['type'] ); ?>
				<?php endforeach; ?>
			</div>
			<button type="submit" class="ghlcs-submit"><?php echo esc_html( $form['submit_text'] ); ?></button>
			<div class="ghlcs-response" role="status" aria-live="polite"></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle frontend form submission.
	 *
	 * @return void
	 */
	public function submit_form() {
		$form_id = isset( $_POST['ghlcs_form_id'] ) ? absint( $_POST['ghlcs_form_id'] ) : 0;

		if ( ! $form_id || ! check_ajax_referer( 'ghlcs_submit_form_' . $form_id, 'ghlcs_nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Form security check failed. Please refresh and try again.', 'ghl-contact-sync' ) ),
				403
			);
		}

		$form = $this->forms->get( $form_id );

		if ( ! $form || 'active' !== $form['status'] ) {
			wp_send_json_error(
				array( 'message' => __( 'This form is not available.', 'ghl-contact-sync' ) ),
				404
			);
		}

		$submission_data = $this->sanitize_submission_data( $form );

		if ( is_wp_error( $submission_data ) ) {
			wp_send_json_error(
				array( 'message' => $submission_data->get_error_message() ),
				400
			);
		}

		$repository    = new Submission_Repository();
		$submission_id = $repository->insert(
			array(
				'form_id'       => $form_id,
				'data'          => $submission_data,
				'email'         => $submission_data['email'] ?? '',
				'phone'         => $submission_data['phone'] ?? '',
				'sync_status'   => ! empty( $form['ghl_enabled'] ) ? 'pending' : 'disabled',
				'ip_hash'       => $this->ip_hash(),
				'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '',
				'landing_page'  => isset( $_POST['ghlcs_landing_page'] ) ? wp_unslash( $_POST['ghlcs_landing_page'] ) : '',
				'referrer'      => isset( $_POST['ghlcs_referrer'] ) ? wp_unslash( $_POST['ghlcs_referrer'] ) : '',
				'utm_source'    => isset( $_POST['utm_source'] ) ? wp_unslash( $_POST['utm_source'] ) : '',
				'utm_medium'    => isset( $_POST['utm_medium'] ) ? wp_unslash( $_POST['utm_medium'] ) : '',
				'utm_campaign'  => isset( $_POST['utm_campaign'] ) ? wp_unslash( $_POST['utm_campaign'] ) : '',
				'utm_term'      => isset( $_POST['utm_term'] ) ? wp_unslash( $_POST['utm_term'] ) : '',
				'utm_content'   => isset( $_POST['utm_content'] ) ? wp_unslash( $_POST['utm_content'] ) : '',
			)
		);

		if ( is_wp_error( $submission_id ) ) {
			wp_send_json_error(
				array( 'message' => $form['error_message'] ),
				500
			);
		}

		if ( ! empty( $form['ghl_enabled'] ) ) {
			$this->sync_submission( $form, $submission_data, $submission_id, $repository );
		}

		wp_send_json_success(
			array(
				'message'          => $form['success_message'],
				'success_behavior' => $form['success_behavior'],
				'redirect_url'     => $form['redirect_url'],
			)
		);
	}

	/**
	 * Render one field.
	 *
	 * @param array $field      Field config.
	 * @param bool  $hide_label Whether to hide the visual label.
	 * @return void
	 */
	private function render_field( array $field, $hide_label = false ) {
		$field_id   = 'ghlcs-' . sanitize_html_class( $field['id'] ) . '-' . wp_rand( 1000, 9999 );
		$type       = $field['type'];
		$name       = sanitize_key( $field['id'] );
		$required   = ! empty( $field['required'] );
		$label      = $field['label'] ?? '';
		$aria_label = $hide_label && '' !== $label ? $label : '';
		$width      = ! empty( $field['width'] ) && 1 === (int) $field['width'] ? '1' : '2';
		$input_type = 'email' === $type ? 'email' : ( 'phone' === $type ? 'tel' : 'text' );
		?>
		<div class="ghlcs-field ghlcs-width-<?php echo esc_attr( $width ); ?>">
			<?php if ( '' !== $label && ! $hide_label ) : ?>
				<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?><?php echo $required ? ' *' : ''; ?></label>
			<?php endif; ?>
			<?php if ( 'textarea' === $type ) : ?>
				<textarea id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"<?php echo '' !== $aria_label ? ' aria-label="' . esc_attr( $aria_label ) . '"' : ''; ?><?php echo $required ? ' required' : ''; ?>></textarea>
			<?php else : ?>
				<input type="<?php echo esc_attr( $input_type ); ?>" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"<?php echo '' !== $aria_label ? ' aria-label="' . esc_attr( $aria_label ) . '"' : ''; ?><?php echo $required ? ' required' : ''; ?>>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Enqueue frontend styles.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		wp_enqueue_style( 'ghlcs-frontend', GHLCS_PLUGIN_URL . 'assets/css/frontend.css', array(), GHLCS_VERSION );
		wp_enqueue_script( 'ghlcs-frontend', GHLCS_PLUGIN_URL . 'assets/js/frontend.js', array(), GHLCS_VERSION, true );
		wp_localize_script(
			'ghlcs-frontend',
			'ghlcsFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => 'ghlcs_submit_form',
			)
		);
	}

	/**
	 * Sanitize submitted field values.
	 *
	 * @param array $form Form config.
	 * @return array|\WP_Error
	 */
	private function sanitize_submission_data( array $form ) {
		$data = array();

		foreach ( $form['fields'] as $field ) {
			$name     = sanitize_key( $field['id'] );
			$raw      = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : '';
			$value    = 'textarea' === $field['type'] ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
			$required = ! empty( $field['required'] );

			if ( $required && '' === $value ) {
				return new \WP_Error( 'ghlcs_required_field', __( 'Please complete all required fields.', 'ghl-contact-sync' ) );
			}

			if ( 'email' === $field['type'] ) {
				$value = sanitize_email( $value );

				if ( ( $required && '' === $value ) || ( '' !== $value && ! is_email( $value ) ) ) {
					return new \WP_Error( 'ghlcs_invalid_email', __( 'Please enter a valid email address.', 'ghl-contact-sync' ) );
				}
			}

			$data[ $name ] = $value;
		}

		return $data;
	}

	/**
	 * Sync a saved submission to GHL.
	 *
	 * @param array                 $form            Form config.
	 * @param array                 $submission_data Submitted data.
	 * @param int                   $submission_id   Submission ID.
	 * @param Submission_Repository $repository      Submission repository.
	 * @return void
	 */
	private function sync_submission( array $form, array $submission_data, $submission_id, Submission_Repository $repository ) {
		$payload = array(
			'source' => $form['source'],
		);

		if ( ! empty( $form['tags'] ) ) {
			$payload['tags'] = array_filter( array_map( 'trim', explode( ',', $form['tags'] ) ) );
		}

		foreach ( $form['fields'] as $field ) {
			if ( empty( $field['ghl_mapping'] ) ) {
				continue;
			}

			$name = sanitize_key( $field['id'] );

			if ( isset( $submission_data[ $name ] ) && '' !== $submission_data[ $name ] ) {
				$payload[ $field['ghl_mapping'] ] = $submission_data[ $name ];
			}
		}

		$client = new GHL_Client( get_option( 'ghlcs_settings', array() ) );
		$result = $client->create_contact( $payload );

		if ( is_wp_error( $result ) || empty( $result['success'] ) ) {
			$repository->update_sync(
				$submission_id,
				array(
					'sync_status'   => 'failed',
					'sync_attempts' => 1,
					'last_error'    => is_wp_error( $result ) ? $result->get_error_message() : ( $result['message'] ?? __( 'GHL sync failed.', 'ghl-contact-sync' ) ),
				)
			);

			return;
		}

		$contact_id = $result['body']['contact']['id'] ?? ( $result['body']['id'] ?? '' );

		$repository->update_sync(
			$submission_id,
			array(
				'sync_status'    => 'synced',
				'sync_attempts'  => 1,
				'ghl_contact_id' => $contact_id,
			)
		);
	}

	/**
	 * Get a privacy-preserving hash of the visitor IP address.
	 *
	 * @return string
	 */
	private function ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return '' === $ip ? '' : wp_hash( $ip );
	}
}
