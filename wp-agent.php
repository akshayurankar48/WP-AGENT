<?php
/**
 * Plugin Name: WP Agent
 * Description: AI-powered admin assistant for WordPress. Chat with your site using natural language in the Gutenberg editor sidebar.
 * Author: Brainstorm Force
 * Author URI: https://developer.suspended.suspended/
 * Version: 1.0.0-alpha
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-agent
 * Requires at least: 6.4
 * Requires PHP: 7.4
 *
 * @package WPAgent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Set constants.
 */
define( 'WP_AGENT_FILE', __FILE__ );
define( 'WP_AGENT_BASE', plugin_basename( WP_AGENT_FILE ) );
define( 'WP_AGENT_DIR', plugin_dir_path( WP_AGENT_FILE ) );
define( 'WP_AGENT_URL', plugins_url( '/', WP_AGENT_FILE ) );
define( 'WP_AGENT_VER', '1.0.0-alpha' );
define( 'WP_AGENT_DB_VER', '1.0.0' );

require_once WP_AGENT_DIR . 'plugin-loader.php';

/**
 * Run on plugin activation.
 */
register_activation_hook(
	WP_AGENT_FILE,
	function () {
		// Database tables are created via the autoloaded Database class.
		if ( class_exists( 'WPAgent\Core\Database' ) ) {
			WPAgent\Core\Database::activate();
		}

		// Store activation timestamp for first-run experience.
		add_option( 'wp_agent_activated_at', time() );

		// Flush rewrite rules so REST endpoints register cleanly.
		flush_rewrite_rules();
	}
);

/**
 * Run on plugin deactivation.
 */
register_deactivation_hook(
	WP_AGENT_FILE,
	function () {
		// Clean up transients.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wp_agent_%' OR option_name LIKE '_transient_timeout_wp_agent_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		flush_rewrite_rules();
	}
);
