<?php
/**
 * Insert Blocks Action.
 *
 * Returns instructions for the React client to insert blocks
 * into the Gutenberg editor. NOT executed server-side — the
 * actual block manipulation happens via @wordpress/data dispatch.
 *
 * Supports nested block structures (groups, columns, covers)
 * for building complex landing page layouts.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Insert_Blocks
 *
 * @since 1.0.0
 */
class Insert_Blocks implements Action_Interface {

	/**
	 * Maximum nesting depth for inner blocks.
	 *
	 * @var int
	 */
	const MAX_NESTING_DEPTH = 6;

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'insert_blocks';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Insert one or more Gutenberg blocks into a post. Works from both the Gutenberg editor AND the admin dashboard. '
			. 'Use this tool (not edit_post) when the user wants to add or build content. '
			. 'Blocks can be nested via the innerBlocks field to create complex layouts — for example, '
			. 'a core/group containing a core/heading, core/paragraph, and core/buttons. '
			. 'blockName must be a registered block type (e.g. "core/paragraph", "core/heading", "core/group", "core/columns"). '
			. 'attrs are the block\'s registered attributes for styling and configuration — use the style object for custom colors, '
			. 'typography, spacing, and borders (e.g. {"style":{"color":{"background":"#0a0a0a"}}}). '
			. 'innerHTML is the text/HTML content for leaf blocks (headings, paragraphs, buttons). '
			. 'Use position "replace" to clear the editor and build a fresh page layout. '
			. 'Content is saved server-side via wp_update_post AND returned for live editor updates.';
	}

	/**
	 * Get the JSON Schema for parameters.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_parameters(): array {
		$block_schema = $this->get_block_schema();

		return [
			'type'       => 'object',
			'properties' => [
				'post_id'  => [
					'type'        => 'integer',
					'description' => 'The ID of the post to insert blocks into.',
				],
				'blocks'   => [
					'type'        => 'array',
					'description' => 'Array of blocks to insert. Each block can contain innerBlocks for nesting.',
					'items'       => $block_schema,
				],
				'position' => [
					'type'        => 'string',
					'description' => 'Where to insert blocks. Use "append" to add after existing content, "prepend" to add before, or "replace" to clear the editor and start fresh (ideal for building full pages).',
					'enum'        => [ 'append', 'prepend', 'replace' ],
					'default'     => 'append',
				],
			],
			'required'   => [ 'post_id', 'blocks' ],
		];
	}

	/**
	 * Get the JSON Schema for a single block with inner blocks.
	 *
	 * Defines two levels of explicit nesting. The AI understands
	 * from the description that deeper nesting follows the same pattern.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_block_schema(): array {
		$inner_block = [
			'type'       => 'object',
			'properties' => [
				'blockName'   => [
					'type'        => 'string',
					'description' => 'Block type name (e.g. "core/paragraph", "core/heading", "core/button").',
				],
				'attrs'       => [
					'type'        => 'object',
					'description' => 'Block attributes for styling and configuration. Use style.color, style.typography, style.spacing, style.border for custom design.',
				],
				'innerHTML'   => [
					'type'        => 'string',
					'description' => 'Text/HTML content for the block (e.g. heading text, paragraph text, button label).',
				],
				'innerBlocks' => [
					'type'        => 'array',
					'description' => 'Nested child blocks (same structure). Used for grouping content inside layout blocks.',
					'items'       => [
						'type' => 'object',
					],
				],
			],
			'required'   => [ 'blockName' ],
		];

		return [
			'type'       => 'object',
			'properties' => [
				'blockName'   => [
					'type'        => 'string',
					'description' => 'Block type name. Layout blocks: "core/group", "core/columns", "core/column", "core/cover", "core/buttons". '
						. 'Content blocks: "core/heading", "core/paragraph", "core/image", "core/button", "core/list", "core/quote". '
						. 'Utility blocks: "core/spacer", "core/separator".',
				],
				'attrs'       => [
					'type'        => 'object',
					'description' => 'Block attributes. Key patterns: '
						. '{"align":"full"} for full-width, '
						. '{"style":{"color":{"background":"#hex","text":"#hex"}}} for colors, '
						. '{"style":{"typography":{"fontSize":"20px","fontWeight":"700"}}} for text, '
						. '{"style":{"spacing":{"padding":{"top":"80px","bottom":"80px","left":"40px","right":"40px"}}}} for spacing, '
						. '{"style":{"border":{"radius":"8px"}}} for borders, '
						. '{"layout":{"type":"constrained"}} or {"layout":{"type":"flex","justifyContent":"center"}} for layout.',
				],
				'innerHTML'   => [
					'type'        => 'string',
					'description' => 'Text/HTML content for leaf blocks. For core/heading: the heading text. For core/paragraph: the paragraph text (may include <strong>, <em>). For core/button: the button label.',
				],
				'innerBlocks' => [
					'type'        => 'array',
					'description' => 'Nested child blocks. core/group, core/columns, core/cover, and core/buttons MUST use innerBlocks for their children instead of innerHTML.',
					'items'       => $inner_block,
				],
			],
			'required'   => [ 'blockName' ],
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
		return false;
	}

	/**
	 * Execute the action.
	 *
	 * Returns client-side instructions rather than modifying the post
	 * directly. The React client reads the response and uses
	 * wp.data.dispatch('core/block-editor') to insert the blocks.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Validated parameters.
	 * @return array Execution result with client-side instructions.
	 */
	public function execute( array $params ): array {
		$post_id  = absint( $params['post_id'] );
		$blocks   = $params['blocks'];
		$position = ! empty( $params['position'] ) ? sanitize_text_field( $params['position'] ) : 'append';

		if ( ! in_array( $position, [ 'append', 'prepend', 'replace' ], true ) ) {
			$position = 'append';
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %d: post ID */
					__( 'Post #%d not found. Verify the post_id is correct.', 'wp-agent' ),
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

		if ( empty( $blocks ) || ! is_array( $blocks ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'No blocks provided. Provide at least one block with a blockName.', 'wp-agent' ),
			];
		}

		// Recursively sanitize the block tree.
		$sanitized_blocks = [];
		foreach ( $blocks as $block ) {
			$sanitized = $this->sanitize_block( $block, 0 );
			if ( $sanitized ) {
				$sanitized_blocks[] = $sanitized;
			}
		}

		if ( empty( $sanitized_blocks ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'No valid blocks after sanitization. Ensure each block has a blockName.', 'wp-agent' ),
			];
		}

		$block_count = $this->count_blocks( $sanitized_blocks );

		// Server-side persistence: serialize blocks to WordPress markup and save.
		// This ensures content is saved even when called from the admin drawer
		// (which has no useBlockActions hook for client-side insertion).
		$serialized  = $this->serialize_blocks( $sanitized_blocks );
		$save_result = $this->save_to_post( $post_id, $serialized, $position );

		if ( is_wp_error( $save_result ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => $save_result->get_error_message(),
			];
		}

		// Also return client-side data — the editor's useBlockActions hook uses
		// this for live block insertion without requiring a page reload.
		return [
			'success' => true,
			'data'    => [
				'post_id'   => $post_id,
				'blocks'    => $sanitized_blocks,
				'position'  => $position,
				'execution' => 'client',
			],
			'message' => sprintf(
				/* translators: 1: total block count, 2: position, 3: post ID */
				__( 'Inserted %1$d block(s) into post #%3$d (%2$s). Content saved.', 'wp-agent' ),
				$block_count,
				$position,
				$post_id
			),
		];
	}

	/**
	 * Recursively sanitize a block and its inner blocks.
	 *
	 * @since 1.0.0
	 *
	 * @param array $block Raw block data from the AI.
	 * @param int   $depth Current nesting depth.
	 * @return array|null Sanitized block or null if invalid.
	 */
	private function sanitize_block( array $block, int $depth ): ?array {
		if ( empty( $block['blockName'] ) ) {
			return null;
		}

		if ( $depth > self::MAX_NESTING_DEPTH ) {
			return null;
		}

		$sanitized = [
			'blockName'   => sanitize_text_field( $block['blockName'] ),
			'attrs'       => ! empty( $block['attrs'] ) && is_array( $block['attrs'] )
				? $this->sanitize_attrs( $block['attrs'] )
				: new \stdClass(),
			'innerHTML'   => isset( $block['innerHTML'] ) ? wp_kses_post( $block['innerHTML'] ) : '',
			'innerBlocks' => [],
		];

		// Recursively sanitize inner blocks.
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				if ( ! is_array( $inner_block ) ) {
					continue;
				}
				$inner = $this->sanitize_block( $inner_block, $depth + 1 );
				if ( $inner ) {
					$sanitized['innerBlocks'][] = $inner;
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Count total blocks including inner blocks.
	 *
	 * @since 1.0.0
	 *
	 * @param array $blocks Array of sanitized blocks.
	 * @return int Total block count.
	 */
	private function count_blocks( array $blocks ): int {
		$count = count( $blocks );
		foreach ( $blocks as $block ) {
			if ( ! empty( $block['innerBlocks'] ) ) {
				$count += $this->count_blocks( $block['innerBlocks'] );
			}
		}
		return $count;
	}

	/**
	 * Recursively sanitize block attributes.
	 *
	 * Strings are sanitized via sanitize_text_field(), arrays are
	 * recursed, booleans and numbers pass through unchanged.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attrs Raw block attributes.
	 * @return array Sanitized attributes.
	 */
	private function sanitize_attrs( array $attrs ): array {
		$clean = [];

		// Keys whose values are URLs — sanitize with esc_url_raw instead of sanitize_text_field.
		$url_keys = [ 'url', 'href', 'src', 'mediaLink' ];

		foreach ( $attrs as $key => $value ) {
			$key = sanitize_text_field( $key );

			if ( is_string( $value ) ) {
				$clean[ $key ] = in_array( $key, $url_keys, true )
					? esc_url_raw( $value )
					: sanitize_text_field( $value );
			} elseif ( is_array( $value ) ) {
				$clean[ $key ] = $this->sanitize_attrs( $value );
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$clean[ $key ] = $value;
			}
			// Silently drop any other types (objects, resources, etc.).
		}

		return $clean;
	}

	/**
	 * Serialize an array of blocks to WordPress block markup.
	 *
	 * @since 1.1.0
	 *
	 * @param array $blocks Array of sanitized blocks.
	 * @return string WordPress block markup.
	 */
	private function serialize_blocks( array $blocks ): string {
		$output = '';
		foreach ( $blocks as $block ) {
			$output .= $this->serialize_single_block( $block );
		}
		return $output;
	}

	/**
	 * Serialize a single block and its inner blocks to WordPress block markup.
	 *
	 * Produces the standard WordPress block comment format:
	 * <!-- wp:blockname {"attrs"} -->
	 * <html>inner content</html>
	 * <!-- /wp:blockname -->
	 *
	 * @since 1.1.0
	 *
	 * @param array $block A single sanitized block.
	 * @return string Block markup.
	 */
	private function serialize_single_block( array $block ): string {
		$block_name = $block['blockName'];
		$attrs      = (array) $block['attrs'];

		// Strip core/ prefix for block comment (WordPress convention).
		$comment_name = 0 === strpos( $block_name, 'core/' )
			? substr( $block_name, 5 )
			: $block_name;

		// Build attrs JSON for the comment. Omit empty attrs.
		$filtered_attrs = $this->filter_comment_attrs( $attrs );
		$attrs_json     = ! empty( $filtered_attrs )
			? ' ' . wp_json_encode( $filtered_attrs, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES )
			: '';

		// Recursively serialize inner blocks.
		$inner_content = '';
		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				$inner_content .= $this->serialize_single_block( $inner_block );
			}
		}

		// Build HTML representation for this block.
		$html = $this->build_block_html( $block, $inner_content );

		return "<!-- wp:{$comment_name}{$attrs_json} -->\n{$html}\n<!-- /wp:{$comment_name} -->\n\n";
	}

	/**
	 * Filter attributes for the block comment JSON.
	 *
	 * Removes empty arrays and null values but preserves false, 0, and '0'.
	 *
	 * @since 1.1.0
	 *
	 * @param array $attrs Raw attributes.
	 * @return array Filtered attributes.
	 */
	private function filter_comment_attrs( array $attrs ): array {
		return array_filter( $attrs, function ( $v ) {
			if ( is_array( $v ) ) {
				return ! empty( $v );
			}
			return '' !== $v && null !== $v;
		} );
	}

	/**
	 * Build the HTML content for a specific block type.
	 *
	 * Maps each block type to its WordPress HTML representation.
	 * Handles classes, inline styles, and inner content.
	 *
	 * @since 1.1.0
	 *
	 * @param array  $block         Sanitized block data.
	 * @param string $inner_content Serialized inner blocks HTML.
	 * @return string HTML for the block.
	 */
	private function build_block_html( array $block, string $inner_content = '' ): string {
		$name       = $block['blockName'];
		$attrs      = (array) $block['attrs'];
		$inner_html = isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';

		$classes = $this->build_class_string( $name, $attrs );
		$styles  = $this->build_style_string( $attrs );

		$class_attr = ! empty( $classes ) ? ' class="' . esc_attr( $classes ) . '"' : '';
		$style_attr = ! empty( $styles ) ? ' style="' . esc_attr( $styles ) . '"' : '';

		switch ( $name ) {
			case 'core/paragraph':
				return "<p{$class_attr}{$style_attr}>{$inner_html}</p>";

			case 'core/heading':
				$level = isset( $attrs['level'] ) ? absint( $attrs['level'] ) : 2;
				$level = max( 1, min( 6, $level ) );
				return "<h{$level}{$class_attr}{$style_attr}>{$inner_html}</h{$level}>";

			case 'core/group':
			case 'core/columns':
			case 'core/buttons':
				return "<div{$class_attr}{$style_attr}>{$inner_content}</div>";

			case 'core/column':
				$width = isset( $attrs['width'] ) ? $attrs['width'] : '';
				if ( $width ) {
					$col_style = "flex-basis:{$width}";
					if ( $styles ) {
						$col_style .= ";{$styles}";
					}
					$style_attr = ' style="' . esc_attr( $col_style ) . '"';
				}
				return "<div{$class_attr}{$style_attr}>{$inner_content}</div>";

			case 'core/cover':
				$url     = isset( $attrs['url'] ) ? esc_url( $attrs['url'] ) : '';
				$bg_span = '<span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span>';
				$img     = $url ? '<img class="wp-block-cover__image-background" alt="" src="' . $url . '" data-object-fit="cover"/>' : '';
				$inner   = '<div class="wp-block-cover__inner-container">' . $inner_content . '</div>';
				return "<div{$class_attr}{$style_attr}>{$bg_span}{$img}{$inner}</div>";

			case 'core/button':
				// Button has a wrapper div and an inner <a> tag.
				$link_classes = [ 'wp-block-button__link', 'wp-element-button' ];
				if ( $this->get_nested_attr( $attrs, 'style', 'color', 'background' ) || ! empty( $attrs['backgroundColor'] ) ) {
					$link_classes[] = 'has-background';
				}
				if ( $this->get_nested_attr( $attrs, 'style', 'color', 'text' ) || ! empty( $attrs['textColor'] ) ) {
					$link_classes[] = 'has-text-color';
				}
				$link_styles    = $this->build_style_string( $attrs );
				$border_radius  = $this->get_nested_attr( $attrs, 'style', 'border', 'radius' );
				if ( $border_radius ) {
					$link_styles .= ( $link_styles ? ';' : '' ) . "border-radius:{$border_radius}";
				}
				$link_class_str = implode( ' ', $link_classes );
				$link_style_attr = $link_styles ? ' style="' . esc_attr( $link_styles ) . '"' : '';
				$url       = isset( $attrs['url'] ) ? esc_url( $attrs['url'] ) : '';
				$href_attr = $url ? ' href="' . $url . '"' : '';
				$outer_class = 'wp-block-button';
				if ( ! empty( $attrs['className'] ) ) {
					$outer_class .= ' ' . $this->sanitize_class_names( $attrs['className'] );
				}
				return '<div class="' . esc_attr( $outer_class ) . '"><a class="' . esc_attr( $link_class_str ) . '"' . $link_style_attr . $href_attr . '>' . $inner_html . '</a></div>';

			case 'core/image':
				$url       = isset( $attrs['url'] ) ? esc_url( $attrs['url'] ) : '';
				$alt       = isset( $attrs['alt'] ) ? esc_attr( $attrs['alt'] ) : '';
				$size      = isset( $attrs['sizeSlug'] ) ? $attrs['sizeSlug'] : 'large';
				$fig_class = 'wp-block-image' . ( $size ? " size-{$size}" : '' );
				$align     = $this->get_align_class( $attrs );
				if ( $align ) {
					$fig_class .= ' ' . $align;
				}
				$img_tag = $url ? '<img src="' . $url . '" alt="' . $alt . '"/>' : '';
				return '<figure class="' . esc_attr( $fig_class ) . '">' . $img_tag . '</figure>';

			case 'core/spacer':
				$height = isset( $attrs['height'] ) ? $attrs['height'] : '60px';
				return '<div style="height:' . esc_attr( $height ) . '" aria-hidden="true" class="wp-block-spacer"></div>';

			case 'core/separator':
				// Separator uses paired block comments with a self-closing <hr/>.
				$sep_classes = 'wp-block-separator has-alpha-channel-opacity';
				if ( ! empty( $attrs['className'] ) ) {
					$sep_classes .= ' ' . $this->sanitize_class_names( $attrs['className'] );
				}
				return '<hr class="' . esc_attr( $sep_classes ) . '"/>';

			case 'core/list':
				$ordered = ! empty( $attrs['ordered'] );
				$tag     = $ordered ? 'ol' : 'ul';
				$content = ! empty( $inner_html ) ? $inner_html : $inner_content;
				return "<{$tag}{$class_attr}{$style_attr}>{$content}</{$tag}>";

			case 'core/list-item':
				return "<li>{$inner_html}</li>";

			case 'core/quote':
				$content = ! empty( $inner_html ) ? $inner_html : $inner_content;
				return "<blockquote{$class_attr}{$style_attr}>{$content}</blockquote>";

			default:
				// Generic fallback: div wrapper with either inner blocks or innerHTML.
				$content = ! empty( $inner_content ) ? $inner_content : $inner_html;
				return "<div{$class_attr}{$style_attr}>{$content}</div>";
		}
	}

	/**
	 * Build CSS class string for a block.
	 *
	 * Assembles base class, alignment, color indicators, preset colors,
	 * and custom className — all in the order WordPress expects.
	 *
	 * @since 1.1.0
	 *
	 * @param string $block_name Block type name.
	 * @param array  $attrs      Block attributes.
	 * @return string Space-separated class string.
	 */
	private function build_class_string( string $block_name, array $attrs ): string {
		$classes = [];

		// Block-specific base class.
		$base_classes = [
			'core/heading'   => 'wp-block-heading',
			'core/group'     => 'wp-block-group',
			'core/columns'   => 'wp-block-columns',
			'core/column'    => 'wp-block-column',
			'core/cover'     => 'wp-block-cover',
			'core/buttons'   => 'wp-block-buttons',
			'core/image'     => 'wp-block-image',
			'core/spacer'    => 'wp-block-spacer',
			'core/separator' => 'wp-block-separator',
			'core/quote'     => 'wp-block-quote',
		];

		if ( isset( $base_classes[ $block_name ] ) ) {
			$classes[] = $base_classes[ $block_name ];
		}

		// Alignment class.
		$align = $this->get_align_class( $attrs );
		if ( $align ) {
			$classes[] = $align;
		}

		// Color indicator classes.
		if ( $this->get_nested_attr( $attrs, 'style', 'color', 'text' ) ) {
			$classes[] = 'has-text-color';
		}
		if ( $this->get_nested_attr( $attrs, 'style', 'color', 'background' ) ) {
			$classes[] = 'has-background';
		}

		// Preset color classes.
		if ( ! empty( $attrs['textColor'] ) ) {
			$classes[] = 'has-' . sanitize_html_class( $attrs['textColor'] ) . '-color';
		}
		if ( ! empty( $attrs['backgroundColor'] ) ) {
			$classes[] = 'has-' . sanitize_html_class( $attrs['backgroundColor'] ) . '-background-color';
		}

		// Custom CSS classes — split by space to avoid sanitize_html_class corrupting spaces.
		if ( ! empty( $attrs['className'] ) ) {
			$classes[] = $this->sanitize_class_names( $attrs['className'] );
		}

		return implode( ' ', array_filter( $classes ) );
	}

	/**
	 * Build inline style string from block attributes.
	 *
	 * Extracts color, typography, spacing, and border styles from
	 * the nested style attribute structure.
	 *
	 * @since 1.1.0
	 *
	 * @param array $attrs Block attributes.
	 * @return string Semicolon-separated CSS declarations.
	 */
	private function build_style_string( array $attrs ): string {
		$styles = [];

		// Colors.
		$text_color = $this->get_nested_attr( $attrs, 'style', 'color', 'text' );
		$bg_color   = $this->get_nested_attr( $attrs, 'style', 'color', 'background' );
		if ( $text_color ) {
			$styles[] = "color:{$text_color}";
		}
		if ( $bg_color ) {
			$styles[] = "background-color:{$bg_color}";
		}

		// Gradient.
		$gradient = $this->get_nested_attr( $attrs, 'style', 'color', 'gradient' );
		if ( $gradient ) {
			$styles[] = "background:{$gradient}";
		}

		// Typography.
		$typo_map = [
			'fontSize'      => 'font-size',
			'fontWeight'    => 'font-weight',
			'lineHeight'    => 'line-height',
			'letterSpacing' => 'letter-spacing',
			'textTransform' => 'text-transform',
			'fontStyle'     => 'font-style',
		];
		foreach ( $typo_map as $attr_key => $css_prop ) {
			$val = $this->get_nested_attr( $attrs, 'style', 'typography', $attr_key );
			if ( $val ) {
				$styles[] = "{$css_prop}:{$val}";
			}
		}

		// Spacing — padding.
		$padding = $this->get_nested_attr( $attrs, 'style', 'spacing', 'padding' );
		if ( is_array( $padding ) ) {
			foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
				if ( ! empty( $padding[ $side ] ) ) {
					$styles[] = "padding-{$side}:{$padding[ $side ]}";
				}
			}
		}

		// Spacing — margin.
		$margin = $this->get_nested_attr( $attrs, 'style', 'spacing', 'margin' );
		if ( is_array( $margin ) ) {
			foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
				if ( ! empty( $margin[ $side ] ) ) {
					$styles[] = "margin-{$side}:{$margin[ $side ]}";
				}
			}
		}

		// Border.
		$border_map = [
			'radius' => 'border-radius',
			'width'  => 'border-width',
			'color'  => 'border-color',
			'style'  => 'border-style',
		];
		foreach ( $border_map as $attr_key => $css_prop ) {
			$val = $this->get_nested_attr( $attrs, 'style', 'border', $attr_key );
			if ( $val ) {
				$styles[] = "{$css_prop}:{$val}";
			}
		}

		return implode( ';', $styles );
	}

	/**
	 * Get the alignment class from block attributes.
	 *
	 * @since 1.1.0
	 *
	 * @param array $attrs Block attributes.
	 * @return string Alignment class or empty string.
	 */
	private function get_align_class( array $attrs ): string {
		if ( empty( $attrs['align'] ) ) {
			return '';
		}
		$valid = [ 'full', 'wide', 'center', 'left', 'right' ];
		$align = sanitize_text_field( $attrs['align'] );
		return in_array( $align, $valid, true ) ? "align{$align}" : '';
	}

	/**
	 * Get a nested attribute value by key path.
	 *
	 * @since 1.1.0
	 *
	 * @param array  $attrs Block attributes.
	 * @param string ...$keys Key path (e.g. 'style', 'color', 'text').
	 * @return mixed|null The value or null if not found.
	 */
	private function get_nested_attr( array $attrs, ...$keys ) {
		$current = $attrs;
		foreach ( $keys as $key ) {
			if ( ! is_array( $current ) || ! isset( $current[ $key ] ) ) {
				return null;
			}
			$current = $current[ $key ];
		}
		return $current;
	}

	/**
	 * Sanitize space-separated CSS class names.
	 *
	 * Unlike sanitize_html_class(), this handles multiple classes correctly
	 * by splitting on spaces, sanitizing each class individually, and rejoining.
	 *
	 * @since 1.1.0
	 *
	 * @param string $class_string Space-separated class names.
	 * @return string Sanitized space-separated class names.
	 */
	private function sanitize_class_names( string $class_string ): string {
		$classes = explode( ' ', $class_string );
		$clean   = array_map( 'sanitize_html_class', $classes );
		return implode( ' ', array_filter( $clean ) );
	}

	/**
	 * Save serialized block markup to a post.
	 *
	 * Handles append, prepend, and replace position modes.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $content  Serialized block markup.
	 * @param string $position Position mode: append, prepend, or replace.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	private function save_to_post( int $post_id, string $content, string $position ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found for saving.', 'wp-agent' ) );
		}

		$existing = $post->post_content;

		switch ( $position ) {
			case 'replace':
				$new_content = $content;
				break;
			case 'prepend':
				$new_content = $content . $existing;
				break;
			case 'append':
			default:
				$new_content = $existing . $content;
				break;
		}

		$result = wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => $new_content,
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}
}
