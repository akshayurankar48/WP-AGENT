<?php
/**
 * Clone Post Action.
 *
 * Duplicates a post or page with all its content, post meta, taxonomies,
 * and featured image. The clone is always created as a draft by default.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Clone_Post
 *
 * @since 1.0.0
 */
class Clone_Post implements Action_Interface {

	/**
	 * Internal meta key prefixes to skip when copying post meta.
	 *
	 * @var string[]
	 */
	const SKIP_META_PREFIXES = [ '_edit_', '_wp_old_slug' ];

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'clone_post';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Duplicate a post or page with all its content, meta, taxonomies, and featured image. '
			. 'Creates the clone as a draft. '
			. 'Use this when the user says "make a copy of my landing page for a new campaign."';
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
				'post_id'    => [
					'type'        => 'integer',
					'description' => 'ID of the post or page to clone.',
				],
				'new_title'  => [
					'type'        => 'string',
					'description' => 'Title for the clone. Defaults to "Copy of {original title}" if omitted.',
				],
				'new_status' => [
					'type'        => 'string',
					'enum'        => [ 'draft', 'publish' ],
					'description' => 'Status for the cloned post. Defaults to "draft".',
					'default'     => 'draft',
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
		$post_id = absint( $params['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'A valid post_id is required.', 'wp-agent' ),
			];
		}

		// Verify the source post exists.
		$source = get_post( $post_id );
		if ( ! $source ) {
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

		// Verify the current user can read the source post.
		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'You do not have permission to read this post.', 'wp-agent' ),
			];
		}

		// Resolve new title.
		if ( ! empty( $params['new_title'] ) ) {
			$new_title = sanitize_text_field( $params['new_title'] );
		} else {
			$new_title = sprintf(
				/* translators: %s: original post title */
				__( 'Copy of %s', 'wp-agent' ),
				sanitize_text_field( $source->post_title )
			);
		}

		// Resolve new status.
		$allowed_statuses = [ 'draft', 'publish' ];
		$new_status       = 'draft';
		if ( ! empty( $params['new_status'] ) ) {
			$candidate = sanitize_key( $params['new_status'] );
			if ( in_array( $candidate, $allowed_statuses, true ) ) {
				$new_status = $candidate;
			}
		}

		// Build the new post array from the source.
		$clone_args = [
			'post_title'     => $new_title,
			'post_content'   => $source->post_content,
			'post_excerpt'   => $source->post_excerpt,
			'post_type'      => $source->post_type,
			'post_status'    => $new_status,
			'post_parent'    => $source->post_parent,
			'menu_order'     => $source->menu_order,
			'comment_status' => $source->comment_status,
			'ping_status'    => $source->ping_status,
		];

		$clone_id = wp_insert_post( $clone_args, true );

		if ( is_wp_error( $clone_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => $clone_id->get_error_message(),
			];
		}

		// Copy post meta.
		$this->copy_post_meta( $post_id, $clone_id );

		// Copy taxonomies.
		$this->copy_taxonomies( $post_id, $clone_id, $source->post_type );

		// Copy featured image.
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( $thumbnail_id ) {
			set_post_thumbnail( $clone_id, $thumbnail_id );
		}

		$clone = get_post( $clone_id );

		return [
			'success' => true,
			'data'    => [
				'new_post_id'      => $clone_id,
				'title'            => sanitize_text_field( $clone->post_title ),
				'edit_url'         => get_edit_post_link( $clone_id, 'raw' ),
				'status'           => $clone->post_status,
				'original_post_id' => $post_id,
			],
			'message' => sprintf(
				/* translators: 1: clone title, 2: clone ID, 3: original ID */
				__( 'Cloned "%1$s" (Post #%2$d) from original Post #%3$d.', 'wp-agent' ),
				sanitize_text_field( $clone->post_title ),
				$clone_id,
				$post_id
			),
		];
	}

	/**
	 * Copy all post meta from source to clone, skipping internal WP keys.
	 *
	 * @since 1.0.0
	 *
	 * @param int $source_id Source post ID.
	 * @param int $clone_id  Clone post ID.
	 * @return void
	 */
	private function copy_post_meta( int $source_id, int $clone_id ): void {
		$all_meta = get_post_meta( $source_id );

		if ( empty( $all_meta ) ) {
			return;
		}

		foreach ( $all_meta as $meta_key => $meta_values ) {
			if ( $this->should_skip_meta_key( $meta_key ) ) {
				continue;
			}

			foreach ( $meta_values as $meta_value ) {
				add_post_meta( $clone_id, $meta_key, maybe_unserialize( $meta_value ) );
			}
		}
	}

	/**
	 * Determine whether a meta key should be skipped during cloning.
	 *
	 * @since 1.0.0
	 *
	 * @param string $meta_key The meta key to check.
	 * @return bool True if the key should be skipped.
	 */
	private function should_skip_meta_key( string $meta_key ): bool {
		foreach ( self::SKIP_META_PREFIXES as $prefix ) {
			if ( 0 === strpos( $meta_key, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Copy all taxonomy terms from source to clone.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $source_id Source post ID.
	 * @param int    $clone_id  Clone post ID.
	 * @param string $post_type Post type of the source post.
	 * @return void
	 */
	private function copy_taxonomies( int $source_id, int $clone_id, string $post_type ): void {
		$taxonomies = get_object_taxonomies( $post_type );

		if ( empty( $taxonomies ) ) {
			return;
		}

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $source_id, $taxonomy, [ 'fields' => 'ids' ] );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			wp_set_object_terms( $clone_id, $terms, $taxonomy );
		}
	}
}
