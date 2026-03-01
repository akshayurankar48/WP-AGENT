<?php
/**
 * Get Page Templates Action.
 *
 * Lists available page templates from the active theme and, for block
 * themes, returns FSE templates and template parts as well. The AI uses
 * this to pick the right template when creating or editing pages
 * (e.g. blank canvas for landing pages, full-width for marketing pages).
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Get_Page_Templates
 *
 * @since 1.0.0
 */
class Get_Page_Templates implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'get_page_templates';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'List available page templates and template parts from the active theme. '
			. 'For classic themes this returns PHP page templates (e.g. blank, full-width, sidebar). '
			. 'For block (FSE) themes it also returns wp_template and wp_template_part slugs. '
			. 'Use this before creating or editing a page to pick the most appropriate template — '
			. 'for example, use a blank/canvas template for landing pages and a full-width template '
			. 'for marketing or hero pages.';
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
				'post_type' => [
					'type'        => 'string',
					'description' => 'The post type to retrieve templates for. Defaults to "page".',
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
		return 'read';
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
		$post_type = ! empty( $params['post_type'] )
			? sanitize_key( $params['post_type'] )
			: 'page';

		// Validate that the post type is registered.
		if ( ! post_type_exists( $post_type ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: post type slug */
					__( 'Post type "%s" does not exist.', 'wp-agent' ),
					$post_type
				),
			];
		}

		$templates      = [];
		$is_block_theme = wp_is_block_theme();

		// Always include the default template first.
		$templates[] = [
			'slug'   => 'default',
			'name'   => __( 'Default Template', 'wp-agent' ),
			'source' => 'theme',
			'type'   => 'template',
		];

		// Classic page templates from the active theme.
		$classic_templates = wp_get_theme()->get_page_templates( null, $post_type );

		foreach ( $classic_templates as $file => $name ) {
			$templates[] = [
				'slug'   => sanitize_text_field( $file ),
				'name'   => sanitize_text_field( $name ),
				'source' => 'theme',
				'type'   => 'template',
			];
		}

		// For block themes, also surface FSE templates and template parts.
		if ( $is_block_theme ) {
			$fse_templates = get_block_templates( [], 'wp_template' );

			foreach ( $fse_templates as $tpl ) {
				$templates[] = [
					'slug'   => sanitize_text_field( $tpl->slug ),
					'name'   => sanitize_text_field( $tpl->title ),
					'source' => sanitize_key( $tpl->source ),
					'type'   => 'template',
				];
			}

			$template_parts = get_block_templates( [], 'wp_template_part' );

			foreach ( $template_parts as $part ) {
				$templates[] = [
					'slug'   => sanitize_text_field( $part->slug ),
					'name'   => sanitize_text_field( $part->title ),
					'source' => sanitize_key( $part->source ),
					'type'   => 'template_part',
				];
			}
		}

		$total = count( $templates );

		return [
			'success' => true,
			'data'    => [
				'templates'      => $templates,
				'is_block_theme' => $is_block_theme,
				'total'          => $total,
			],
			'message' => sprintf(
				/* translators: 1: number of templates, 2: post type slug */
				__( 'Found %1$d template(s) for post type "%2$s".', 'wp-agent' ),
				$total,
				$post_type
			),
		];
	}
}
