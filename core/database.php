<?php
/**
 * Database schema and migration system.
 *
 * Creates and manages the 4 custom tables used by WP Agent:
 * - {prefix}agent_conversations  — Chat sessions (per-user, optionally per-post)
 * - {prefix}agent_messages       — Individual messages within conversations
 * - {prefix}agent_checkpoints    — Pre/post action snapshots for undo/rollback
 * - {prefix}agent_history        — Audit log of all executed actions
 *
 * @package WPAgent
 * @since   1.0.0
 */

namespace WPAgent\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Database
 *
 * Handles table creation via dbDelta and version-based migrations.
 *
 * @since 1.0.0
 */
class Database {

	/**
	 * Current schema version.
	 *
	 * Bump this when adding migrations.
	 *
	 * @var string
	 */
	const SCHEMA_VERSION = '1.0.0';

	/**
	 * Option key that stores the installed schema version.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'wp_agent_db_version';

	/**
	 * Run on plugin activation.
	 *
	 * Called from register_activation_hook in wp-agent.php.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Check if schema needs upgrading on admin_init.
	 *
	 * Compares stored version with current SCHEMA_VERSION and runs
	 * create_tables() if they differ (dbDelta is safe to re-run).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( self::VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installed_version, self::SCHEMA_VERSION, '<' ) ) {
			self::create_tables();
			update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
		}
	}

	/**
	 * Create or update all custom tables using dbDelta.
	 *
	 * dbDelta is idempotent — it creates tables if they don't exist
	 * and adds missing columns if they do.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = self::get_conversations_schema( $wpdb->prefix, $charset_collate )
			. self::get_messages_schema( $wpdb->prefix, $charset_collate )
			. self::get_checkpoints_schema( $wpdb->prefix, $charset_collate )
			. self::get_history_schema( $wpdb->prefix, $charset_collate );

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get the conversations table schema.
	 *
	 * Stores chat sessions — one row per conversation.
	 * A conversation may be global (post_id NULL) or bound to a specific post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $prefix          The wpdb table prefix.
	 * @param string $charset_collate The charset collate string.
	 * @return string SQL statement for dbDelta.
	 */
	private static function get_conversations_schema( $prefix, $charset_collate ) {
		return "CREATE TABLE {$prefix}agent_conversations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned DEFAULT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			model varchar(100) NOT NULL DEFAULT '',
			tokens_used int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_user_id (user_id),
			KEY idx_post_id (post_id),
			KEY idx_status (status)
		) $charset_collate;\n";
	}

	/**
	 * Get the messages table schema.
	 *
	 * Stores individual messages within a conversation.
	 * Role is VARCHAR(20) instead of ENUM to avoid dbDelta parsing issues.
	 * Metadata column stores JSON (tool calls, action plans, etc.).
	 *
	 * @since 1.0.0
	 *
	 * @param string $prefix          The wpdb table prefix.
	 * @param string $charset_collate The charset collate string.
	 * @return string SQL statement for dbDelta.
	 */
	private static function get_messages_schema( $prefix, $charset_collate ) {
		return "CREATE TABLE {$prefix}agent_messages (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(20) NOT NULL DEFAULT 'user',
			content longtext NOT NULL,
			metadata longtext DEFAULT NULL,
			tokens int(10) unsigned NOT NULL DEFAULT 0,
			model varchar(100) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_conversation_id (conversation_id),
			KEY idx_role (role)
		) $charset_collate;\n";
	}

	/**
	 * Get the checkpoints table schema.
	 *
	 * Stores pre/post action snapshots for undo/rollback.
	 * Each checkpoint captures the state before an action executes
	 * and optionally the state after (for verification).
	 *
	 * @since 1.0.0
	 *
	 * @param string $prefix          The wpdb table prefix.
	 * @param string $charset_collate The charset collate string.
	 * @return string SQL statement for dbDelta.
	 */
	private static function get_checkpoints_schema( $prefix, $charset_collate ) {
		return "CREATE TABLE {$prefix}agent_checkpoints (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			message_id bigint(20) unsigned NOT NULL,
			action_type varchar(100) NOT NULL DEFAULT '',
			snapshot_before longtext NOT NULL,
			snapshot_after longtext DEFAULT NULL,
			entity_type varchar(50) NOT NULL DEFAULT '',
			entity_id bigint(20) unsigned DEFAULT NULL,
			is_restored tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_conversation_id (conversation_id),
			KEY idx_message_id (message_id)
		) $charset_collate;\n";
	}

	/**
	 * Get the history table schema.
	 *
	 * Audit log of all agent-executed actions.
	 * Tracks who did what, when, and with what result.
	 *
	 * @since 1.0.0
	 *
	 * @param string $prefix          The wpdb table prefix.
	 * @param string $charset_collate The charset collate string.
	 * @return string SQL statement for dbDelta.
	 */
	private static function get_history_schema( $prefix, $charset_collate ) {
		return "CREATE TABLE {$prefix}agent_history (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			conversation_id bigint(20) unsigned DEFAULT NULL,
			action_type varchar(100) NOT NULL DEFAULT '',
			action_data longtext DEFAULT NULL,
			result_status varchar(20) NOT NULL DEFAULT '',
			result_message text NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_user_id (user_id),
			KEY idx_conversation_id (conversation_id),
			KEY idx_action_type (action_type)
		) $charset_collate;\n";
	}

	/**
	 * Drop all custom tables.
	 *
	 * Called from uninstall.php for clean removal.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function drop_tables() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables = [
			"{$wpdb->prefix}agent_messages",
			"{$wpdb->prefix}agent_checkpoints",
			"{$wpdb->prefix}agent_history",
			"{$wpdb->prefix}agent_conversations",
		];

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		// phpcs:enable

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Get custom table names.
	 *
	 * @since 1.0.0
	 * @return array<string, string> Associative array of table identifiers to full table names.
	 */
	public static function get_table_names() {
		global $wpdb;

		return [
			'conversations' => "{$wpdb->prefix}agent_conversations",
			'messages'      => "{$wpdb->prefix}agent_messages",
			'checkpoints'   => "{$wpdb->prefix}agent_checkpoints",
			'history'       => "{$wpdb->prefix}agent_history",
		];
	}
}
