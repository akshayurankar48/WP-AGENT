<?php
/**
 * Dashboard Stats REST Controller.
 *
 * Provides aggregate counts for the admin dashboard.
 *
 * @package WPAgent\REST
 * @since   1.1.0
 */

namespace WPAgent\REST;

use WPAgent\Core\Database;
use WPAgent\Actions\Manage_Scheduled_Tasks;

defined( 'ABSPATH' ) || exit;

class Stats_Controller extends \WP_REST_Controller {

	protected $namespace = 'wp-agent/v1';
	protected $rest_base = 'stats';

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_stats' ],
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				],
			]
		);
	}

	public function get_stats() {
		global $wpdb;

		$tables  = Database::get_table_names();
		$user_id = get_current_user_id();

		// Conversations count for current user.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$conversations = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$tables['conversations']} WHERE user_id = %d",
				$user_id
			)
		);

		// Actions executed (history count).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$actions_executed = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$tables['history']} WHERE user_id = %d",
				$user_id
			)
		);

		// Scheduled tasks.
		$active_schedules = 0;
		if ( class_exists( Manage_Scheduled_Tasks::class ) ) {
			$scheduled        = get_option( Manage_Scheduled_Tasks::OPTION_KEY, [] );
			$active_schedules = count( array_filter( $scheduled, function ( $t ) {
				return 'active' === ( $t['status'] ?? '' );
			} ) );
		}

		// Memory entries (stored in wp_options).
		$memory_entries = count( get_option( 'wp_agent_memories', [] ) );

		// Total registered actions.
		$registry = \WPAgent\Actions\Action_Registry::get_instance();
		$total_actions = count( $registry->get_tool_definitions() );

		// Token usage stats.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$token_stats = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COALESCE(SUM(tokens_used), 0) AS total_tokens,
				        COUNT(*) AS total_conversations
				 FROM {$tables['conversations']}
				 WHERE user_id = %d",
				$user_id
			)
		);

		// Requests today (messages sent today).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$requests_today = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$tables['messages']} m
				 JOIN {$tables['conversations']} c ON c.id = m.conversation_id
				 WHERE c.user_id = %d AND m.role = 'assistant' AND DATE(m.created_at) = %s",
				$user_id,
				current_time( 'Y-m-d' )
			)
		);

		return rest_ensure_response( [
			'total_actions'    => $total_actions,
			'conversations'    => $conversations,
			'actions_executed' => $actions_executed,
			'schedules_active' => $active_schedules,
			'memory_entries'   => $memory_entries,
			'total_tokens'     => (int) ( $token_stats->total_tokens ?? 0 ),
			'requests_today'   => $requests_today,
		] );
	}
}
