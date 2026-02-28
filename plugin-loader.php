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

		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'plugins_loaded', [ $this, 'load_admin' ] );
		add_action( 'admin_init', [ 'WPAgent\Core\Database', 'maybe_upgrade' ] );
		add_action( 'wp_agent_register_actions', [ $this, 'register_core_actions' ] );
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
		$registry->register( new Actions\Read_Blocks() );
		$registry->register( new Actions\Insert_Blocks() );

		// Settings & plugin management actions.
		$registry->register( new Actions\Update_Settings() );
		$registry->register( new Actions\Manage_Permalinks() );
		$registry->register( new Actions\Install_Plugin() );
		$registry->register( new Actions\Activate_Plugin() );
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
