<?php
/**
 * Admin bootstrap.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin assets and menu pages.
 */
final class Admin {

	/**
	 * Forms list page.
	 *
	 * @var Forms_Page
	 */
	private $forms_page;

	/**
	 * Form editor page.
	 *
	 * @var Form_Editor
	 */
	private $form_editor;

	/**
	 * Submissions page.
	 *
	 * @var Submissions_Page
	 */
	private $submissions_page;

	/**
	 * Settings page.
	 *
	 * @var Settings_Page
	 */
	private $settings_page;

	/**
	 * Logs page.
	 *
	 * @var Logs_Page
	 */
	private $logs_page;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->forms_page       = new Forms_Page();
		$this->form_editor      = new Form_Editor();
		$this->submissions_page = new Submissions_Page();
		$this->settings_page    = new Settings_Page();
		$this->logs_page        = new Logs_Page();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		$this->forms_page->hooks();
		$this->form_editor->hooks();
		$this->settings_page->hooks();
	}

	/**
	 * Register plugin menu pages.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'GHL Contact Sync', 'ghl-contact-sync' ),
			__( 'GHL Contact Sync', 'ghl-contact-sync' ),
			'manage_options',
			'ghl-contact-sync',
			array( $this->forms_page, 'render' ),
			'dashicons-email-alt2',
			56
		);

		add_submenu_page(
			'ghl-contact-sync',
			__( 'Forms', 'ghl-contact-sync' ),
			__( 'Forms', 'ghl-contact-sync' ),
			'manage_options',
			'ghl-contact-sync',
			array( $this->forms_page, 'render' )
		);

		add_submenu_page(
			'ghl-contact-sync',
			__( 'Add New Form', 'ghl-contact-sync' ),
			__( 'Add New Form', 'ghl-contact-sync' ),
			'manage_options',
			'ghl-contact-sync-add',
			array( $this->form_editor, 'render' )
		);

		add_submenu_page(
			'ghl-contact-sync',
			__( 'Submissions', 'ghl-contact-sync' ),
			__( 'Submissions', 'ghl-contact-sync' ),
			'manage_options',
			'ghl-contact-sync-submissions',
			array( $this->submissions_page, 'render' )
		);

		add_submenu_page(
			'ghl-contact-sync',
			__( 'Settings', 'ghl-contact-sync' ),
			__( 'Settings', 'ghl-contact-sync' ),
			'manage_options',
			'ghl-contact-sync-settings',
			array( $this->settings_page, 'render' )
		);

		add_submenu_page(
			'ghl-contact-sync',
			__( 'Logs', 'ghl-contact-sync' ),
			__( 'Logs', 'ghl-contact-sync' ),
			'manage_options',
			'ghl-contact-sync-logs',
			array( $this->logs_page, 'render' )
		);
	}

	/**
	 * Load admin assets only on plugin pages.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'ghl-contact-sync' ) ) {
			return;
		}

		wp_enqueue_style(
			'ghlcs-admin',
			GHLCS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			GHLCS_VERSION
		);
	}
}
