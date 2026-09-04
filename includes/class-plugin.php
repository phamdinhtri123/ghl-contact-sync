<?php
/**
 * Main plugin coordinator.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync;

use GHLContactSync\Admin\Admin;
use GHLContactSync\Forms\Form_Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires plugin services to WordPress hooks.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Admin service.
	 *
	 * @var Admin
	 */
	private $admin;

	/**
	 * Update checker service.
	 *
	 * @var Update_Checker
	 */
	private $update_checker;

	/**
	 * Frontend form renderer.
	 *
	 * @var Form_Renderer
	 */
	private $form_renderer;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->admin          = new Admin();
		$this->update_checker = new Update_Checker();
		$this->form_renderer  = new Form_Renderer();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'init', array( $this, 'register_form_post_type' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( Activator::class, 'maybe_upgrade' ) );

		if ( is_admin() ) {
			$this->admin->hooks();
		}

		$this->form_renderer->hooks();
		$this->update_checker->hooks();
	}

	/**
	 * Load translation files.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'ghl-contact-sync', false, dirname( GHLCS_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Register the internal form configuration post type.
	 *
	 * @return void
	 */
	public function register_form_post_type() {
		register_post_type(
			'ghlcs_form',
			array(
				'labels'              => array(
					'name'          => __( 'GHL Forms', 'ghl-contact-sync' ),
					'singular_name' => __( 'GHL Form', 'ghl-contact-sync' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'exclude_from_search' => true,
			)
		);
	}
}