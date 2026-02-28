<?php
/**
 * Insert Blocks Action.
 *
 * Returns instructions for the React client to insert blocks
 * into the Gutenberg editor. NOT executed server-side — the
 * actual block manipulation happens via @wordpress/data dispatch.
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
		return 'Insert Gutenberg blocks into the current post in the editor. Blocks are inserted client-side via the block editor API.';
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
					'description' => 'The ID of the post to insert blocks into.',
				],
				'blocks'   => [
					'type'        => 'array',
					'description' => 'Array of blocks to insert.',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'blockName' => [
								'type'        => 'string',
								'description' => 'Block type name (e.g. "core/paragraph", "core/heading").',
							],
							'attrs'     => [
								'type'        => 'object',
								'description' => 'Block attributes (e.g. {"level": 2} for a heading).',
							],
							'innerHTML' => [
								'type'        => 'string',
								'description' => 'The inner HTML content of the block.',
							],
						],
						'required'   => [ 'blockName' ],
					],
				],
				'position' => [
					'type'        => 'string',
					'description' => 'Where to insert blocks relative to existing content.',
					'enum'        => [ 'append', 'prepend' ],
					'default'     => 'append',
				],
			],
			'required'   => [ 'post_id', 'blocks' ],
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

		if ( ! in_array( $position, [ 'append', 'prepend' ], true ) ) {
			$position = 'append';
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
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

		if ( empty( $blocks ) || ! is_array( $blocks ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'No blocks provided to insert.', 'wp-agent' ),
			];
		}

		// Sanitize block data before returning to client.
		$sanitized_blocks = [];
		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$sanitized_blocks[] = [
				'blockName' => sanitize_text_field( $block['blockName'] ),
				'attrs'     => ! empty( $block['attrs'] ) && is_array( $block['attrs'] )
					? $this->sanitize_attrs( $block['attrs'] )
					: new \stdClass(),
				'innerHTML' => isset( $block['innerHTML'] ) ? wp_kses_post( $block['innerHTML'] ) : '',
			];
		}

		if ( empty( $sanitized_blocks ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'No valid blocks provided to insert.', 'wp-agent' ),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'post_id'   => $post_id,
				'blocks'    => $sanitized_blocks,
				'position'  => $position,
				'execution' => 'client',
			],
			'message' => sprintf(
				/* translators: 1: block count, 2: position, 3: post ID */
				__( 'Ready to %2$s %1$d block(s) in post #%3$d (client-side).', 'wp-agent' ),
				count( $sanitized_blocks ),
				$position,
				$post_id
			),
		];
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

		foreach ( $attrs as $key => $value ) {
			$key = sanitize_text_field( $key );

			if ( is_string( $value ) ) {
				$clean[ $key ] = sanitize_text_field( $value );
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
