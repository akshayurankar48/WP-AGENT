<?php
/**
 * Edit Template Parts Action.
 *
 * Lists, gets, or updates template parts (header, footer, sidebar)
 * in block themes. Uses the WordPress block template API to read
 * and modify template parts.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Edit_Template_Parts
 *
 * @since 1.0.0
 */
class Edit_Template_Parts implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'edit_template_parts';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'List, get, or update template parts (header, footer, sidebar) in block themes. '
			. 'Operations: "list" shows all template parts, "get" returns the block content of a specific part, '
			. '"update" modifies a template part\'s content (replace, append, or prepend). '
			. 'Only works with block themes (FSE).';
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
				'operation' => [
					'type'        => 'string',
					'enum'        => [ 'list', 'get', 'update' ],
					'description' => 'Operation to perform.',
				],
				'slug'      => [
					'type'        => 'string',
					'description' => 'Template part slug (e.g. "header", "footer"). Required for "get" and "update".',
				],
				'content'   => [
					'type'        => 'string',
					'description' => 'New block content (HTML block markup) for "update" operation.',
				],
				'mode'      => [
					'type'        => 'string',
					'enum'        => [ 'replace', 'append', 'prepend' ],
					'description' => 'Update mode: "replace" overwrites all content, "append" adds after, "prepend" adds before. Defaults to "replace".',
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
		return 'edit_theme_options';
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
		// Block theme check.
		if ( ! wp_is_block_theme() ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Template parts are only available in block themes (Full Site Editing). The current theme is not a block theme.', 'wp-agent' ),
			];
		}

		$operation = $params['operation'] ?? '';

		switch ( $operation ) {
			case 'list':
				return $this->list_template_parts();

			case 'get':
				$slug = ! empty( $params['slug'] ) ? sanitize_title( $params['slug'] ) : '';
				return $this->get_template_part( $slug );

			case 'update':
				$slug    = ! empty( $params['slug'] ) ? sanitize_title( $params['slug'] ) : '';
				$content = $params['content'] ?? '';
				$mode    = ! empty( $params['mode'] ) ? sanitize_key( $params['mode'] ) : 'replace';
				return $this->update_template_part( $slug, $content, $mode );

			default:
				return [
					'success' => false,
					'data'    => null,
					'message' => __( 'Invalid operation. Use "list", "get", or "update".', 'wp-agent' ),
				];
		}
	}

	/**
	 * List all template parts.
	 *
	 * @since 1.0.0
	 * @return array Execution result.
	 */
	private function list_template_parts() {
		$parts   = get_block_templates( [], 'wp_template_part' );
		$results = [];

		foreach ( $parts as $part ) {
			$results[] = [
				'slug'   => $part->slug,
				'title'  => ! empty( $part->title ) ? $part->title : $part->slug,
				'area'   => ! empty( $part->area ) ? $part->area : 'uncategorized',
				'source' => $part->source ?? 'theme',
				'has_custom_content' => ! empty( $part->wp_id ),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'total' => count( $results ),
				'parts' => $results,
			],
			'message' => sprintf(
				/* translators: %d: part count */
				__( '%d template part(s) found.', 'wp-agent' ),
				count( $results )
			),
		];
	}

	/**
	 * Get a specific template part.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Template part slug.
	 * @return array Execution result.
	 */
	private function get_template_part( $slug ) {
		if ( empty( $slug ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Template part slug is required.', 'wp-agent' ),
			];
		}

		$template_part = $this->find_template_part( $slug );

		if ( ! $template_part ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: template part slug */
					__( 'Template part "%s" not found.', 'wp-agent' ),
					$slug
				),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'slug'    => $template_part->slug,
				'title'   => ! empty( $template_part->title ) ? $template_part->title : $template_part->slug,
				'area'    => ! empty( $template_part->area ) ? $template_part->area : 'uncategorized',
				'content' => $template_part->content,
				'source'  => $template_part->source ?? 'theme',
			],
			'message' => sprintf(
				/* translators: %s: template part slug */
				__( 'Template part "%s" retrieved.', 'wp-agent' ),
				$slug
			),
		];
	}

	/**
	 * Update a template part.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug    Template part slug.
	 * @param string $content New content.
	 * @param string $mode    Update mode (replace, append, prepend).
	 * @return array Execution result.
	 */
	private function update_template_part( $slug, $content, $mode ) {
		if ( empty( $slug ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Template part slug is required.', 'wp-agent' ),
			];
		}

		if ( empty( $content ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Content is required for update.', 'wp-agent' ),
			];
		}

		if ( ! in_array( $mode, [ 'replace', 'append', 'prepend' ], true ) ) {
			$mode = 'replace';
		}

		$template_part = $this->find_template_part( $slug );

		if ( ! $template_part ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: template part slug */
					__( 'Template part "%s" not found.', 'wp-agent' ),
					$slug
				),
			];
		}

		// Compose the final content based on mode.
		$existing_content = $template_part->content;
		switch ( $mode ) {
			case 'append':
				$final_content = $existing_content . "\n" . $content;
				break;
			case 'prepend':
				$final_content = $content . "\n" . $existing_content;
				break;
			default:
				$final_content = $content;
				break;
		}

		// Check if there's already a DB override (wp_template_part post).
		if ( ! empty( $template_part->wp_id ) ) {
			// Update the existing post.
			$result = wp_update_post(
				[
					'ID'           => $template_part->wp_id,
					'post_content' => $final_content,
				],
				true
			);

			if ( is_wp_error( $result ) ) {
				return [
					'success' => false,
					'data'    => null,
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Failed to update template part: %s', 'wp-agent' ),
						$result->get_error_message()
					),
				];
			}
		} else {
			// Create a new wp_template_part post to override the theme file.
			$theme = get_stylesheet();
			$result = wp_insert_post(
				[
					'post_type'    => 'wp_template_part',
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => ! empty( $template_part->title ) ? $template_part->title : $slug,
					'post_content' => $final_content,
					'tax_input'    => [
						'wp_theme'              => [ $theme ],
						'wp_template_part_area' => [ ! empty( $template_part->area ) ? $template_part->area : 'uncategorized' ],
					],
				],
				true
			);

			if ( is_wp_error( $result ) ) {
				return [
					'success' => false,
					'data'    => null,
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Failed to create template part override: %s', 'wp-agent' ),
						$result->get_error_message()
					),
				];
			}

			// Set the theme taxonomy term since tax_input may not work for custom taxonomies.
			wp_set_object_terms( $result, $theme, 'wp_theme' );
			if ( ! empty( $template_part->area ) ) {
				wp_set_object_terms( $result, $template_part->area, 'wp_template_part_area' );
			}
		}

		return [
			'success' => true,
			'data'    => [
				'slug' => $slug,
				'mode' => $mode,
			],
			'message' => sprintf(
				/* translators: 1: template part slug, 2: mode */
				__( 'Template part "%1$s" updated (%2$s).', 'wp-agent' ),
				$slug,
				$mode
			),
		];
	}

	/**
	 * Find a template part by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Template part slug.
	 * @return \WP_Block_Template|null The template part or null.
	 */
	private function find_template_part( $slug ) {
		$theme = get_stylesheet();
		$id    = $theme . '//' . $slug;

		$template_part = get_block_template( $id, 'wp_template_part' );

		return $template_part ? $template_part : null;
	}
}
