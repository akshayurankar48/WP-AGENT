<?php
/**
 * Edit Post Action.
 *
 * Updates an existing WordPress post via wp_update_post().
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Edit_Post
 *
 * @since 1.0.0
 */
class Edit_Post implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'edit_post';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Update an existing WordPress post or page. Only the fields you provide will be changed.';
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
				'post_id'       => [
					'type'        => 'integer',
					'description' => 'The ID of the post to update.',
				],
				'post_title'    => [
					'type'        => 'string',
					'description' => 'New title for the post.',
				],
				'post_content'  => [
					'type'        => 'string',
					'description' => 'New content/body for the post (supports HTML and block markup).',
				],
				'post_excerpt'  => [
					'type'        => 'string',
					'description' => 'New excerpt/summary for the post.',
				],
				'post_status'   => [
					'type'        => 'string',
					'description' => 'New status for the post.',
					'enum'        => [ 'draft', 'publish', 'pending', 'private' ],
				],
				'post_name'     => [
					'type'        => 'string',
					'description' => 'New slug (URL-friendly name) for the post.',
				],
				'post_parent'   => [
					'type'        => 'integer',
					'description' => 'New parent post ID (for hierarchical post types).',
				],
				'post_category' => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => 'Array of category IDs to assign (replaces existing).',
				],
				'tags_input'    => [
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
					'description' => 'Array of tag names to assign (replaces existing).',
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
		return 'edit_posts';
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

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'You do not have permission to edit this post.', 'wp-agent' ),
			];
		}

		$args = [ 'ID' => $post_id ];

		if ( isset( $params['post_title'] ) ) {
			$args['post_title'] = sanitize_text_field( $params['post_title'] );
		}

		if ( isset( $params['post_content'] ) ) {
			$args['post_content'] = wp_kses_post( $params['post_content'] );
		}

		if ( isset( $params['post_excerpt'] ) ) {
			$args['post_excerpt'] = sanitize_textarea_field( $params['post_excerpt'] );
		}

		if ( ! empty( $params['post_status'] ) ) {
			$allowed_statuses = [ 'draft', 'publish', 'pending', 'private' ];
			$status           = sanitize_text_field( $params['post_status'] );
			if ( in_array( $status, $allowed_statuses, true ) ) {
				$args['post_status'] = $status;
			}
		}

		if ( isset( $params['post_name'] ) ) {
			$args['post_name'] = sanitize_title( $params['post_name'] );
		}

		if ( isset( $params['post_parent'] ) ) {
			$args['post_parent'] = absint( $params['post_parent'] );
		}

		if ( isset( $params['post_category'] ) ) {
			$args['post_category'] = array_map( 'absint', $params['post_category'] );
		}

		if ( isset( $params['tags_input'] ) ) {
			$args['tags_input'] = array_map( 'sanitize_text_field', $params['tags_input'] );
		}

		$result = wp_update_post( $args, true );

		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => $result->get_error_message(),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'post_id'  => $result,
				'edit_url' => get_edit_post_link( $result, 'raw' ),
			],
			'message' => sprintf(
				/* translators: %d: post ID */
				__( 'Updated post #%d.', 'wp-agent' ),
				$result
			),
		];
	}
}
