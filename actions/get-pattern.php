<?php
/**
 * Get Pattern Action.
 *
 * Returns the full block JSON for a specific pattern with variable
 * overrides and theme token resolution. The returned blocks are
 * ready to be passed directly to insert_blocks.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Get_Pattern
 *
 * @since 1.0.0
 */
class Get_Pattern implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'get_pattern';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Get the full block JSON for a curated section pattern. '
			. 'Returns blocks ready for insert_blocks with theme colors resolved. '
			. 'Optionally override default variable values (headings, text, colors, image URLs) '
			. 'to customize the pattern for the user\'s needs. '
			. 'Workflow: list_patterns → get_pattern → insert_blocks.';
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
				'pattern_id' => [
					'type'        => 'string',
					'description' => 'The pattern ID to retrieve (e.g. "hero-dark", "features-3col", "cta-gradient").',
				],
				'variables'  => [
					'type'        => 'object',
					'description' => 'Optional variable overrides. Keys are variable names (e.g. "heading", "cta_primary", "bg_color"), values are the custom text or colors to use.',
				],
			],
			'required'   => [ 'pattern_id' ],
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
		$pattern_id = sanitize_key( $params['pattern_id'] ?? '' );
		if ( empty( $pattern_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Pattern ID is required.', 'wp-agent' ),
			];
		}

		$overrides = [];
		if ( ! empty( $params['variables'] ) && is_array( $params['variables'] ) ) {
			foreach ( $params['variables'] as $key => $value ) {
				$overrides[ sanitize_key( $key ) ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		}

		$manager = \WPAgent\Patterns\Pattern_Manager::get_instance();
		$pattern = $manager->get_pattern( $pattern_id, $overrides );

		if ( ! $pattern ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: pattern ID */
					__( 'Pattern "%s" not found. Call list_patterns to see available patterns.', 'wp-agent' ),
					$pattern_id
				),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'id'          => $pattern['id'],
				'name'        => $pattern['name'],
				'category'    => $pattern['category'],
				'description' => $pattern['description'],
				'blocks'      => $pattern['blocks'],
			],
			'message' => sprintf(
				/* translators: 1: pattern name, 2: block count */
				__( 'Loaded pattern "%1$s" with %2$d block(s). Pass these blocks to insert_blocks.', 'wp-agent' ),
				$pattern['name'],
				count( $pattern['blocks'] )
			),
		];
	}
}
