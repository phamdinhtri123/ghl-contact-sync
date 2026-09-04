<?php
/**
 * Frontend form renderer.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Forms;

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
		<form class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" method="post" data-form-id="<?php echo esc_attr( $form_id ); ?>">
			<input type="hidden" name="ghlcs_form_id" value="<?php echo esc_attr( $form_id ); ?>">
			<?php wp_nonce_field( 'ghlcs_submit_form_' . $form_id, 'ghlcs_nonce' ); ?>
			<div class="ghlcs-fields">
				<?php foreach ( $form['fields'] as $field ) : ?>
					<?php $this->render_field( $field ); ?>
				<?php endforeach; ?>
			</div>
			<button type="submit" class="ghlcs-submit"><?php echo esc_html( $form['submit_text'] ); ?></button>
			<div class="ghlcs-response" role="status" aria-live="polite"></div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render one field.
	 *
	 * @param array $field Field config.
	 * @return void
	 */
	private function render_field( array $field ) {
		$field_id   = 'ghlcs-' . sanitize_html_class( $field['id'] ) . '-' . wp_rand( 1000, 9999 );
		$type       = $field['type'];
		$name       = sanitize_key( $field['id'] );
		$required   = ! empty( $field['required'] );
		$label      = $field['label'] ?? '';
		$width      = ! empty( $field['width'] ) && 1 === (int) $field['width'] ? '1' : '2';
		$input_type = 'email' === $type ? 'email' : ( 'phone' === $type ? 'tel' : 'text' );
		?>
		<div class="ghlcs-field ghlcs-width-<?php echo esc_attr( $width ); ?>">
			<?php if ( '' !== $label ) : ?>
				<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?><?php echo $required ? ' *' : ''; ?></label>
			<?php endif; ?>
			<?php if ( 'textarea' === $type ) : ?>
				<textarea id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>" <?php required( $required ); ?>></textarea>
			<?php else : ?>
				<input type="<?php echo esc_attr( $input_type ); ?>" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>" <?php required( $required ); ?>>
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
	}
}