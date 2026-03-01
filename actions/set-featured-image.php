<?php
/**
 * Set Featured Image Action.
 *
 * Sets the featured image (post thumbnail) for a post or page
 * using an existing media library attachment.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Set_Featured_Image
 *
 * @since 1.0.0
 */
class Set_Featured_Image implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'set_featured_image';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Set the featured image (thumbnail) for a post or page. '
			. 'Requires the attachment_id of an image already in the media library. '
			. 'Use search_media first to find an image, or import_media to add one from a URL.';
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
					'description' => 'The ID of the post or page to set the featured image for.',
				],
				'attachment_id' => [
					'type'        => 'integer',
					'description' => 'The media library attachment ID to use as the featured image.',
				],
			],
			'required'   => [ 'post_id', 'attachment_id' ],
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
		$post_id       = absint( $params['post_id'] ?? 0 );
		$attachment_id = absint( $params['attachment_id'] ?? 0 );

		if ( ! $post_id || ! $attachment_id ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Both post_id and attachment_id are required.', 'wp-agent' ),
			];
		}

		// Verify post exists and user can edit it.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf( __( 'Post #%d not found.', 'wp-agent' ), $post_id ),
			];
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'You do not have permission to edit this post.', 'wp-agent' ),
			];
		}

		// Verify attachment exists and is an image.
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf( __( 'Attachment #%d not found.', 'wp-agent' ), $attachment_id ),
			];
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'The attachment is not an image.', 'wp-agent' ),
			];
		}

		// Verify post type supports thumbnails.
		if ( ! post_type_supports( $post->post_type, 'thumbnail' ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					__( 'Post type "%s" does not support featured images.', 'wp-agent' ),
					$post->post_type
				),
			];
		}

		$result = set_post_thumbnail( $post_id, $attachment_id );

		if ( ! $result ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Failed to set featured image.', 'wp-agent' ),
			];
		}

		$image_url = wp_get_attachment_url( $attachment_id );

		return [
			'success' => true,
			'data'    => [
				'post_id'       => $post_id,
				'attachment_id' => $attachment_id,
				'image_url'     => esc_url( $image_url ),
			],
			'message' => sprintf(
				__( 'Featured image set for "%s" (Post #%d).', 'wp-agent' ),
				sanitize_text_field( $post->post_title ),
				$post_id
			),
		];
	}
}
