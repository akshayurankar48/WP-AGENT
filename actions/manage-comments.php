<?php
/**
 * Manage Comments Action.
 *
 * Provides comment moderation capabilities: list, approve, unapprove,
 * spam, trash, reply, and bulk operations. Includes email masking
 * in list output for privacy.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Manage_Comments
 *
 * @since 1.0.0
 */
class Manage_Comments implements Action_Interface {

	/**
	 * Maximum comments per page for list.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 50;

	/**
	 * Maximum comments for bulk operations.
	 *
	 * @var int
	 */
	const MAX_BULK = 100;

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'manage_comments';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Manage WordPress comments. Operations: "list" (with filters), "approve", "unapprove", '
			. '"spam", "trash", "reply" (post a reply to a comment), "bulk" (approve/spam/trash multiple). '
			. 'Use for comment moderation and responding to user feedback.';
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
				'operation'  => [
					'type'        => 'string',
					'enum'        => [ 'list', 'approve', 'unapprove', 'spam', 'trash', 'reply', 'bulk' ],
					'description' => 'Operation to perform.',
				],
				'comment_id' => [
					'type'        => 'integer',
					'description' => 'Comment ID. Required for approve, unapprove, spam, trash, and reply.',
				],
				'post_id'    => [
					'type'        => 'integer',
					'description' => 'Filter comments by post ID (for "list" operation).',
				],
				'status'     => [
					'type'        => 'string',
					'enum'        => [ 'all', 'approve', 'hold', 'spam', 'trash' ],
					'description' => 'Filter by comment status for "list". Defaults to "all".',
				],
				'search'     => [
					'type'        => 'string',
					'description' => 'Search comments by keyword (for "list").',
				],
				'per_page'   => [
					'type'        => 'integer',
					'description' => 'Number of comments to return (max 50). Defaults to 20.',
				],
				'content'    => [
					'type'        => 'string',
					'description' => 'Reply content (for "reply" operation).',
				],
				'comment_ids' => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => 'Array of comment IDs for "bulk" operation (max 100).',
				],
				'bulk_action' => [
					'type'        => 'string',
					'enum'        => [ 'approve', 'unapprove', 'spam', 'trash' ],
					'description' => 'Action to apply in "bulk" operation.',
				],
			],
			'required'   => [ 'operation' ],
		];
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'moderate_comments';
	}

	/**
	 * Whether this action is reversible.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_reversible(): bool {
		return true;
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
		$operation = $params['operation'] ?? '';

		switch ( $operation ) {
			case 'list':
				return $this->list_comments( $params );

			case 'approve':
			case 'unapprove':
			case 'spam':
			case 'trash':
				$comment_id = isset( $params['comment_id'] ) ? absint( $params['comment_id'] ) : 0;
				return $this->moderate_comment( $comment_id, $operation );

			case 'reply':
				$comment_id = isset( $params['comment_id'] ) ? absint( $params['comment_id'] ) : 0;
				$content    = $params['content'] ?? '';
				return $this->reply_to_comment( $comment_id, $content );

			case 'bulk':
				$comment_ids = isset( $params['comment_ids'] ) && is_array( $params['comment_ids'] ) ? $params['comment_ids'] : [];
				$bulk_action = ! empty( $params['bulk_action'] ) ? sanitize_key( $params['bulk_action'] ) : '';
				return $this->bulk_moderate( $comment_ids, $bulk_action );

			default:
				return [
					'success' => false,
					'data'    => null,
					'message' => __( 'Invalid operation.', 'wp-agent' ),
				];
		}
	}

	/**
	 * List comments with filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Filter parameters.
	 * @return array Execution result.
	 */
	private function list_comments( array $params ) {
		$per_page = isset( $params['per_page'] ) ? min( absint( $params['per_page'] ), self::MAX_PER_PAGE ) : 20;

		$args = [
			'number' => $per_page,
			'orderby' => 'comment_date',
			'order'   => 'DESC',
		];

		if ( ! empty( $params['status'] ) && 'all' !== $params['status'] ) {
			$args['status'] = sanitize_key( $params['status'] );
		}

		if ( ! empty( $params['post_id'] ) ) {
			$args['post_id'] = absint( $params['post_id'] );
		}

		if ( ! empty( $params['search'] ) ) {
			$args['search'] = sanitize_text_field( $params['search'] );
		}

		$comments = get_comments( $args );
		$results  = [];

		foreach ( $comments as $comment ) {
			$results[] = [
				'id'          => (int) $comment->comment_ID,
				'post_id'     => (int) $comment->comment_post_ID,
				'post_title'  => get_the_title( $comment->comment_post_ID ),
				'author'      => sanitize_text_field( $comment->comment_author ),
				'email'       => $this->mask_email( $comment->comment_author_email ),
				'content'     => wp_trim_words( $comment->comment_content, 30 ),
				'status'      => wp_get_comment_status( $comment ),
				'date'        => $comment->comment_date,
				'parent'      => (int) $comment->comment_parent,
			];
		}

		// Get counts by status.
		$counts = wp_count_comments();

		return [
			'success' => true,
			'data'    => [
				'comments' => $results,
				'total'    => count( $results ),
				'counts'   => [
					'approved' => (int) $counts->approved,
					'pending'  => (int) $counts->moderated,
					'spam'     => (int) $counts->spam,
					'trash'    => (int) $counts->trash,
					'total'    => (int) $counts->total_comments,
				],
			],
			'message' => sprintf(
				/* translators: 1: returned count, 2: pending count */
				__( '%1$d comment(s) returned. %2$d pending moderation.', 'wp-agent' ),
				count( $results ),
				(int) $counts->moderated
			),
		];
	}

	/**
	 * Moderate a single comment.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $comment_id Comment ID.
	 * @param string $action     Moderation action.
	 * @return array Execution result.
	 */
	private function moderate_comment( $comment_id, $action ) {
		if ( ! $comment_id ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Comment ID is required.', 'wp-agent' ),
			];
		}

		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Comment not found.', 'wp-agent' ),
			];
		}

		$status_map = [
			'approve'   => '1',
			'unapprove' => '0',
			'spam'      => 'spam',
			'trash'     => 'trash',
		];

		$new_status = $status_map[ $action ] ?? '';
		if ( '' === $new_status ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Invalid moderation action.', 'wp-agent' ),
			];
		}

		$result = wp_set_comment_status( $comment_id, $new_status );

		if ( ! $result ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: action */
					__( 'Failed to %s comment.', 'wp-agent' ),
					$action
				),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'comment_id' => $comment_id,
				'action'     => $action,
			],
			'message' => sprintf(
				/* translators: 1: action (past tense), 2: comment ID */
				__( 'Comment #%2$d %1$s.', 'wp-agent' ),
				$this->past_tense( $action ),
				$comment_id
			),
		];
	}

	/**
	 * Reply to a comment.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $comment_id Parent comment ID.
	 * @param string $content    Reply content.
	 * @return array Execution result.
	 */
	private function reply_to_comment( $comment_id, $content ) {
		if ( ! $comment_id ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Comment ID is required.', 'wp-agent' ),
			];
		}

		if ( empty( $content ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Reply content is required.', 'wp-agent' ),
			];
		}

		$parent_comment = get_comment( $comment_id );
		if ( ! $parent_comment ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Parent comment not found.', 'wp-agent' ),
			];
		}

		$current_user = wp_get_current_user();

		$reply_data = [
			'comment_post_ID'  => $parent_comment->comment_post_ID,
			'comment_parent'   => $comment_id,
			'comment_content'  => wp_kses_post( $content ),
			'comment_approved' => 1,
			'user_id'          => $current_user->ID,
			'comment_author'   => $current_user->display_name,
			'comment_author_email' => $current_user->user_email,
		];

		$reply_id = wp_insert_comment( $reply_data );

		if ( ! $reply_id ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Failed to post reply.', 'wp-agent' ),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'reply_id'   => $reply_id,
				'parent_id'  => $comment_id,
				'post_id'    => (int) $parent_comment->comment_post_ID,
			],
			'message' => sprintf(
				/* translators: 1: reply ID, 2: parent comment ID */
				__( 'Reply #%1$d posted to comment #%2$d.', 'wp-agent' ),
				$reply_id,
				$comment_id
			),
		];
	}

	/**
	 * Bulk moderate comments.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $comment_ids Array of comment IDs.
	 * @param string $action      Bulk action to apply.
	 * @return array Execution result.
	 */
	private function bulk_moderate( array $comment_ids, $action ) {
		if ( empty( $comment_ids ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Comment IDs are required for bulk operations.', 'wp-agent' ),
			];
		}

		if ( ! in_array( $action, [ 'approve', 'unapprove', 'spam', 'trash' ], true ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Invalid bulk action. Use "approve", "unapprove", "spam", or "trash".', 'wp-agent' ),
			];
		}

		// Cap the number of comments.
		$comment_ids = array_slice( array_map( 'absint', $comment_ids ), 0, self::MAX_BULK );

		$status_map = [
			'approve'   => '1',
			'unapprove' => '0',
			'spam'      => 'spam',
			'trash'     => 'trash',
		];

		$success_count = 0;
		$fail_count    = 0;

		foreach ( $comment_ids as $id ) {
			if ( ! get_comment( $id ) ) {
				++$fail_count;
				continue;
			}

			$result = wp_set_comment_status( $id, $status_map[ $action ] );
			if ( $result ) {
				++$success_count;
			} else {
				++$fail_count;
			}
		}

		return [
			'success' => $success_count > 0,
			'data'    => [
				'action'    => $action,
				'succeeded' => $success_count,
				'failed'    => $fail_count,
				'total'     => count( $comment_ids ),
			],
			'message' => sprintf(
				/* translators: 1: success count, 2: total count, 3: action (past tense) */
				__( '%1$d of %2$d comment(s) %3$s.', 'wp-agent' ),
				$success_count,
				count( $comment_ids ),
				$this->past_tense( $action )
			),
		];
	}

	/**
	 * Mask an email address for privacy.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address.
	 * @return string Masked email (e.g. j***@example.com).
	 */
	private function mask_email( $email ) {
		if ( empty( $email ) || false === strpos( $email, '@' ) ) {
			return '';
		}

		$parts  = explode( '@', $email );
		$local  = $parts[0];
		$domain = $parts[1];

		$masked_local = substr( $local, 0, 1 ) . '***';

		return $masked_local . '@' . $domain;
	}

	/**
	 * Get past tense of a moderation action.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action Action verb.
	 * @return string Past tense.
	 */
	private function past_tense( $action ) {
		$map = [
			'approve'   => 'approved',
			'unapprove' => 'unapproved',
			'spam'      => 'marked as spam',
			'trash'     => 'trashed',
		];

		return $map[ $action ] ?? $action;
	}
}
