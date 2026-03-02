<?php
/**
 * Plugin Loader.
 *
 * @package WPAgent
 * @since 1.0.0
 */

namespace WPAgent;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin_Loader
 *
 * @since 1.0.0
 */
class Plugin_Loader {

	/**
	 * Instance
	 *
	 * @access private
	 * @var Plugin_Loader|null Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return Plugin_Loader Initialized object of class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class Class name.
	 * @since 1.0.0
	 */
	public function autoload( $class ) {
		if ( 0 !== strpos( $class, __NAMESPACE__ ) ) {
			return;
		}

		$class_to_load = $class;

		$filename = strtolower(
			preg_replace(
				[ '/^' . __NAMESPACE__ . '\\\/', '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
				[ '', '$1-$2', '-', DIRECTORY_SEPARATOR ],
				$class_to_load
			)
		);

		$file = WP_AGENT_DIR . $filename . '.php';

		// If the file is readable, include it.
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		spl_autoload_register( [ $this, 'autoload' ] );

		// Load bundled libraries (SDK, providers, MCP adapter).
		require_once WP_AGENT_DIR . 'lib/autoload.php';

		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'plugins_loaded', [ $this, 'load_admin' ] );
		add_action( 'plugins_loaded', [ $this, 'load_frontend' ] );
		add_action( 'admin_init', [ 'WPAgent\Core\Database', 'maybe_upgrade' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'wp_agent_register_actions', [ $this, 'register_core_actions' ] );

		// Ecosystem integrations (conditional — only when APIs available).
		add_action( 'init', [ $this, 'load_integrations' ] );

		// Process URL redirects on frontend.
		add_action( 'template_redirect', [ 'WPAgent\Actions\Manage_Redirects', 'process_redirects' ] );

		// Cleanup export files.
		add_action( 'wp_agent_cleanup_export', function ( $filepath ) {
			if ( file_exists( $filepath ) ) {
				wp_delete_file( $filepath );
			}
		} );
	}

	/**
	 * Load ecosystem integrations.
	 *
	 * Conditionally initializes Abilities Bridge and MCP Server
	 * only when the required WordPress APIs are available.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_integrations() {
		// WordPress Abilities API (WP 6.9+).
		if ( function_exists( 'wp_register_ability' ) ) {
			Integrations\Abilities_Bridge::get_instance();
		}

		// MCP Adapter (WP 7.0+ or bundled).
		if ( class_exists( 'WP\\MCP\\Core\\McpAdapter' ) ) {
			Integrations\MCP_Server::get_instance();
		}
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_rest_routes() {
		( new REST\Settings_Controller() )->register_routes();
		( new REST\Chat_Controller() )->register_routes();
		( new REST\Stream_Controller() )->register_routes();
		( new REST\History_Controller() )->register_routes();
		( new REST\Action_Controller() )->register_routes();
		( new REST\Ab_Tracking_Controller() )->register_routes();
		( new REST\Schedules_Controller() )->register_routes();
		( new REST\Stats_Controller() )->register_routes();
	}

	/**
	 * Register core actions.
	 *
	 * @since 1.0.0
	 *
	 * @param \WPAgent\Actions\Action_Registry $registry The action registry.
	 * @return void
	 */
	public function register_core_actions( $registry ) {
		// Content actions.
		$registry->register( new Actions\Create_Post() );
		$registry->register( new Actions\Edit_Post() );
		$registry->register( new Actions\Delete_Post() );
		$registry->register( new Actions\Clone_Post() );
		$registry->register( new Actions\Read_Blocks() );
		$registry->register( new Actions\Insert_Blocks() );
		$registry->register( new Actions\Search_Posts() );
		$registry->register( new Actions\Bulk_Edit() );
		$registry->register( new Actions\Get_Page_Templates() );
		$registry->register( new Actions\Set_Page_Template() );
		$registry->register( new Actions\Search_Media() );
		$registry->register( new Actions\Import_Media() );
		$registry->register( new Actions\Generate_Image() );
		$registry->register( new Actions\Set_Featured_Image() );

		// Pattern library actions.
		$registry->register( new Actions\List_Patterns() );
		$registry->register( new Actions\Get_Pattern() );
		$registry->register( new Actions\Create_Pattern() );

		// Navigation menu actions.
		$registry->register( new Actions\Manage_Menus() );

		// Taxonomy actions.
		$registry->register( new Actions\Manage_Taxonomies() );

		// Design control actions.
		$registry->register( new Actions\Edit_Global_Styles() );
		$registry->register( new Actions\Add_Custom_Css() );
		$registry->register( new Actions\Screenshot_Page() );

		// Settings & plugin management actions.
		$registry->register( new Actions\Update_Settings() );
		$registry->register( new Actions\Manage_Permalinks() );
		$registry->register( new Actions\Install_Plugin() );
		$registry->register( new Actions\Activate_Plugin() );
		$registry->register( new Actions\List_Plugins() );

		// User & system actions.
		$registry->register( new Actions\Deactivate_Plugin() );
		$registry->register( new Actions\Create_User() );
		$registry->register( new Actions\Manage_Users() );
		$registry->register( new Actions\List_Users() );
		$registry->register( new Actions\Site_Health() );

		// Content intelligence actions.
		$registry->register( new Actions\Read_Url() );
		$registry->register( new Actions\Web_Search() );
		$registry->register( new Actions\Manage_Seo() );

		// Site appearance actions.
		$registry->register( new Actions\Install_Theme() );
		$registry->register( new Actions\Search_Theme() );
		$registry->register( new Actions\Manage_Theme() );
		$registry->register( new Actions\Edit_Template_Parts() );

		// Comment management actions.
		$registry->register( new Actions\Manage_Comments() );

		// Content generation action.
		$registry->register( new Actions\Generate_Content() );

		// Undo/rollback action.
		$registry->register( new Actions\Undo_Action() );

		// Quick-win actions (Phase 1).
		$registry->register( new Actions\Export_Site() );
		$registry->register( new Actions\Manage_Redirects() );
		$registry->register( new Actions\Manage_Widgets() );
		$registry->register( new Actions\Manage_Cron() );
		$registry->register( new Actions\Database_Optimize() );
		$registry->register( new Actions\Manage_Roles() );
		$registry->register( new Actions\Bulk_Find_Replace() );
		$registry->register( new Actions\Generate_Sitemap() );
		$registry->register( new Actions\Manage_Shortcodes() );
		$registry->register( new Actions\Manage_Options_Bulk() );

		// Site cloner / reference builder.
		$registry->register( new Actions\Analyze_Reference_Site() );

		// Scheduled tasks.
		$registry->register( new Actions\Manage_Scheduled_Tasks() );

		// Conversation memory.
		$registry->register( new Actions\Manage_Memory() );

		// Accessibility auditor.
		$registry->register( new Actions\Audit_Accessibility() );

		// Performance optimizer.
		$registry->register( new Actions\Optimize_Performance() );

		// A/B testing.
		$registry->register( new Actions\Manage_Ab_Test() );

		// Multi-page site generator.
		$registry->register( new Actions\Generate_Full_Site() );

		// Plugin recommender.
		$registry->register( new Actions\Recommend_Plugin() );

		// Content migration.
		$registry->register( new Actions\Import_Content() );

		// Additional quick wins.
		$registry->register( new Actions\Manage_Rewrite_Rules() );
		$registry->register( new Actions\Manage_Transients() );

		// WooCommerce actions (conditional).
		$this->register_woo_actions( $registry );
	}

	/**
	 * Register WooCommerce-specific actions.
	 *
	 * Only registers when WooCommerce is active.
	 *
	 * @since 1.1.0
	 *
	 * @param \WPAgent\Actions\Action_Registry $registry The action registry.
	 * @return void
	 */
	private function register_woo_actions( $registry ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$registry->register( new Actions\Woo_Manage_Products() );
		$registry->register( new Actions\Woo_Manage_Orders() );
		$registry->register( new Actions\Woo_Manage_Coupons() );
		$registry->register( new Actions\Woo_Manage_Categories() );
		$registry->register( new Actions\Woo_Manage_Shipping() );
		$registry->register( new Actions\Woo_Manage_Settings() );
		$registry->register( new Actions\Woo_Analytics() );
		$registry->register( new Actions\Woo_Manage_Inventory() );
	}

	/**
	 * Load admin classes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_admin() {
		if ( is_admin() ) {
			Admin\Admin_Loader::get_instance();
		}
	}

	/**
	 * Load frontend classes.
	 *
	 * Initializes assets that must enqueue on frontend pages (e.g. animation
	 * CSS/JS). Separated from load_admin() because Assets_Manager registers
	 * wp_enqueue_scripts hooks that only fire on non-admin requests.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function load_frontend() {
		if ( ! is_admin() ) {
			Admin\Assets_Manager::get_instance();
		}
	}

	/**
	 * Load Plugin Text Domain.
	 *
	 * This will load the translation textdomain depending on the file priorities.
	 *      1. Global Languages /wp-content/languages/wp-agent/ folder
	 *      2. Local directory /wp-content/plugins/wp-agent/languages/ folder
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain() {
		// Default languages directory.
		$lang_dir = WP_AGENT_DIR . 'languages/';

		/**
		 * Filters the languages directory path to use for plugin.
		 *
		 * @param string $lang_dir The languages directory path.
		 */
		$lang_dir = apply_filters( 'wp_agent_languages_directory', $lang_dir );

		// Traditional WordPress plugin locale filter.
		global $wp_version;

		$get_locale = get_locale();

		if ( $wp_version >= 4.7 ) {
			$get_locale = get_user_locale();
		}

		/**
		 * Language Locale for plugin.
		 *
		 * @var string $get_locale The locale to use.
		 * Uses get_user_locale() in WordPress 4.7 or greater,
		 * otherwise uses get_locale().
		 */
		$locale = apply_filters( 'plugin_locale', $get_locale, 'wp-agent' );
		$mofile = sprintf( '%1$s-%2$s.mo', 'wp-agent', $locale );

		// Setup paths to current locale file.
		$mofile_global = WP_LANG_DIR . '/plugins/' . $mofile;
		$mofile_local  = $lang_dir . $mofile;

		if ( file_exists( $mofile_global ) ) {
			// Look in global /wp-content/languages/wp-agent/ folder.
			load_textdomain( 'wp-agent', $mofile_global );
		} elseif ( file_exists( $mofile_local ) ) {
			// Look in local /wp-content/plugins/wp-agent/languages/ folder.
			load_textdomain( 'wp-agent', $mofile_local );
		} else {
			// Load the default language files.
			load_plugin_textdomain( 'wp-agent', false, $lang_dir );
		}
	}
}

/**
 * Kicking this off by calling 'get_instance()' method.
 */
Plugin_Loader::get_instance();
