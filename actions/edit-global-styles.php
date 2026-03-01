<?php
/**
 * Edit Global Styles Action.
 *
 * Reads or updates the site's global styles (theme.json) — colors, typography,
 * spacing, and element styles. Controls the site-wide look and feel for block themes.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Edit_Global_Styles
 *
 * @since 1.0.0
 */
class Edit_Global_Styles implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'edit_global_styles';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Read or update the site\'s global styles (theme.json) — colors, typography, spacing, and element styles. '
			. 'This controls the site-wide look and feel. Use this to set a cohesive color palette, modern fonts, '
			. 'consistent spacing, button styles, and link colors across the entire site. '
			. 'Works with block themes (FSE). For classic themes, some styles may not apply.';
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
					'enum'        => [ 'get', 'update' ],
					'description' => 'Read current styles ("get") or update them ("update").',
				],
				'styles'    => [
					'type'        => 'object',
					'description' => 'Nested style object to MERGE into existing global styles. Only required for "update". Structure mirrors theme.json styles.',
					'properties'  => [
						'color'      => [
							'type'        => 'object',
							'description' => 'Site-wide color settings.',
							'properties'  => [
								'palette'    => [
									'type'        => 'array',
									'description' => 'Custom color palette entries. Each item: { slug, color, name }.',
									'items'       => [
										'type'       => 'object',
										'properties' => [
											'slug'  => [ 'type' => 'string', 'description' => 'Machine-readable color identifier.' ],
											'color' => [ 'type' => 'string', 'description' => 'Hex color value (e.g. "#6366f1").' ],
											'name'  => [ 'type' => 'string', 'description' => 'Human-readable color name.' ],
										],
									],
								],
								'background' => [
									'type'        => 'string',
									'description' => 'Site background color (hex, e.g. "#ffffff").',
								],
								'text'       => [
									'type'        => 'string',
									'description' => 'Default text color (hex, e.g. "#111111").',
								],
							],
						],
						'typography' => [
							'type'        => 'object',
							'description' => 'Site-wide typography defaults.',
							'properties'  => [
								'fontFamily' => [
									'type'        => 'string',
									'description' => 'Default body font family (e.g. "Inter, sans-serif").',
								],
								'fontSize'   => [
									'type'        => 'string',
									'description' => 'Default body font size (e.g. "16px" or "1rem").',
								],
								'lineHeight' => [
									'type'        => 'string',
									'description' => 'Default line height (e.g. "1.6").',
								],
							],
						],
						'spacing'    => [
							'type'        => 'object',
							'description' => 'Site-wide spacing defaults.',
							'properties'  => [
								'blockGap' => [
									'type'        => 'string',
									'description' => 'Gap between blocks (e.g. "24px").',
								],
								'padding'  => [
									'type'        => 'object',
									'description' => 'Site-wide padding.',
									'properties'  => [
										'top'    => [ 'type' => 'string', 'description' => 'Top padding (e.g. "0px").' ],
										'right'  => [ 'type' => 'string', 'description' => 'Right padding (e.g. "var(--wp--preset--spacing--30)").' ],
										'bottom' => [ 'type' => 'string', 'description' => 'Bottom padding (e.g. "0px").' ],
										'left'   => [ 'type' => 'string', 'description' => 'Left padding (e.g. "var(--wp--preset--spacing--30)").' ],
									],
								],
							],
						],
						'elements'   => [
							'type'        => 'object',
							'description' => 'Style overrides for specific HTML elements: link, button, heading, h1–h6.',
						],
						'blocks'     => [
							'type'        => 'object',
							'description' => 'Per-block style overrides keyed by block name (e.g. "core/group", "core/button").',
						],
					],
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
		$operation = sanitize_key( $params['operation'] ?? '' );

		if ( ! in_array( $operation, [ 'get', 'update' ], true ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Invalid operation. Must be "get" or "update".', 'wp-agent' ),
			];
		}

		if ( 'get' === $operation ) {
			return $this->handle_get();
		}

		return $this->handle_update( $params );
	}

	// -------------------------------------------------------------------------
	// Private: Operation handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle the get operation.
	 *
	 * @since 1.0.0
	 * @return array Execution result.
	 */
	private function handle_get(): array {
		$is_block_theme = wp_is_block_theme();

		$styles   = wp_get_global_styles();
		$settings = wp_get_global_settings();

		$message = $is_block_theme
			? __( 'Global styles retrieved successfully.', 'wp-agent' )
			: __( 'Global styles retrieved. Note: this is not a block theme — some styles may have limited effect.', 'wp-agent' );

		return [
			'success' => true,
			'data'    => [
				'is_block_theme' => $is_block_theme,
				'styles'         => $styles,
				'settings'       => $settings,
			],
			'message' => $message,
		];
	}

	/**
	 * Handle the update operation.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Validated parameters.
	 * @return array Execution result.
	 */
	private function handle_update( array $params ): array {
		if ( empty( $params['styles'] ) || ! is_array( $params['styles'] ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'The "styles" parameter is required and must be an object for the update operation.', 'wp-agent' ),
			];
		}

		$incoming = $params['styles'];

		// Warn (but do not block) if not a block theme.
		$is_block_theme = wp_is_block_theme();

		// Find the existing user global styles post.
		$stylesheet = get_stylesheet();
		$query      = new \WP_Query(
			[
				'post_type'      => 'wp_global_styles',
				'post_status'    => [ 'publish', 'draft' ],
				'name'           => 'wp-global-styles-' . urlencode( $stylesheet ),
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			]
		);

		wp_reset_postdata();

		// Decode existing JSON or start from a minimal scaffold.
		if ( $query->have_posts() ) {
			$existing_post   = $query->posts[0];
			$existing_json   = json_decode( $existing_post->post_content, true );
			$existing_json   = is_array( $existing_json ) ? $existing_json : [];
			$existing_post_id = (int) $existing_post->ID;
		} else {
			$existing_json    = [
				'version'                     => 3,
				'isGlobalStylesUserThemeJSON'  => true,
			];
			$existing_post_id = 0;
		}

		// Ensure the scaffold keys exist.
		if ( ! isset( $existing_json['styles'] ) ) {
			$existing_json['styles'] = [];
		}
		if ( ! isset( $existing_json['settings'] ) ) {
			$existing_json['settings'] = [];
		}

		// Separate palette from the rest — palette lives under settings, not styles.
		$palette = null;
		if ( isset( $incoming['color']['palette'] ) ) {
			$palette = $incoming['color']['palette'];
			unset( $incoming['color']['palette'] );
			// Clean up empty color key if nothing else was set.
			if ( empty( $incoming['color'] ) ) {
				unset( $incoming['color'] );
			}
		}

		// Sanitize incoming styles before merging.
		$sanitized = $this->sanitize_styles( $incoming );

		// Deep-merge sanitized styles into existing styles.
		$existing_json['styles'] = $this->deep_merge( $existing_json['styles'], $sanitized );

		// Merge custom palette into settings.color.palette.custom.
		if ( ! empty( $palette ) && is_array( $palette ) ) {
			$safe_palette = $this->sanitize_palette( $palette );
			if ( ! empty( $safe_palette ) ) {
				if ( ! isset( $existing_json['settings']['color'] ) ) {
					$existing_json['settings']['color'] = [];
				}
				if ( ! isset( $existing_json['settings']['color']['palette'] ) ) {
					$existing_json['settings']['color']['palette'] = [];
				}
				if ( ! isset( $existing_json['settings']['color']['palette']['custom'] ) ) {
					$existing_json['settings']['color']['palette']['custom'] = [];
				}
				// Replace existing custom palette entirely so callers set a clean palette.
				$existing_json['settings']['color']['palette']['custom'] = $safe_palette;
			}
		}

		$new_content = wp_json_encode( $existing_json );

		if ( $existing_post_id > 0 ) {
			$result = wp_update_post(
				[
					'ID'           => $existing_post_id,
					'post_content' => $new_content,
				],
				true
			);
		} else {
			$result = wp_insert_post(
				[
					'post_type'    => 'wp_global_styles',
					'post_title'   => 'Custom Styles',
					'post_name'    => 'wp-global-styles-' . urlencode( $stylesheet ),
					'post_status'  => 'publish',
					'post_content' => $new_content,
				],
				true
			);
		}

		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => $result->get_error_message(),
			];
		}

		// Bust the global styles cache so changes take effect immediately.
		if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
			wp_clean_theme_json_cache();
		}

		$message = $is_block_theme
			? __( 'Global styles updated successfully.', 'wp-agent' )
			: __( 'Global styles updated. Note: this is not a block theme — some styles may have limited effect.', 'wp-agent' );

		return [
			'success' => true,
			'data'    => [
				'post_id'        => (int) $result,
				'is_block_theme' => $is_block_theme,
				'styles'         => $existing_json['styles'],
				'settings'       => $existing_json['settings'],
			],
			'message' => $message,
		];
	}

	// -------------------------------------------------------------------------
	// Private: Sanitization helpers
	// -------------------------------------------------------------------------

	/**
	 * Recursively sanitize a styles array.
	 *
	 * Walks the tree and sanitizes leaf values based on key context:
	 * - Keys named "color", "background", "text", "fill", "stroke" get hex sanitization.
	 * - All other string leaf values get sanitize_text_field().
	 *
	 * @since 1.0.0
	 *
	 * @param array  $styles    Raw styles array.
	 * @param string $parent_key Parent key context for type inference.
	 * @return array Sanitized styles array.
	 */
	private function sanitize_styles( array $styles, string $parent_key = '' ): array {
		$color_keys = [ 'color', 'background', 'text', 'fill', 'stroke', 'gradient' ];
		$sanitized  = [];

		foreach ( $styles as $key => $value ) {
			$safe_key = sanitize_key( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $safe_key ] = $this->sanitize_styles( $value, $safe_key );
			} elseif ( is_string( $value ) ) {
				// Attempt hex sanitization for color-context keys; fall back to text field.
				if ( in_array( $parent_key, $color_keys, true ) || in_array( $safe_key, $color_keys, true ) ) {
					$hex = sanitize_hex_color( $value );
					$sanitized[ $safe_key ] = $hex ? $hex : sanitize_text_field( $value );
				} else {
					$sanitized[ $safe_key ] = sanitize_text_field( $value );
				}
			} elseif ( is_int( $value ) || is_float( $value ) ) {
				$sanitized[ $safe_key ] = $value;
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $safe_key ] = $value;
			}
			// Drop any other types (objects, nulls from untrusted input).
		}

		return $sanitized;
	}

	/**
	 * Sanitize a custom color palette array.
	 *
	 * Each entry must have slug, color, and name.
	 *
	 * @since 1.0.0
	 *
	 * @param array $palette Raw palette entries.
	 * @return array Sanitized palette entries.
	 */
	private function sanitize_palette( array $palette ): array {
		$safe = [];

		foreach ( $palette as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$slug  = isset( $entry['slug'] ) ? sanitize_key( $entry['slug'] ) : '';
			$name  = isset( $entry['name'] ) ? sanitize_text_field( $entry['name'] ) : '';
			$color = '';

			if ( isset( $entry['color'] ) ) {
				$hex   = sanitize_hex_color( $entry['color'] );
				$color = $hex ? $hex : sanitize_text_field( $entry['color'] );
			}

			if ( ! $slug || ! $color ) {
				continue;
			}

			$safe[] = [
				'slug'  => $slug,
				'color' => $color,
				'name'  => $name,
			];
		}

		return $safe;
	}

	// -------------------------------------------------------------------------
	// Private: Deep merge helper
	// -------------------------------------------------------------------------

	/**
	 * Recursively merge $incoming into $base.
	 *
	 * Unlike array_merge, nested arrays are merged rather than replaced,
	 * so a partial update (e.g. only typography) won't wipe existing color settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array $base     The existing data to merge into.
	 * @param array $incoming The new data to layer on top.
	 * @return array Merged result.
	 */
	private function deep_merge( array $base, array $incoming ): array {
		foreach ( $incoming as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
				$base[ $key ] = $this->deep_merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}

		return $base;
	}
}
