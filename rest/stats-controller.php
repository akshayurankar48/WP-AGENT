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
		$scheduled = get_option( Manage_Scheduled_Tasks::OPTION_KEY, [] );
		$active_schedules = count( array_filter( $scheduled, function ( $t ) {
			return 'active' === ( $t['status'] ?? '' );
		} ) );

		// Memory entries (stored in wp_options).
		$memory_entries = count( get_option( 'wp_agent_memories', [] ) );

		// Total registered actions.
		$registry = \WPAgent\Actions\Action_Registry::get_instance();
		$total_actions = count( $registry->get_all_tools() );

		return rest_ensure_response( [
			'total_actions'    => $total_actions,
			'conversations'    => $conversations,
			'actions_executed' => $actions_executed,
			'schedules_active' => $active_schedules,
			'memory_entries'   => $memory_entries,
		] );
	}
}
