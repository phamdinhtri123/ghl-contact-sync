<?php
/**
 * Abandoned cart module bootstrap.
 *
 * @package GHLContactSync
 */

namespace GHLContactSync\AbandonedCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires abandoned cart services.
 */
final class Module {

	/**
	 * Repository.
	 *
	 * @var Cart_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new Cart_Repository();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		( new Cart_Tracker( $this->repository ) )->hooks();
		( new Recovery_Service( $this->repository ) )->hooks();
		( new Order_Lifecycle_Service( $this->repository ) )->hooks();
		( new Background_Jobs() )->hooks();
	}
}
