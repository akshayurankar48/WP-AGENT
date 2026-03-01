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
		return 'Insert one or more Gutenberg blocks into the currently open post in the block editor. '
			. 'Use this tool (not edit_post) when the user is editing a post and wants to add or build content. '
			. 'Blocks can be nested via the innerBlocks field to create complex layouts — for example, '
			. 'a core/group containing a core/heading, core/paragraph, and core/buttons. '
			. 'blockName must be a registered block type (e.g. "core/paragraph", "core/heading", "core/group", "core/columns"). '
			. 'attrs are the block\'s registered attributes for styling and configuration — use the style object for custom colors, '
			. 'typography, spacing, and borders (e.g. {"style":{"color":{"background":"#0a0a0a"}}}). '
			. 'innerHTML is the text/HTML content for leaf blocks (headings, paragraphs, buttons). '
			. 'Use position "replace" to clear the editor and build a fresh page layout. '
			. 'The user must be in the Gutenberg editor for this to work.';
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
				__( 'Ready to %2$s %1$d block(s) in post #%3$d (client-side).', 'wp-agent' ),
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
}
