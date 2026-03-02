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
		'wp-agent_page_wp-agent-history',
		'wp-agent_page_wp-agent-schedules',
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
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_drawer_assets' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_animations' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_ab_testing' ] );
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

	/**
	 * Enqueue drawer assets on all admin pages except WP Agent's own pages
	 * and the block editor (which has its own PluginSidebar).
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_drawer_assets( $hook_suffix ) {
		// Skip on WP Agent pages — they already have the full UI.
		if ( in_array( $hook_suffix, self::PAGE_HOOKS, true ) ) {
			return;
		}

		// Skip in the block editor — it has the PluginSidebar.
		$screen = get_current_screen();
		if ( $screen && $screen->is_block_editor() ) {
			return;
		}

		// Only for admins.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$asset_file = WP_AGENT_DIR . 'build/drawer.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'wp-agent-drawer',
			WP_AGENT_URL . 'build/drawer.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( WP_AGENT_DIR . 'build/style-drawer.css' ) ) {
			wp_enqueue_style(
				'wp-agent-drawer',
				WP_AGENT_URL . 'build/style-drawer.css',
				[],
				$asset['version']
			);
		}

		wp_localize_script(
			'wp-agent-drawer',
			'wpAgentData',
			[
				'restUrl'   => rest_url( 'wp-agent/v1/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'hasApiKey' => ! empty( get_option( \WPAgent\AI\Open_Router_Client::API_KEY_OPTION ) ),
				'userId'    => get_current_user_id(),
				'userName'  => wp_get_current_user()->display_name,
				'version'   => WP_AGENT_VER,
				'adminUrl'  => admin_url(),
			]
		);
	}

	/**
	 * Enqueue animation assets on the frontend when content uses wpa- classes.
	 *
	 * Checks the current post content for animation class names and only
	 * enqueues the CSS/JS when at least one is found.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_animations() {
		if ( is_admin() ) {
			return;
		}

		$post = get_post();
		if ( ! $post || empty( $post->post_content ) ) {
			return;
		}

		// Only enqueue when the content actually contains animation classes.
		if ( false === strpos( $post->post_content, 'wpa-' ) ) {
			return;
		}

		wp_enqueue_style(
			'wp-agent-animations',
			WP_AGENT_URL . 'assets/css/animations.css',
			[],
			WP_AGENT_VER
		);

		wp_enqueue_script(
			'wp-agent-animations',
			WP_AGENT_URL . 'assets/js/animations.js',
			[],
			WP_AGENT_VER,
			true
		);
	}

	/**
	 * Enqueue A/B testing script on the frontend when active tests exist.
	 *
	 * Only loads the lightweight tracking script when at least one A/B test
	 * is active, passing test data via wpAgentAB localization.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function enqueue_ab_testing() {
		if ( is_admin() ) {
			return;
		}

		$tests = get_option( 'wp_agent_ab_tests', [] );

		if ( empty( $tests ) ) {
			return;
		}

		// Filter to only active tests.
		$active_tests = array_values(
			array_filter(
				$tests,
				function ( $test ) {
					return 'active' === ( $test['status'] ?? '' );
				}
			)
		);

		if ( empty( $active_tests ) ) {
			return;
		}

		// Only send minimal data to the frontend.
		$frontend_tests = array_map(
			function ( $test ) {
				return [ 'id' => $test['id'] ];
			},
			$active_tests
		);

		wp_enqueue_script(
			'wp-agent-ab-testing',
			WP_AGENT_URL . 'assets/js/ab-testing.js',
			[],
			WP_AGENT_VER,
			true
		);

		wp_localize_script(
			'wp-agent-ab-testing',
			'wpAgentAB',
			[
				'restUrl' => rest_url( 'wp-agent/v1/ab-track' ),
				'tests'   => $frontend_tests,
			]
		);
	}

	/**
	 * Enqueue block editor assets for the Gutenberg sidebar.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_editor_assets() {
		$asset_file = WP_AGENT_DIR . 'build/editor.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'wp-agent-editor',
			WP_AGENT_URL . 'build/editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'wp-agent-editor',
			WP_AGENT_URL . 'build/style-main.css',
			[],
			$asset['version']
		);

		wp_localize_script(
			'wp-agent-editor',
			'wpAgentData',
			[
				'restUrl'   => rest_url( 'wp-agent/v1/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'hasApiKey' => ! empty( get_option( \WPAgent\AI\Open_Router_Client::API_KEY_OPTION ) ),
				'userId'    => get_current_user_id(),
				'userName'  => wp_get_current_user()->display_name,
				'version'   => WP_AGENT_VER,
				'adminUrl'  => admin_url(),
			]
		);
	}
}
