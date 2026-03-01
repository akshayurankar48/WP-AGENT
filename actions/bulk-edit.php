<?php
/**
 * Bulk Edit Action.
 *
 * Updates multiple posts or pages at once. Supports changing status,
 * author, comment status, ping status, categories, and tags in batch.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Bulk_Edit
 *
 * @since 1.0.0
 */
class Bulk_Edit implements Action_Interface {

	/**
	 * Maximum post IDs allowed per operation.
	 *
	 * @var int
	 */
	const MAX_POST_IDS = 50;

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'bulk_edit';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Update multiple posts or pages at once. Change status, author, categories, or other properties in batch. '
			. 'Use for operations like "publish all draft posts" or "assign all posts to category News". '
			. 'Maximum 50 posts per operation for safety.';
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
				'post_ids' => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => 'Array of post IDs to update. Maximum 50 IDs per operation.',
				],
				'updates'  => [
					'type'        => 'object',
					'description' => 'Fields to update on every matched post. At least one field is required.',
					'properties'  => [
						'post_status'     => [
							'type'        => 'string',
							'enum'        => [ 'publish', 'draft', 'pending', 'private' ],
							'description' => 'New post status.',
						],
						'post_author'     => [
							'type'        => 'integer',
							'description' => 'New author user ID.',
						],
						'comment_status'  => [
							'type'        => 'string',
							'enum'        => [ 'open', 'closed' ],
							'description' => 'New comment status.',
						],
						'ping_status'     => [
							'type'        => 'string',
							'enum'        => [ 'open', 'closed' ],
							'description' => 'New ping status.',
						],
						'categories'      => [
							'type'        => 'array',
							'items'       => [ 'type' => 'integer' ],
							'description' => 'Category IDs to assign. Replaces existing categories.',
						],
						'tags'            => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => 'Tag names to assign. Replaces existing tags.',
						],
					],
				],
			],
			'required'   => [ 'post_ids', 'updates' ],
		];
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'edit_others_posts';
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
		$raw_ids = $params['post_ids'] ?? [];
		$updates = $params['updates'] ?? [];

		// Validate post_ids is a non-empty array.
		if ( ! is_array( $raw_ids ) || empty( $raw_ids ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'post_ids must be a non-empty array.', 'wp-agent' ),
			];
		}

		// Enforce max limit.
		if ( count( $raw_ids ) > self::MAX_POST_IDS ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %d: maximum allowed post IDs */
					__( 'Maximum %d post IDs allowed per bulk operation.', 'wp-agent' ),
					self::MAX_POST_IDS
				),
			];
		}

		// Sanitize post IDs to positive integers.
		$post_ids = array_values( array_filter( array_map( 'absint', $raw_ids ) ) );

		if ( empty( $post_ids ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'post_ids must contain valid positive integers.', 'wp-agent' ),
			];
		}

		// Validate updates is a non-empty array/object.
		if ( ! is_array( $updates ) || empty( $updates ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'updates must be a non-empty object with at least one field.', 'wp-agent' ),
			];
		}

		// Validate and sanitize each update field.
		$sanitized_updates = $this->sanitize_updates( $updates );

		if ( is_wp_error( $sanitized_updates ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => $sanitized_updates->get_error_message(),
			];
		}

		if ( empty( $sanitized_updates ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'No valid update fields provided.', 'wp-agent' ),
			];
		}

		$succeeded      = [];
		$failed         = [];
		$total_requested = count( $post_ids );

		foreach ( $post_ids as $post_id ) {
			$result = $this->process_single_post( $post_id, $sanitized_updates );

			if ( $result['success'] ) {
				$succeeded[] = $post_id;
			} else {
				$failed[] = [
					'id'    => $post_id,
					'error' => $result['error'],
				];
			}
		}

		$succeeded_count = count( $succeeded );
		$failed_count    = count( $failed );

		if ( 0 === $succeeded_count ) {
			$message = sprintf(
				/* translators: %d: number of failed posts */
				_n(
					'Bulk edit failed. %d post could not be updated.',
					'Bulk edit failed. %d posts could not be updated.',
					$failed_count,
					'wp-agent'
				),
				$failed_count
			);
		} elseif ( 0 === $failed_count ) {
			$message = sprintf(
				/* translators: %d: number of updated posts */
				_n(
					'Successfully updated %d post.',
					'Successfully updated %d posts.',
					$succeeded_count,
					'wp-agent'
				),
				$succeeded_count
			);
		} else {
			$message = sprintf(
				/* translators: 1: succeeded count, 2: failed count */
				__( 'Updated %1$d post(s). %2$d could not be updated.', 'wp-agent' ),
				$succeeded_count,
				$failed_count
			);
		}

		return [
			'success' => $succeeded_count > 0,
			'data'    => [
				'total_requested' => $total_requested,
				'succeeded'       => [
					'count' => $succeeded_count,
					'ids'   => $succeeded,
				],
				'failed'          => [
					'count' => $failed_count,
					'items' => $failed,
				],
			],
			'message' => $message,
		];
	}

	/**
	 * Validate and sanitize the updates object.
	 *
	 * Returns an array of sanitized wp_update_post args plus side-effect keys
	 * ('_categories', '_tags'), or a WP_Error on validation failure.
	 *
	 * @since 1.0.0
	 *
	 * @param array $updates Raw updates from params.
	 * @return array|\WP_Error Sanitized updates or WP_Error.
	 */
	private function sanitize_updates( array $updates ) {
		$sanitized = [];

		// post_status.
		if ( isset( $updates['post_status'] ) ) {
			$allowed_statuses = [ 'publish', 'draft', 'pending', 'private' ];
			$status           = sanitize_key( $updates['post_status'] );

			if ( ! in_array( $status, $allowed_statuses, true ) ) {
				return new \WP_Error(
					'invalid_post_status',
					sprintf(
						/* translators: %s: invalid status value */
						__( 'Invalid post_status "%s". Allowed: publish, draft, pending, private.', 'wp-agent' ),
						esc_html( $updates['post_status'] )
					)
				);
			}

			$sanitized['post_status'] = $status;
		}

		// post_author — verify user exists.
		if ( isset( $updates['post_author'] ) ) {
			$author_id = absint( $updates['post_author'] );

			if ( ! $author_id ) {
				return new \WP_Error(
					'invalid_post_author',
					__( 'post_author must be a positive integer.', 'wp-agent' )
				);
			}

			if ( ! get_userdata( $author_id ) ) {
				return new \WP_Error(
					'author_not_found',
					sprintf(
						/* translators: %d: user ID */
						__( 'Author user #%d does not exist.', 'wp-agent' ),
						$author_id
					)
				);
			}

			$sanitized['post_author'] = $author_id;
		}

		// comment_status.
		if ( isset( $updates['comment_status'] ) ) {
			$comment_status = sanitize_key( $updates['comment_status'] );

			if ( ! in_array( $comment_status, [ 'open', 'closed' ], true ) ) {
				return new \WP_Error(
					'invalid_comment_status',
					__( 'comment_status must be "open" or "closed".', 'wp-agent' )
				);
			}

			$sanitized['comment_status'] = $comment_status;
		}

		// ping_status.
		if ( isset( $updates['ping_status'] ) ) {
			$ping_status = sanitize_key( $updates['ping_status'] );

			if ( ! in_array( $ping_status, [ 'open', 'closed' ], true ) ) {
				return new \WP_Error(
					'invalid_ping_status',
					__( 'ping_status must be "open" or "closed".', 'wp-agent' )
				);
			}

			$sanitized['ping_status'] = $ping_status;
		}

		// categories — stored separately; applied via wp_set_post_categories().
		if ( isset( $updates['categories'] ) ) {
			if ( ! is_array( $updates['categories'] ) ) {
				return new \WP_Error(
					'invalid_categories',
					__( 'categories must be an array of integers.', 'wp-agent' )
				);
			}

			$cat_ids = array_values( array_filter( array_map( 'absint', $updates['categories'] ) ) );

			$sanitized['_categories'] = $cat_ids;
		}

		// tags — stored separately; applied via wp_set_post_tags().
		if ( isset( $updates['tags'] ) ) {
			if ( ! is_array( $updates['tags'] ) ) {
				return new \WP_Error(
					'invalid_tags',
					__( 'tags must be an array of strings.', 'wp-agent' )
				);
			}

			$sanitized['_tags'] = array_map( 'sanitize_text_field', $updates['tags'] );
		}

		return $sanitized;
	}

	/**
	 * Apply sanitized updates to a single post.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id         Post ID to update.
	 * @param array $sanitized_updates Sanitized update fields.
	 * @return array { success: bool, error: string }
	 */
	private function process_single_post( int $post_id, array $sanitized_updates ): array {
		// Verify post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [
				'success' => false,
				'error'   => sprintf(
					/* translators: %d: post ID */
					__( 'Post #%d not found.', 'wp-agent' ),
					$post_id
				),
			];
		}

		// Per-post capability check — never trust bulk capability alone.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return [
				'success' => false,
				'error'   => sprintf(
					/* translators: %d: post ID */
					__( 'Permission denied for post #%d.', 'wp-agent' ),
					$post_id
				),
			];
		}

		// Build wp_update_post args (excludes side-effect keys prefixed with _).
		$update_args = [ 'ID' => $post_id ];

		foreach ( $sanitized_updates as $key => $value ) {
			if ( '_' !== substr( $key, 0, 1 ) ) {
				$update_args[ $key ] = $value;
			}
		}

		// Run wp_update_post only if there are core fields to update.
		if ( count( $update_args ) > 1 ) {
			$result = wp_update_post( $update_args, true );

			if ( is_wp_error( $result ) ) {
				return [
					'success' => false,
					'error'   => $result->get_error_message(),
				];
			}
		}

		// Apply categories if provided.
		if ( isset( $sanitized_updates['_categories'] ) ) {
			wp_set_post_categories( $post_id, $sanitized_updates['_categories'] );
		}

		// Apply tags if provided (posts only; pages don't support post_tag by default).
		if ( isset( $sanitized_updates['_tags'] ) ) {
			wp_set_post_tags( $post_id, $sanitized_updates['_tags'] );
		}

		return [ 'success' => true, 'error' => '' ];
	}
}
