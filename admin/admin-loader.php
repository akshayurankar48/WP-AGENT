<?php
/**
 * Admin Loader.
 *
 * @package WPAgent\Admin
 * @since 1.0.0
 */

namespace WPAgent\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin_Loader
 *
 * @since 1.0.0
 */
class Admin_Loader {

	/**
	 * Instance
	 *
	 * @access private
	 * @var Admin_Loader|null Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return Admin_Loader Initialized object of class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		Admin_Menu::get_instance();
		Assets_Manager::get_instance();
	}
}
