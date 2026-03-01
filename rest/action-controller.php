<?php
/**
 * Action REST Controller.
 *
 * Direct action dispatch and undo endpoints. Allows the frontend to
 * execute actions outside of a chat flow and undo checkpointed actions.
 *
 * @package WPAgent\REST
 * @since   1.0.0
 */

namespace WPAgent\REST;

use WPAgent\Actions\Action_Registry;
use WPAgent\Core\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Class Action_Controller
 *
 * @since 1.0.0
 */
class Action_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'wp-agent/v1';

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		// POST /action/execute — dispatch an action.
		register_rest_route(
			self::NAMESPACE,
			'/action/execute',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'execute_action' ],
				'permission_callback' => [ $this, 'check_execute_permissions' ],
				'args'                => [
					'action' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'params' => [
						'type'              => 'object',
						'default'           => [],
						'sanitize_callback' => function ( $params ) {
							if ( ! is_array( $params ) ) {
								return [];
							}
							return map_deep( $params, 'sanitize_text_field' );
						},
					],
				],
			]
		);

		// POST /action/undo — restore a checkpoint.
		register_rest_route(
			self::NAMESPACE,
			'/action/undo',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'undo_action' ],
				'permission_callback' => [ $this, 'check_undo_permissions' ],
				'args'                => [
					'checkpoint_id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Permission check for execute — edit_posts as baseline.
	 *
	 * Per-action capability checks happen inside Action_Registry::dispatch().
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return bool|\WP_Error
	 */
	public function check_execute_permissions( $request ) {
		if ( ! current_user_can( 'edit_posts' ) || ! REST_Permissions::current_user_has_allowed_role() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to execute actions.', 'wp-agent' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Permission check for undo — edit_posts required.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return bool|\WP_Error
	 */
	public function check_undo_permissions( $request ) {
		if ( ! current_user_can( 'edit_posts' ) || ! REST_Permissions::current_user_has_allowed_role() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to undo actions.', 'wp-agent' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * POST /wp-agent/v1/action/execute
	 *
	 * Dispatches an action through the Action Registry.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function execute_action( $request ) {
		$action_name = $request->get_param( 'action' );
		$params      = $request->get_param( 'params' );

		if ( ! is_array( $params ) ) {
			$params = [];
		}

		$result = Action_Registry::get_instance()->dispatch( $action_name, $params );

		if ( is_wp_error( $result ) ) {
			$status = $result->get_error_data() && isset( $result->get_error_data()['status'] )
				? $result->get_error_data()['status']
				: 400;

			return new \WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => $status ]
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * POST /wp-agent/v1/action/undo
	 *
	 * Marks a checkpoint as restored. Full snapshot restore logic
	 * will be implemented with the sidebar UI.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function undo_action( $request ) {
		global $wpdb;

		$checkpoint_id = (int) $request->get_param( 'checkpoint_id' );
		$tables        = Database::get_table_names();
		$user_id       = get_current_user_id();

		// Verify checkpoint exists and belongs to a conversation owned by the current user.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$checkpoint = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT cp.id, cp.is_restored, cp.action_type, c.user_id
				FROM {$tables['checkpoints']} cp
				INNER JOIN {$tables['conversations']} c ON c.id = cp.conversation_id
				WHERE cp.id = %d
				LIMIT 1",
				$checkpoint_id
			),
			ARRAY_A
		);

		if ( null === $checkpoint ) {
			return new \WP_Error(
				'not_found',
				__( 'Checkpoint not found.', 'wp-agent' ),
				[ 'status' => 404 ]
			);
		}

		if ( (int) $checkpoint['user_id'] !== $user_id ) {
			return new \WP_Error(
				'forbidden',
				__( 'You do not have access to this checkpoint.', 'wp-agent' ),
				[ 'status' => 403 ]
			);
		}

		if ( (int) $checkpoint['is_restored'] ) {
			return new \WP_Error(
				'already_restored',
				__( 'This checkpoint has already been restored.', 'wp-agent' ),
				[ 'status' => 409 ]
			);
		}

		// Mark as restored.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$tables['checkpoints'],
			[ 'is_restored' => 1 ],
			[ 'id' => $checkpoint_id ],
			[ '%d' ],
			[ '%d' ]
		);

		if ( false === $updated ) {
			return new \WP_Error(
				'db_error',
				__( 'Failed to mark checkpoint as restored.', 'wp-agent' ),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response( [
			'success'       => true,
			'checkpoint_id' => $checkpoint_id,
			'action_type'   => $checkpoint['action_type'],
		] );
	}
}
