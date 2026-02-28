<?php
/**
 * History REST Controller.
 *
 * Provides conversation list and detail endpoints for the current user.
 * All queries are scoped to the authenticated user — no cross-user access.
 *
 * @package WPAgent\REST
 * @since   1.0.0
 */

namespace WPAgent\REST;

use WPAgent\Core\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Class History_Controller
 *
 * @since 1.0.0
 */
class History_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'wp-agent/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	const ROUTE = '/history';

	/**
	 * Default items per page.
	 *
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 20;

	/**
	 * Maximum items per page.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		// GET /history — paginated conversation list.
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_conversations' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args'                => $this->get_list_args(),
			]
		);

		// GET /history/<id> — single conversation with messages.
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE . '/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_conversation' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Permission check — edit_posts required.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return bool|\WP_Error
	 */
	public function check_permissions( $request ) {
		if ( ! current_user_can( 'edit_posts' ) || ! REST_Permissions::current_user_has_allowed_role() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view chat history.', 'wp-agent' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * GET /wp-agent/v1/history
	 *
	 * Returns a paginated list of the current user's conversations.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function list_conversations( $request ) {
		global $wpdb;

		$tables   = Database::get_table_names();
		$user_id  = get_current_user_id();
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) $request->get_param( 'per_page' ) ?: self::DEFAULT_PER_PAGE ) );
		$offset   = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$tables['conversations']} WHERE user_id = %d",
				$user_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, post_id, title, status, model, tokens_used, created_at, updated_at
				FROM {$tables['conversations']}
				WHERE user_id = %d
				ORDER BY updated_at DESC
				LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		if ( null === $conversations ) {
			$conversations = [];
		}

		// Cast numeric fields.
		foreach ( $conversations as &$conv ) {
			$conv['id']          = (int) $conv['id'];
			$conv['post_id']     = $conv['post_id'] ? (int) $conv['post_id'] : null;
			$conv['tokens_used'] = (int) $conv['tokens_used'];
		}
		unset( $conv );

		$response = rest_ensure_response( [
			'conversations' => $conversations,
			'total'         => $total,
			'page'          => $page,
			'per_page'      => $per_page,
			'total_pages'   => (int) ceil( $total / $per_page ),
		] );

		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * GET /wp-agent/v1/history/<id>
	 *
	 * Returns a single conversation with its messages.
	 * Verifies the current user owns the conversation.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_conversation( $request ) {
		global $wpdb;

		$tables          = Database::get_table_names();
		$conversation_id = (int) $request->get_param( 'id' );
		$user_id         = get_current_user_id();

		// Fetch conversation and verify ownership.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$conversation = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, user_id, post_id, title, status, model, tokens_used, created_at, updated_at
				FROM {$tables['conversations']}
				WHERE id = %d
				LIMIT 1",
				$conversation_id
			),
			ARRAY_A
		);

		if ( null === $conversation ) {
			return new \WP_Error(
				'not_found',
				__( 'Conversation not found.', 'wp-agent' ),
				[ 'status' => 404 ]
			);
		}

		if ( (int) $conversation['user_id'] !== $user_id ) {
			return new \WP_Error(
				'forbidden',
				__( 'You do not have access to this conversation.', 'wp-agent' ),
				[ 'status' => 403 ]
			);
		}

		// Cast numeric fields.
		$conversation['id']          = (int) $conversation['id'];
		$conversation['post_id']     = $conversation['post_id'] ? (int) $conversation['post_id'] : null;
		$conversation['tokens_used'] = (int) $conversation['tokens_used'];
		unset( $conversation['user_id'] ); // Don't expose user_id in response.

		// Fetch messages.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, role, content, metadata, tokens, model, created_at
				FROM {$tables['messages']}
				WHERE conversation_id = %d
				ORDER BY id ASC",
				$conversation_id
			),
			ARRAY_A
		);

		if ( null === $messages ) {
			$messages = [];
		}

		// Parse message metadata and cast fields.
		foreach ( $messages as &$msg ) {
			$msg['id']     = (int) $msg['id'];
			$msg['tokens'] = (int) $msg['tokens'];

			if ( ! empty( $msg['metadata'] ) ) {
				$decoded = json_decode( $msg['metadata'], true );
				$msg['metadata'] = is_array( $decoded ) ? $decoded : null;
			} else {
				$msg['metadata'] = null;
			}
		}
		unset( $msg );

		return rest_ensure_response( [
			'conversation' => $conversation,
			'messages'     => $messages,
		] );
	}

	/**
	 * Define argument schema for GET /history.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_list_args() {
		return [
			'page'     => [
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			],
			'per_page' => [
				'type'              => 'integer',
				'default'           => self::DEFAULT_PER_PAGE,
				'sanitize_callback' => 'absint',
			],
		];
	}
}
