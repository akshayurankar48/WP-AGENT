<?php
/**
 * Search Media Action.
 *
 * Queries the WordPress media library for attachments.
 * Returns image URLs, IDs, alt text, titles, and dimensions
 * so the AI can use real uploaded images when building pages.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Search_Media
 *
 * @since 1.0.0
 */
class Search_Media implements Action_Interface {

	/**
	 * Maximum results per query.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 20;

	/**
	 * Allowed MIME type filters.
	 *
	 * @var string[]
	 */
	const ALLOWED_MIME_TYPES = [
		'image',
		'image/jpeg',
		'image/png',
		'image/webp',
		'image/gif',
		'image/svg+xml',
	];

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'search_media';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Search the WordPress media library for images and files. '
			. 'Returns URLs, IDs, alt text, titles, and dimensions. '
			. 'Use this BEFORE building pages to find real images instead of placeholders. '
			. 'Call with no search term to list recent uploads, or search by keyword to find specific images.';
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
				'search'    => [
					'type'        => 'string',
					'description' => 'Optional keyword to search media by title, caption, or alt text. Leave empty to list recent uploads.',
				],
				'mime_type' => [
					'type'        => 'string',
					'enum'        => self::ALLOWED_MIME_TYPES,
					'description' => 'Filter by MIME type. Defaults to "image" (all image formats).',
				],
				'per_page'  => [
					'type'        => 'integer',
					'description' => 'Number of results to return (1-20). Defaults to 12.',
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
		return 'upload_files';
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
		$search    = ! empty( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';
		$raw_mime  = ! empty( $params['mime_type'] ) ? sanitize_mime_type( $params['mime_type'] ) : 'image';
		$mime_type = in_array( $raw_mime, self::ALLOWED_MIME_TYPES, true ) ? $raw_mime : 'image';
		$per_page  = isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 12;
		$per_page  = max( 1, min( self::MAX_PER_PAGE, $per_page ) );

		$query_args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => $mime_type,
			'posts_per_page' => $per_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( ! empty( $search ) ) {
			$query_args['s'] = $search;
		}

		$query   = new \WP_Query( $query_args );
		$results = [];

		foreach ( $query->posts as $attachment ) {
			$item = $this->format_attachment( $attachment );
			if ( $item ) {
				$results[] = $item;
			}
		}

		wp_reset_postdata();

		if ( empty( $results ) ) {
			$message = $search
				? sprintf(
					/* translators: %s: search term */
					__( 'No media found matching "%s".', 'wp-agent' ),
					$search
				)
				: __( 'No media found in the library.', 'wp-agent' );

			return [
				'success' => true,
				'data'    => [
					'total'   => 0,
					'results' => [],
				],
				'message' => $message,
			];
		}

		$message = $search
			? sprintf(
				/* translators: 1: result count, 2: search term */
				__( 'Found %1$d media item(s) matching "%2$s".', 'wp-agent' ),
				count( $results ),
				$search
			)
			: sprintf(
				/* translators: %d: result count */
				__( 'Found %d recent media item(s).', 'wp-agent' ),
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

	/**
	 * Format a single attachment for the AI response.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $attachment The attachment post object.
	 * @return array|null Formatted attachment data, or null if URL is missing.
	 */
	private function format_attachment( $attachment ) {
		$id = $attachment->ID;

		// Per-attachment permission check (mirrors read-blocks.php pattern).
		if ( ! current_user_can( 'read_post', $id ) ) {
			return null;
		}

		$url = wp_get_attachment_url( $id );

		if ( ! $url ) {
			return null;
		}

		$metadata = wp_get_attachment_metadata( $id );
		if ( ! is_array( $metadata ) ) {
			$metadata = [];
		}

		$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );

		$item = [
			'id'    => $id,
			'url'   => esc_url( $url ),
			'title' => sanitize_text_field( $attachment->post_title ),
			'alt'   => sanitize_text_field( $alt ? $alt : $attachment->post_title ),
			'mime'  => sanitize_mime_type( $attachment->post_mime_type ),
		];

		// Include dimensions for images.
		if ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
			$item['width']  = absint( $metadata['width'] );
			$item['height'] = absint( $metadata['height'] );
		}

		// Include available sizes for images.
		if ( ! empty( $metadata['sizes'] ) ) {
			$base_url = trailingslashit( dirname( $url ) );
			$sizes    = [];
			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				if ( ! empty( $size_data['file'] ) ) {
					$sizes[ sanitize_key( $size_name ) ] = [
						'url'    => esc_url( $base_url . $size_data['file'] ),
						'width'  => absint( $size_data['width'] ),
						'height' => absint( $size_data['height'] ),
					];
				}
			}
			if ( ! empty( $sizes ) ) {
				$item['sizes'] = $sizes;
			}
		}

		return $item;
	}
}
