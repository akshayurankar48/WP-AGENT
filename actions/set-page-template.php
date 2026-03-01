<?php
/**
 * Set Page Template Action.
 *
 * Sets the page template for a post or page via the _wp_page_template
 * meta key. Works for both classic and block (FSE) themes.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Set_Page_Template
 *
 * @since 1.0.0
 */
class Set_Page_Template implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'set_page_template';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Set the page template for a post or page. '
			. 'Use this to switch between Default, Blank Canvas, Full Width, or any custom template. '
			. 'Critical for landing pages — always set to a blank/full-width template to avoid sidebars and headers interfering with the design. '
			. 'Use get_page_templates first to see available templates.';
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
				'post_id'  => [
					'type'        => 'integer',
					'description' => 'The ID of the post or page to set the template for.',
				],
				'template' => [
					'type'        => 'string',
					'description' => 'Template slug to apply. Common values: "default" (theme default), "blank" (blank canvas in block themes), "page-templates/full-width.php" (classic themes), or any slug from get_page_templates. Use "default" or empty string to reset to the theme\'s default template.',
				],
			],
			'required'   => [ 'post_id', 'template' ],
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
		$post_id  = absint( $params['post_id'] ?? 0 );
		$template = sanitize_text_field( $params['template'] ?? '' );

		if ( ! $post_id ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'A valid post_id is required.', 'wp-agent' ),
			];
		}

		// Verify post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf( __( 'Post #%d not found.', 'wp-agent' ), $post_id ),
			];
		}

		// Check user can edit this specific post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'You do not have permission to edit this post.', 'wp-agent' ),
			];
		}

		// Normalise "default" to empty string — WordPress stores '' for the theme default.
		if ( 'default' === $template ) {
			$template = '';
		}

		$template_name = __( 'Default Template', 'wp-agent' );

		if ( '' !== $template ) {
			if ( wp_is_block_theme() ) {
				$template_name = $this->resolve_block_template_name( $template );
			} else {
				$validation = $this->validate_classic_template( $template, $post );
				if ( is_wp_error( $validation ) ) {
					return [
						'success' => false,
						'data'    => null,
						'message' => $validation->get_error_message(),
					];
				}
				$template_name = $validation;
			}
		}

		update_post_meta( $post_id, '_wp_page_template', $template );

		return [
			'success' => true,
			'data'    => [
				'post_id'       => $post_id,
				'post_title'    => sanitize_text_field( $post->post_title ),
				'template'      => '' === $template ? 'default' : $template,
				'template_name' => $template_name,
			],
			'message' => sprintf(
				/* translators: 1: template name, 2: post title, 3: post ID */
				__( 'Template "%1$s" applied to "%2$s" (Post #%3$d).', 'wp-agent' ),
				$template_name,
				sanitize_text_field( $post->post_title ),
				$post_id
			),
		];
	}

	/**
	 * Resolve a human-readable name for a block theme template slug.
	 *
	 * Attempts to find the template via get_block_templates(). Falls back to
	 * returning the slug itself so the action never hard-fails on an unknown
	 * FSE template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Template slug (e.g. "blank", "single").
	 * @return string Human-readable template name or the raw slug.
	 */
	private function resolve_block_template_name( string $slug ): string {
		// File-extension slugs are not FSE slugs — return as-is.
		if ( false !== strpos( $slug, '.php' ) ) {
			return $slug;
		}

		$templates = get_block_templates( [ 'slug__in' => [ $slug ] ] );

		if ( ! empty( $templates ) && isset( $templates[0]->title ) ) {
			return $templates[0]->title;
		}

		return $slug;
	}

	/**
	 * Validate a template slug against the active classic theme's registered templates.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $template Template slug / file path (e.g. "page-templates/full-width.php").
	 * @param \WP_Post $post     The post being updated (used to scope templates by post type).
	 * @return string|\WP_Error Template name on success, WP_Error on failure.
	 */
	private function validate_classic_template( string $template, \WP_Post $post ) {
		$available = wp_get_theme()->get_page_templates( null, $post->post_type );

		if ( array_key_exists( $template, $available ) ) {
			return $available[ $template ];
		}

		if ( ! empty( $available ) ) {
			$list = implode( ', ', array_keys( $available ) );
			return new \WP_Error(
				'invalid_template',
				sprintf(
					/* translators: 1: requested slug, 2: comma-separated list of available slugs */
					__( 'Template "%1$s" not found in the active theme. Available templates: %2$s', 'wp-agent' ),
					$template,
					$list
				)
			);
		}

		return new \WP_Error(
			'no_templates',
			sprintf(
				/* translators: %s: requested template slug */
				__( 'Template "%s" not found. The active theme has no registered page templates.', 'wp-agent' ),
				$template
			)
		);
	}
}
