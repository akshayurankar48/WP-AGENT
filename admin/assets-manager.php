<?php
/**
 * Assets Manager.
 *
 * @package WPAgent\Admin
 * @since 1.0.0
 */

namespace WPAgent\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Assets_Manager
 *
 * @since 1.0.0
 */
class Assets_Manager {

	/**
	 * Instance
	 *
	 * @access private
	 * @var Assets_Manager|null Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Admin page hook suffixes.
	 *
	 * @var string[]
	 */
	private const PAGE_HOOKS = [
		'toplevel_page_wp-agent',
		'wp-agent_page_wp-agent-settings',
		'wp-agent_page_wp-agent-capabilities',
		'wp-agent_page_wp-agent-help',
	];

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return Assets_Manager Initialized object of class.
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
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, self::PAGE_HOOKS, true ) ) {
			return;
		}

		$asset_file = WP_AGENT_DIR . 'build/main.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'wp-agent-admin',
			WP_AGENT_URL . 'build/main.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'wp-agent-admin',
			WP_AGENT_URL . 'build/style-main.css',
			[],
			$asset['version']
		);

		wp_localize_script(
			'wp-agent-admin',
			'wpAgentData',
			[
				'restUrl'     => rest_url( 'wp-agent/v1/' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'hasApiKey'   => ! empty( get_option( \WPAgent\AI\Open_Router_Client::API_KEY_OPTION ) ),
				'userId'      => get_current_user_id(),
				'userName'    => wp_get_current_user()->display_name,
				'version'     => WP_AGENT_VER,
				'adminUrl'    => admin_url(),
				'currentPage' => isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'wp-agent',
			]
		);

		// Hide default admin notices on our pages.
		wp_add_inline_style(
			'wp-agent-admin',
			'.wp-agent-wrap ~ .notice, .wp-agent-wrap ~ .updated, .wp-agent-wrap ~ .error, .wp-agent-wrap .notice, div.notice:not(.wp-agent-notice) { display: none !important; } #wpcontent { padding-left: 0; } #wpbody-content { padding-bottom: 0; }'
		);
	}
}
