<?php
/**
 * Uninstall WP Agent.
 *
 * Removes all plugin data when uninstalled via WP Admin > Plugins.
 *
 * @package WPAgent
 * @since 1.0.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop custom tables.
$tables = [
	$wpdb->prefix . 'agent_conversations',
	$wpdb->prefix . 'agent_messages',
	$wpdb->prefix . 'agent_checkpoints',
	$wpdb->prefix . 'agent_history',
];

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete all plugin options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_agent_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Delete all plugin transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wp_agent_%' OR option_name LIKE '_transient_timeout_wp_agent_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
