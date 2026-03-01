<?php
/**
 * Delete Post Action.
 *
 * Moves a WordPress post to trash via wp_trash_post().
 * Never permanently deletes — always uses trash for safety.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Delete_Post
 *
 * @since 1.0.0
 */
class Delete_Post implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'delete_post';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Move a WordPress post or page to trash. Does not permanently delete — the post can be restored from trash.';
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
				'post_id' => [
					'type'        => 'integer',
					'description' => 'The ID of the post to move to trash.',
				],
			],
			'required'   => [ 'post_id' ],
		];
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'delete_posts';
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
		$post_id = absint( $params['post_id'] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %d: post ID */
					__( 'Post #%d not found.', 'wp-agent' ),
					$post_id
				),
			];
		}

		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'You do not have permission to delete this post.', 'wp-agent' ),
			];
		}

		if ( 'trash' === $post->post_status ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %d: post ID */
					__( 'Post #%d is already in trash.', 'wp-agent' ),
					$post_id
				),
			];
		}

		$result = wp_trash_post( $post_id );

		if ( ! $result ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %d: post ID */
					__( 'Failed to trash post #%d.', 'wp-agent' ),
					$post_id
				),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'post_id' => $post_id,
			],
			'message' => sprintf(
				/* translators: 1: post title, 2: post ID */
				__( 'Moved "%1$s" (post #%2$d) to trash.', 'wp-agent' ),
				$post->post_title,
				$post_id
			),
		];
	}
}
