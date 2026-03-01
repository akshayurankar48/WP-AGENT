<?php
/**
 * Create Pattern Action.
 *
 * Saves a block layout as a reusable WordPress block pattern
 * (wp_block post type). The saved pattern appears in the
 * WordPress inserter for reuse across pages and posts.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Create_Pattern
 *
 * @since 1.0.0
 */
class Create_Pattern implements Action_Interface {

	/**
	 * Valid sync_status values.
	 *
	 * @var string[]
	 */
	const ALLOWED_SYNC_STATUSES = [ 'fully', 'unsynced' ];

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'create_pattern';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Save blocks as a reusable WordPress block pattern. '
			. 'Takes a name and serialized block HTML content and saves it as a wp_block post. '
			. 'The saved pattern appears in the WordPress block inserter for reuse across pages and posts. '
			. 'Optionally assign a category (e.g. "heroes", "features") and control whether edits sync everywhere.';
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
				'name'        => [
					'type'        => 'string',
					'description' => 'Display name for the pattern (e.g. "Homepage Hero Section").',
				],
				'content'     => [
					'type'        => 'string',
					'description' => 'Serialized block HTML markup to save as the pattern content.',
				],
				'category'    => [
					'type'        => 'string',
					'description' => 'Pattern category slug (e.g. "heroes", "features", "cta"). Optional.',
				],
				'description' => [
					'type'        => 'string',
					'description' => 'Short description of what the pattern is for. Optional.',
				],
				'sync_status' => [
					'type'        => 'string',
					'enum'        => [ 'fully', 'unsynced' ],
					'description' => 'Whether edits to the pattern propagate to all uses. "fully" = synced everywhere, "unsynced" = independent copies. Defaults to "unsynced".',
				],
			],
			'required'   => [ 'name', 'content' ],
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
		$name        = sanitize_text_field( $params['name'] ?? '' );
		$content     = wp_kses_post( $params['content'] ?? '' );
		$category    = sanitize_key( $params['category'] ?? '' );
		$description = sanitize_text_field( $params['description'] ?? '' );
		$sync_status = sanitize_key( $params['sync_status'] ?? 'unsynced' );

		if ( empty( $name ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Pattern name is required.', 'wp-agent' ),
			];
		}

		if ( empty( $content ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Pattern content is required.', 'wp-agent' ),
			];
		}

		// Fall back to 'unsynced' for any unrecognised value.
		if ( ! in_array( $sync_status, self::ALLOWED_SYNC_STATUSES, true ) ) {
			$sync_status = 'unsynced';
		}

		$post_data = [
			'post_type'    => 'wp_block',
			'post_title'   => $name,
			'post_content' => $content,
			'post_status'  => 'publish',
		];

		if ( ! empty( $description ) ) {
			$post_data['post_excerpt'] = $description;
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Failed to create pattern: %s', 'wp-agent' ),
					$post_id->get_error_message()
				),
			];
		}

		// Assign pattern category taxonomy term if provided.
		$assigned_category = '';
		if ( ! empty( $category ) ) {
			$term_result = wp_set_object_terms( $post_id, $category, 'wp_pattern_category' );
			if ( ! is_wp_error( $term_result ) && ! empty( $term_result ) ) {
				$assigned_category = $category;
			}
		}

		// Set sync status post meta. 'unsynced' patterns are independent copies;
		// omitting this meta (or setting 'fully') keeps the pattern synced.
		if ( 'unsynced' === $sync_status ) {
			update_post_meta( $post_id, 'wp_block_sync_status', 'unsynced' );
		}

		$edit_url = get_edit_post_link( $post_id, 'raw' );

		return [
			'success' => true,
			'data'    => [
				'post_id'     => $post_id,
				'name'        => $name,
				'edit_url'    => esc_url_raw( $edit_url ),
				'category'    => $assigned_category,
				'sync_status' => $sync_status,
			],
			'message' => sprintf(
				/* translators: 1: pattern name, 2: post ID */
				__( 'Pattern "%1$s" created successfully (ID: %2$d). It is now available in the block inserter.', 'wp-agent' ),
				$name,
				$post_id
			),
		];
	}
}
