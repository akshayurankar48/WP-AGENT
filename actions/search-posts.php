<?php
/**
 * Search Posts Action.
 *
 * Queries WordPress posts and pages by title, type, and status.
 * Returns metadata for the AI to reference existing content.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Search_Posts
 *
 * @since 1.0.0
 */
class Search_Posts implements Action_Interface {

	/**
	 * Maximum results per query.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 20;

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'search_posts';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Search for existing WordPress posts and pages by title keyword, post type, or status. '
			. 'Returns ID, title, type, status, date, edit URL, and excerpt. '
			. 'Use this to find content before editing, duplicating, or referencing it.';
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
				'search'      => [
					'type'        => 'string',
					'description' => 'Keyword to search post titles. Leave empty to list recent posts.',
				],
				'post_type'   => [
					'type'        => 'string',
					'enum'        => [ 'post', 'page', 'any' ],
					'description' => 'Filter by post type. Defaults to "any".',
				],
				'post_status' => [
					'type'        => 'string',
					'enum'        => [ 'publish', 'draft', 'pending', 'private', 'any' ],
					'description' => 'Filter by post status. Defaults to "publish". Admins can use "any" or "private".',
				],
				'per_page'    => [
					'type'        => 'integer',
					'description' => 'Number of results (1-20). Defaults to 10.',
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
		return 'read';
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
		$search      = ! empty( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';
		$post_type   = ! empty( $params['post_type'] ) ? sanitize_key( $params['post_type'] ) : 'any';
		$post_status = ! empty( $params['post_status'] ) ? sanitize_key( $params['post_status'] ) : 'any';
		$per_page    = isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 10;
		$per_page    = max( 1, min( self::MAX_PER_PAGE, $per_page ) );

		// Validate post_type.
		$allowed_types = [ 'post', 'page', 'any' ];
		if ( ! in_array( $post_type, $allowed_types, true ) ) {
			$post_type = 'any';
		}

		// Validate post_status — restrict to safe statuses unless user can edit others' posts.
		if ( current_user_can( 'edit_others_posts' ) ) {
			$allowed_statuses = [ 'publish', 'draft', 'pending', 'private', 'trash', 'any' ];
		} else {
			$allowed_statuses = [ 'publish', 'draft' ];
		}
		if ( ! in_array( $post_status, $allowed_statuses, true ) ) {
			$post_status = 'publish';
		}

		// Map 'any' to explicit safe statuses to avoid leaking private content.
		if ( 'any' === $post_status ) {
			$post_status = current_user_can( 'edit_others_posts' )
				? [ 'publish', 'draft', 'pending', 'private' ]
				: [ 'publish' ];
		}

		$query_args = [
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => $per_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( ! empty( $search ) ) {
			$query_args['s'] = $search;
		}

		$query   = new \WP_Query( $query_args );
		$results = [];

		foreach ( $query->posts as $post ) {
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}

			$results[] = [
				'id'       => $post->ID,
				'title'    => sanitize_text_field( $post->post_title ),
				'type'     => $post->post_type,
				'status'   => $post->post_status,
				'date'     => $post->post_date,
				'edit_url' => get_edit_post_link( $post->ID, 'raw' ),
				'excerpt'  => wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 ),
			];
		}

		wp_reset_postdata();

		if ( empty( $results ) ) {
			$message = $search
				? sprintf( __( 'No posts found matching "%s".', 'wp-agent' ), $search )
				: __( 'No posts found.', 'wp-agent' );

			return [
				'success' => true,
				'data'    => [ 'total' => 0, 'results' => [] ],
				'message' => $message,
			];
		}

		$message = sprintf(
			__( 'Found %d post(s).', 'wp-agent' ),
			count( $results )
		);

		return [
			'success' => true,
			'data'    => [
				'total'   => count( $results ),
				'results' => $results,
			],
			'message' => $message,
		];
	}
}
