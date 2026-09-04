<?php
/**
 * Form editor admin page.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Admin;

use GHLContactSync\Forms\Form_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and saves the form editor.
 */
final class Form_Editor {

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
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_post_ghlcs_save_form', array( $this, 'save' ) );
	}

	/**
	 * Save a form.
	 *
	 * @return void
	 */
	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to save forms.', 'ghl-contact-sync' ) );
		}

		check_admin_referer( 'ghlcs_save_form' );

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$config  = array(
			'name'             => isset( $_POST['form_name'] ) ? wp_unslash( $_POST['form_name'] ) : '',
			'type'             => isset( $_POST['form_type'] ) ? wp_unslash( $_POST['form_type'] ) : 'newsletter',
			'status'           => isset( $_POST['form_status'] ) ? wp_unslash( $_POST['form_status'] ) : 'active',
			'layout'           => isset( $_POST['layout'] ) ? wp_unslash( $_POST['layout'] ) : '',
			'theme'            => isset( $_POST['theme'] ) ? wp_unslash( $_POST['theme'] ) : 'theme-1',
			'custom_class'     => isset( $_POST['custom_class'] ) ? wp_unslash( $_POST['custom_class'] ) : '',
			'submit_text'      => isset( $_POST['submit_text'] ) ? wp_unslash( $_POST['submit_text'] ) : '',
			'loading_text'     => isset( $_POST['loading_text'] ) ? wp_unslash( $_POST['loading_text'] ) : '',
			'success_behavior' => isset( $_POST['success_behavior'] ) ? wp_unslash( $_POST['success_behavior'] ) : 'message',
			'success_message'  => isset( $_POST['success_message'] ) ? wp_unslash( $_POST['success_message'] ) : '',
			'error_message'    => isset( $_POST['error_message'] ) ? wp_unslash( $_POST['error_message'] ) : '',
			'redirect_url'     => isset( $_POST['redirect_url'] ) ? wp_unslash( $_POST['redirect_url'] ) : '',
			'ghl_enabled'      => isset( $_POST['ghl_enabled'] ) ? 1 : 0,
			'tags'             => isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : '',
			'source'           => isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : '',
		);

		$saved_id = $this->forms->save( $config, $form_id );

		if ( is_wp_error( $saved_id ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'          => 'ghl-contact-sync-add',
						'form_id'       => $form_id,
						'ghlcs_message' => 'save_failed',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'ghl-contact-sync-add',
					'form_id'       => $saved_id,
					'ghlcs_message' => 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ghl-contact-sync' ) );
		}

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$form    = $form_id ? $this->forms->get( $form_id ) : $this->forms->defaults( 'newsletter' );
		$message = isset( $_GET['ghlcs_message'] ) ? sanitize_key( wp_unslash( $_GET['ghlcs_message'] ) ) : '';

		if ( ! $form ) {
			$form = $this->forms->defaults( 'newsletter' );
		}

		?>
		<div class="wrap ghlcs-admin">
			<h1><?php echo esc_html( $form_id ? __( 'Edit Form', 'ghl-contact-sync' ) : __( 'Add New Form', 'ghl-contact-sync' ) ); ?></h1>

			<?php if ( 'saved' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Form saved.', 'ghl-contact-sync' ); ?></p></div>
			<?php elseif ( 'save_failed' === $message ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Form could not be saved.', 'ghl-contact-sync' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ghlcs_save_form">
				<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
				<?php wp_nonce_field( 'ghlcs_save_form' ); ?>

				<div class="ghlcs-panel">
					<h2><?php esc_html_e( 'General', 'ghl-contact-sync' ); ?></h2>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="ghlcs-form-name"><?php esc_html_e( 'Form Name', 'ghl-contact-sync' ); ?></label></th>
								<td><input type="text" id="ghlcs-form-name" name="form_name" class="regular-text" value="<?php echo esc_attr( $form['name'] ); ?>" required></td>
							</tr>
							<tr>
								<th scope="row"><label for="ghlcs-form-type"><?php esc_html_e( 'Form Type', 'ghl-contact-sync' ); ?></label></th>
								<td>
									<select id="ghlcs-form-type" name="form_type">
										<option value="newsletter" <?php selected( $form['type'], 'newsletter' ); ?>><?php esc_html_e( 'Newsletter', 'ghl-contact-sync' ); ?></option>
										<option value="contact" <?php selected( $form['type'], 'contact' ); ?>><?php esc_html_e( 'Contact Form', 'ghl-contact-sync' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Changing the type applies the default field set for that form type when saved.', 'ghl-contact-sync' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="ghlcs-form-status"><?php esc_html_e( 'Status', 'ghl-contact-sync' ); ?></label></th>
								<td>
									<select id="ghlcs-form-status" name="form_status">
										<option value="active" <?php selected( $form['status'], 'active' ); ?>><?php esc_html_e( 'Active', 'ghl-contact-sync' ); ?></option>
										<option value="inactive" <?php selected( $form['status'], 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'ghl-contact-sync' ); ?></option>
									</select>
								</td>
							</tr>
							<?php if ( $form_id ) : ?>
								<tr>
									<th scope="row"><?php esc_html_e( 'Shortcode', 'ghl-contact-sync' ); ?></th>
									<td><code>[ghl_form id="<?php echo esc_html( $form_id ); ?>"]</code></td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<div class="ghlcs-panel">
					<h2><?php esc_html_e( 'Layout & Style', 'ghl-contact-sync' ); ?></h2>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="ghlcs-layout"><?php esc_html_e( 'Layout', 'ghl-contact-sync' ); ?></label></th>
								<td>
									<select id="ghlcs-layout" name="layout">
										<option value="inline" <?php selected( $form['layout'], 'inline' ); ?>><?php esc_html_e( 'Inline', 'ghl-contact-sync' ); ?></option>
										<option value="stacked" <?php selected( $form['layout'], 'stacked' ); ?>><?php esc_html_e( 'Stacked', 'ghl-contact-sync' ); ?></option>
										<option value="grid" <?php selected( $form['layout'], 'grid' ); ?>><?php esc_html_e( 'Grid', 'ghl-contact-sync' ); ?></option>
										<option value="column" <?php selected( $form['layout'], 'column' ); ?>><?php esc_html_e( 'Column', 'ghl-contact-sync' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="ghlcs-theme"><?php esc_html_e( 'Theme', 'ghl-contact-sync' ); ?></label></th>
								<td>
									<select id="ghlcs-theme" name="theme">
										<option value="theme-1" <?php selected( $form['theme'], 'theme-1' ); ?>><?php esc_html_e( 'Theme 1', 'ghl-contact-sync' ); ?></option>
										<option value="theme-2" <?php selected( $form['theme'], 'theme-2' ); ?>><?php esc_html_e( 'Theme 2', 'ghl-contact-sync' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="ghlcs-custom-class"><?php esc_html_e( 'Custom CSS Class', 'ghl-contact-sync' ); ?></label></th>
								<td><input type="text" id="ghlcs-custom-class" name="custom_class" class="regular-text" value="<?php echo esc_attr( $form['custom_class'] ); ?>"></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="ghlcs-panel">
					<h2><?php esc_html_e( 'Fields', 'ghl-contact-sync' ); ?></h2>
					<p><?php esc_html_e( 'Version 1 currently creates the default fields for the selected form type. Full drag-and-drop field editing will be added in the next form-builder pass.', 'ghl-contact-sync' ); ?></p>
					<ul class="ghlcs-field-preview">
						<?php foreach ( $form['fields'] as $field ) : ?>
							<li>
								<strong><?php echo esc_html( $field['label'] ); ?></strong>
								<span><?php echo esc_html( $field['type'] ); ?></span>
								<?php if ( ! empty( $field['required'] ) ) : ?><em><?php esc_html_e( 'Required', 'ghl-contact-sync' ); ?></em><?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div class="ghlcs-panel">
					<h2><?php esc_html_e( 'GHL Actions', 'ghl-contact-sync' ); ?></h2>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Create / Update Contact', 'ghl-contact-sync' ); ?></th>
								<td><label><input type="checkbox" name="ghl_enabled" value="1" <?php checked( ! empty( $form['ghl_enabled'] ) ); ?>> <?php esc_html_e( 'Enabled', 'ghl-contact-sync' ); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><label for="ghlcs-tags"><?php esc_html_e( 'Tags', 'ghl-contact-sync' ); ?></label></th>
								<td><input type="text" id="ghlcs-tags" name="tags" class="regular-text" value="<?php echo esc_attr( $form['tags'] ); ?>"><p class="description"><?php esc_html_e( 'Separate multiple tags with commas.', 'ghl-contact-sync' ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><label for="ghlcs-source"><?php esc_html_e( 'Source', 'ghl-contact-sync' ); ?></label></th>
								<td><input type="text" id="ghlcs-source" name="source" class="regular-text" value="<?php echo esc_attr( $form['source'] ); ?>"></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="ghlcs-panel">
					<h2><?php esc_html_e( 'Submit Behavior', 'ghl-contact-sync' ); ?></h2>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="ghlcs-submit-text"><?php esc_html_e( 'Submit Button Text', 'ghl-contact-sync' ); ?></label></th>
								<td><input type="text" id="ghlcs-submit-text" name="submit_text" class="regular-text" value="<?php echo esc_attr( $form['submit_text'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="ghlcs-loading-text"><?php esc_html_e( 'Loading Text', 'ghl-contact-sync' ); ?></label></th>
								<td><input type="text" id="ghlcs-loading-text" name="loading_text" class="regular-text" value="<?php echo esc_attr( $form['loading_text'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="ghlcs-success-behavior"><?php esc_html_e( 'Success Behavior', 'ghl-contact-sync' ); ?></label></th>
								<td>
									<select id="ghlcs-success-behavior" name="success_behavior">
										<option value="message" <?php selected( $form['success_behavior'], 'message' ); ?>><?php esc_html_e( 'Show Success Message', 'ghl-contact-sync' ); ?></option>
										<option value="redirect" <?php selected( $form['success_behavior'], 'redirect' ); ?>><?php esc_html_e( 'Redirect to URL', 'ghl-contact-sync' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="ghlcs-success-message"><?php esc_html_e( 'Success Message', 'ghl-contact-sync' ); ?></label></th>
								<td><input type="text" id="ghlcs-success-message" name="success_message" class="regular-text" value="<?php echo esc_attr( $form['success_message'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="ghlcs-error-message"><?php esc_html_e( 'Error Message', 'ghl-contact-sync' ); ?></label></th>
								<td><input type="text" id="ghlcs-error-message" name="error_message" class="regular-text" value="<?php echo esc_attr( $form['error_message'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="ghlcs-redirect-url"><?php esc_html_e( 'Redirect URL', 'ghl-contact-sync' ); ?></label></th>
								<td><input type="url" id="ghlcs-redirect-url" name="redirect_url" class="regular-text" value="<?php echo esc_attr( $form['redirect_url'] ); ?>"></td>
							</tr>
						</tbody>
					</table>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save Form', 'ghl-contact-sync' ); ?></button>
					<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-contact-sync' ) ); ?>"><?php esc_html_e( 'Back to Forms', 'ghl-contact-sync' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}
}