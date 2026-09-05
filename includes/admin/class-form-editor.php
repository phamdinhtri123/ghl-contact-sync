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
			'render_mode'      => isset( $_POST['render_mode'] ) ? wp_unslash( $_POST['render_mode'] ) : 'plugin',
			'type'             => isset( $_POST['form_type'] ) ? wp_unslash( $_POST['form_type'] ) : 'newsletter',
			'status'           => isset( $_POST['form_status'] ) ? wp_unslash( $_POST['form_status'] ) : 'active',
			'layout'           => isset( $_POST['layout'] ) ? wp_unslash( $_POST['layout'] ) : '',
			'theme'            => 'theme-1',
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
			'external_container' => isset( $_POST['external_container'] ) ? wp_unslash( $_POST['external_container'] ) : '',
			'external_submit'  => isset( $_POST['external_submit'] ) ? wp_unslash( $_POST['external_submit'] ) : '',
			'external_is_popup' => isset( $_POST['external_is_popup'] ) ? 1 : 0,
			'external_fields'  => $this->posted_external_fields(),
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
								<th scope="row"><label for="ghlcs-render-mode"><?php esc_html_e( 'Form Source', 'ghl-contact-sync' ); ?></label></th>
								<td>
									<select id="ghlcs-render-mode" name="render_mode">
										<option value="plugin" <?php selected( $form['render_mode'], 'plugin' ); ?>><?php esc_html_e( 'Plugin shortcode form', 'ghl-contact-sync' ); ?></option>
										<option value="external" <?php selected( $form['render_mode'], 'external' ); ?>><?php esc_html_e( 'External existing form', 'ghl-contact-sync' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Use a plugin-rendered shortcode form, or connect this form action to an existing popup/block form by CSS selectors.', 'ghl-contact-sync' ); ?></p>
								</td>
							</tr>
							<tr class="ghlcs-plugin-mode-row">
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
								<tr class="ghlcs-plugin-mode-row">
									<th scope="row"><?php esc_html_e( 'Shortcode', 'ghl-contact-sync' ); ?></th>
									<td><code>[ghl_form id="<?php echo esc_html( $form_id ); ?>"]</code></td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<div class="ghlcs-panel ghlcs-plugin-mode-panel">
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
								<th scope="row"><label for="ghlcs-custom-class"><?php esc_html_e( 'Custom CSS Class', 'ghl-contact-sync' ); ?></label></th>
								<td><input type="text" id="ghlcs-custom-class" name="custom_class" class="regular-text" value="<?php echo esc_attr( $form['custom_class'] ); ?>"></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="ghlcs-panel ghlcs-plugin-mode-panel">
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

				<div class="ghlcs-panel ghlcs-external-mode-panel">
					<h2><?php esc_html_e( 'External Form Selectors', 'ghl-contact-sync' ); ?></h2>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="ghlcs-external-container"><?php esc_html_e( 'Container / Wrapper Selector', 'ghl-contact-sync' ); ?></label></th>
								<td>
									<input type="text" id="ghlcs-external-container" name="external_container" class="regular-text code" value="<?php echo esc_attr( $form['external_container'] ); ?>" placeholder="#newsletter-popup">
									<p class="description"><?php esc_html_e( 'Enter the CSS selector for the block that contains the form. Field selectors below are searched only inside this container.', 'ghl-contact-sync' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Field Selectors', 'ghl-contact-sync' ); ?></th>
								<td>
									<div class="ghlcs-external-fields" data-next-index="<?php echo esc_attr( count( $form['external_fields'] ) ); ?>">
										<?php foreach ( $form['external_fields'] as $index => $external_field ) : ?>
											<?php $this->render_external_field_row( $external_field, $index ); ?>
										<?php endforeach; ?>
									</div>
									<button type="button" class="button button-secondary ghlcs-add-external-field"><?php esc_html_e( 'Add Field', 'ghl-contact-sync' ); ?></button>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="ghlcs-external-submit"><?php esc_html_e( 'Submit Button Selector', 'ghl-contact-sync' ); ?></label></th>
								<td>
									<input type="text" id="ghlcs-external-submit" name="external_submit" class="regular-text code" value="<?php echo esc_attr( $form['external_submit'] ); ?>" placeholder="button[type=&quot;submit&quot;]">
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Popup Form', 'ghl-contact-sync' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="external_is_popup" value="1" <?php checked( ! empty( $form['external_is_popup'] ) ); ?>>
										<?php esc_html_e( 'Close the matched container after a successful submit.', 'ghl-contact-sync' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Use a popup root or overlay as the Container / Wrapper Selector so the full popup can be hidden.', 'ghl-contact-sync' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
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

				<div class="ghlcs-panel ghlcs-plugin-mode-panel">
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
		<script type="text/html" id="tmpl-ghlcs-external-field-row">
			<?php
			$this->render_external_field_row(
				array(
					'key'         => 'phone',
					'label'       => 'Phone',
					'selector'    => '',
					'required'    => false,
					'ghl_mapping' => 'phone',
				),
				'__INDEX__'
			);
			?>
		</script>
		<script>
			(function() {
				var mode = document.getElementById('ghlcs-render-mode');
				var fields = document.querySelector('.ghlcs-external-fields');
				var add = document.querySelector('.ghlcs-add-external-field');
				var template = document.getElementById('tmpl-ghlcs-external-field-row');

				function toggleMode() {
					var external = mode && mode.value === 'external';
					document.querySelectorAll('.ghlcs-plugin-mode-row, .ghlcs-plugin-mode-panel').forEach(function(el) {
						el.style.display = external ? 'none' : '';
					});
					document.querySelectorAll('.ghlcs-external-mode-panel').forEach(function(el) {
						el.style.display = external ? '' : 'none';
					});
				}

				function refreshRemoveButtons() {
					document.querySelectorAll('.ghlcs-remove-external-field').forEach(function(button) {
						button.onclick = function() {
							button.closest('.ghlcs-external-field-row').remove();
						};
					});
				}

				if (mode) {
					mode.addEventListener('change', toggleMode);
					toggleMode();
				}

				if (fields && add && template) {
					add.addEventListener('click', function() {
						var index = fields.getAttribute('data-next-index') || '0';
						fields.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, index));
						fields.setAttribute('data-next-index', String(parseInt(index, 10) + 1));
						refreshRemoveButtons();
					});
					refreshRemoveButtons();
				}
			}());
		</script>
		<?php
	}

	/**
	 * Get external selector field rows from POST.
	 *
	 * @return array
	 */
	private function posted_external_fields() {
		$keys      = isset( $_POST['external_field_key'] ) && is_array( $_POST['external_field_key'] ) ? wp_unslash( $_POST['external_field_key'] ) : array();
		$selectors = isset( $_POST['external_field_selector'] ) && is_array( $_POST['external_field_selector'] ) ? wp_unslash( $_POST['external_field_selector'] ) : array();
		$required  = isset( $_POST['external_field_required'] ) && is_array( $_POST['external_field_required'] ) ? wp_unslash( $_POST['external_field_required'] ) : array();
		$fields    = array();

		foreach ( $selectors as $index => $selector ) {
			$fields[] = array(
				'key'         => $keys[ $index ] ?? 'custom',
				'selector'    => $selector,
				'required'    => isset( $required[ $index ] ),
				'ghl_mapping' => $this->default_mapping_for_key( $keys[ $index ] ?? 'custom' ),
			);
		}

		return $fields;
	}

	/**
	 * Render one external selector field row.
	 *
	 * @param array      $field External field config.
	 * @param int|string $index Field index.
	 * @return void
	 */
	private function render_external_field_row( array $field, $index ) {
		$key = $field['key'] ?? 'custom';
		?>
		<div class="ghlcs-external-field-row">
			<select name="external_field_key[<?php echo esc_attr( $index ); ?>]">
				<option value="email" <?php selected( $key, 'email' ); ?>><?php esc_html_e( 'Email', 'ghl-contact-sync' ); ?></option>
				<option value="phone" <?php selected( $key, 'phone' ); ?>><?php esc_html_e( 'Phone', 'ghl-contact-sync' ); ?></option>
				<option value="first_name" <?php selected( $key, 'first_name' ); ?>><?php esc_html_e( 'First Name', 'ghl-contact-sync' ); ?></option>
				<option value="last_name" <?php selected( $key, 'last_name' ); ?>><?php esc_html_e( 'Last Name', 'ghl-contact-sync' ); ?></option>
				<option value="message" <?php selected( $key, 'message' ); ?>><?php esc_html_e( 'Message', 'ghl-contact-sync' ); ?></option>
				<option value="custom" <?php selected( $key, 'custom' ); ?>><?php esc_html_e( 'Custom', 'ghl-contact-sync' ); ?></option>
			</select>
			<input type="text" name="external_field_selector[<?php echo esc_attr( $index ); ?>]" class="regular-text code" value="<?php echo esc_attr( $field['selector'] ?? '' ); ?>" placeholder="input[name=&quot;email&quot;]">
			<label><input type="checkbox" name="external_field_required[<?php echo esc_attr( $index ); ?>]" value="1" <?php checked( ! empty( $field['required'] ) ); ?>> <?php esc_html_e( 'Required', 'ghl-contact-sync' ); ?></label>
			<button type="button" class="button button-link-delete ghlcs-remove-external-field"><?php esc_html_e( 'Remove', 'ghl-contact-sync' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Get the default GHL mapping for an external field type.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private function default_mapping_for_key( $key ) {
		$map = array(
			'email'      => 'email',
			'phone'      => 'phone',
			'first_name' => 'firstName',
			'last_name'  => 'lastName',
			'message'    => '',
			'custom'     => '',
		);

		return $map[ sanitize_key( $key ) ] ?? '';
	}
}
