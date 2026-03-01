<?php
/**
 * List Users Action.
 *
 * Queries WordPress users with optional role filter.
 * Returns user metadata for the AI to reference.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class List_Users
 *
 * @since 1.0.0
 */
class List_Users implements Action_Interface {

	/**
	 * Maximum results per query.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 50;

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'list_users';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'List WordPress users with optional role filter. '
			. 'Returns ID, login, email, display name, role, and registration date.';
	}

	/**
	 * Get the JSON Schema for parameters.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_parameters(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'role'     => [
					'type'        => 'string',
					'enum'        => [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ],
					'description' => 'Filter by user role. Omit to list all users.',
				],
				'search'   => [
					'type'        => 'string',
					'description' => 'Search by username, email, or display name.',
				],
				'per_page' => [
					'type'        => 'integer',
					'description' => 'Number of results (1-50). Defaults to 20.',
				],
			],
			'required'   => [],
		];
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'list_users';
	}

	/**
	 * Whether this action is reversible.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_reversible(): bool {
		return false;
	}

	/**
	 * Execute the action.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Validated parameters.
	 * @return array Execution result.
	 */
	public function execute( array $params ): array {
		$role     = ! empty( $params['role'] ) ? sanitize_key( $params['role'] ) : '';
		$search   = ! empty( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';
		$per_page = isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 20;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );

		$query_args = [
			'number'  => $per_page,
			'orderby' => 'registered',
			'order'   => 'DESC',
		];

		if ( $role ) {
			$query_args['role'] = $role;
		}

		if ( $search ) {
			$query_args['search']         = '*' . $search . '*';
			$query_args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
		}

		$user_query = new \WP_User_Query( $query_args );
		$users      = $user_query->get_results();
		$results    = [];

		foreach ( $users as $user ) {
			$roles = $user->roles;

			// Mask email to prevent PII leakage through AI responses.
			$email       = sanitize_email( $user->user_email );
			$at_pos      = strpos( $email, '@' );
			$masked_email = $at_pos > 1
				? substr( $email, 0, 1 ) . str_repeat( '*', $at_pos - 1 ) . substr( $email, $at_pos )
				: $email;

			$results[] = [
				'id'           => $user->ID,
				'login'        => sanitize_user( $user->user_login ),
				'email'        => $masked_email,
				'display_name' => sanitize_text_field( $user->display_name ),
				'role'         => ! empty( $roles ) ? sanitize_key( reset( $roles ) ) : 'none',
				'registered'   => $user->user_registered,
			];
		}

		$total = $user_query->get_total();

		if ( empty( $results ) ) {
			return [
				'success' => true,
				'data'    => [ 'total' => 0, 'users' => [] ],
				'message' => __( 'No users found.', 'wp-agent' ),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'total' => $total,
				'users' => $results,
			],
			'message' => sprintf(
				__( 'Found %d user(s).', 'wp-agent' ),
				$total
			),
		];
	}
}
