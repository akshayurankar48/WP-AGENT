<?php
/**
 * Admin Menu.
 *
 * @package WPAgent\Admin
 * @since 1.0.0
 */

namespace WPAgent\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin_Menu
 *
 * @since 1.0.0
 */
class Admin_Menu {

	/**
	 * Instance
	 *
	 * @access private
	 * @var Admin_Menu|null Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return Admin_Menu Initialized object of class.
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
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
	}

	/**
	 * Register admin menu and submenu pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'WP Agent', 'wp-agent' ),
			__( 'WP Agent', 'wp-agent' ),
			'manage_options',
			'wp-agent',
			[ $this, 'render_dashboard' ],
			'dashicons-format-chat',
			30
		);

		add_submenu_page(
			'wp-agent',
			__( 'Dashboard', 'wp-agent' ),
			__( 'Dashboard', 'wp-agent' ),
			'manage_options',
			'wp-agent',
			[ $this, 'render_dashboard' ]
		);

		add_submenu_page(
			'wp-agent',
			__( 'Settings', 'wp-agent' ),
			__( 'Settings', 'wp-agent' ),
			'manage_options',
			'wp-agent-settings',
			[ $this, 'render_settings' ]
		);

		add_submenu_page(
			'wp-agent',
			__( 'History', 'wp-agent' ),
			__( 'History', 'wp-agent' ),
			'manage_options',
			'wp-agent-history',
			[ $this, 'render_history' ]
		);

		add_submenu_page(
			'wp-agent',
			__( 'Usage', 'wp-agent' ),
			__( 'Usage', 'wp-agent' ),
			'manage_options',
			'wp-agent-usage',
			[ $this, 'render_usage' ]
		);
	}

	/**
	 * Render the Dashboard page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_dashboard() {
		?>
		<div id="wp-agent-dashboard" class="wp-agent-wrap"></div>
		<?php
	}

	/**
	 * Render the Settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings() {
		?>
		<div id="wp-agent-settings" class="wp-agent-wrap"></div>
		<?php
	}

	/**
	 * Render the History page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_history() {
		?>
		<div id="wp-agent-history" class="wp-agent-wrap"></div>
		<?php
	}

	/**
	 * Render the Usage page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_usage() {
		?>
		<div id="wp-agent-usage" class="wp-agent-wrap"></div>
		<?php
	}
}
