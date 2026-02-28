<?php
/**
 * Create Post Action.
 *
 * Creates a new WordPress post via wp_insert_post().
 * Always defaults to 'draft' status for safety.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Create_Post
 *
 * @since 1.0.0
 */
class Create_Post implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'create_post';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Create a new WordPress post or page. Always creates as draft unless explicitly set.';
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
				'post_title'    => [
					'type'        => 'string',
					'description' => 'The title of the post.',
				],
				'post_content'  => [
					'type'        => 'string',
					'description' => 'The full content/body of the post (supports HTML and block markup).',
				],
				'post_excerpt'  => [
					'type'        => 'string',
					'description' => 'A short excerpt/summary of the post.',
				],
				'post_type'     => [
					'type'        => 'string',
					'description' => 'Post type (e.g. "post", "page"). Defaults to "post".',
					'default'     => 'post',
				],
				'post_status'   => [
					'type'        => 'string',
					'description' => 'Post status. Defaults to "draft".',
					'default'     => 'draft',
					'enum'        => [ 'draft', 'publish', 'pending', 'private' ],
				],
				'post_name'     => [
					'type'        => 'string',
					'description' => 'The post slug (URL-friendly name).',
				],
				'post_parent'   => [
					'type'        => 'integer',
					'description' => 'ID of the parent post (for hierarchical post types).',
				],
				'post_category' => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => 'Array of category IDs to assign.',
				],
				'tags_input'    => [
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
					'description' => 'Array of tag names to assign.',
				],
			],
			'required'   => [ 'post_title' ],
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
		$post_type = ! empty( $params['post_type'] ) ? sanitize_text_field( $params['post_type'] ) : 'post';

		if ( ! post_type_exists( $post_type ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: post type name */
					__( 'Invalid post type: %s', 'wp-agent' ),
					$post_type
				),
			];
		}

		$args = [
			'post_title'  => sanitize_text_field( $params['post_title'] ),
			'post_status' => 'draft',
			'post_type'   => $post_type,
		];

		if ( ! empty( $params['post_content'] ) ) {
			$args['post_content'] = wp_kses_post( $params['post_content'] );
		}

		if ( ! empty( $params['post_excerpt'] ) ) {
			$args['post_excerpt'] = sanitize_textarea_field( $params['post_excerpt'] );
		}

		if ( ! empty( $params['post_status'] ) ) {
			$allowed_statuses = [ 'draft', 'publish', 'pending', 'private' ];
			$status           = sanitize_text_field( $params['post_status'] );
			if ( in_array( $status, $allowed_statuses, true ) ) {
				$args['post_status'] = $status;
			}
		}

		if ( ! empty( $params['post_name'] ) ) {
			$args['post_name'] = sanitize_title( $params['post_name'] );
		}

		if ( ! empty( $params['post_parent'] ) ) {
			$args['post_parent'] = absint( $params['post_parent'] );
		}

		if ( ! empty( $params['post_category'] ) ) {
			$args['post_category'] = array_map( 'absint', $params['post_category'] );
		}

		if ( ! empty( $params['tags_input'] ) ) {
			$args['tags_input'] = array_map( 'sanitize_text_field', $params['tags_input'] );
		}

		$result = wp_insert_post( $args, true );

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
				/* translators: 1: post type, 2: post ID, 3: post status */
				__( 'Created %1$s #%2$d with status "%3$s".', 'wp-agent' ),
				$post_type,
				$result,
				$args['post_status']
			),
		];
	}
}
