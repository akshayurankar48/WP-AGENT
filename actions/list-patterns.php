<?php
/**
 * List Patterns Action.
 *
 * Returns the curated pattern catalog for the AI to browse.
 * Returns metadata only (no block JSON) so the AI can pick
 * patterns before fetching their full content via get_pattern.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class List_Patterns
 *
 * @since 1.0.0
 */
class List_Patterns implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'list_patterns';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'List available curated section patterns for building pages. '
			. 'Returns pattern metadata (id, name, category, description) — no block JSON. '
			. 'Use this to browse the pattern catalog, then call get_pattern to fetch the full blocks for a specific pattern. '
			. 'Categories: heroes, features, testimonials, pricing, cta, stats, content, faq, footers, headers.';
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
				'category' => [
					'type'        => 'string',
					'enum'        => [ 'heroes', 'features', 'testimonials', 'pricing', 'cta', 'stats', 'content', 'faq', 'footers', 'headers' ],
					'description' => 'Filter patterns by category. Omit to list all patterns.',
				],
				'search'   => [
					'type'        => 'string',
					'description' => 'Optional keyword to search pattern names and descriptions.',
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
		$category = ! empty( $params['category'] ) ? sanitize_key( $params['category'] ) : '';
		$search   = ! empty( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';

		$manager  = \WPAgent\Patterns\Pattern_Manager::get_instance();
		$patterns = $manager->list_patterns( $category, $search );

		// Also include blueprints metadata.
		$blueprints = [];
		$blueprint_ids = [ 'landing-page', 'saas-landing', 'startup-page', 'about-page' ];
		foreach ( $blueprint_ids as $bp_id ) {
			$bp = $manager->get_blueprint( $bp_id );
			if ( $bp ) {
				$blueprints[] = $bp;
			}
		}

		if ( empty( $patterns ) ) {
			$message = $category
				? sprintf( __( 'No patterns found in the "%s" category.', 'wp-agent' ), $category )
				: __( 'No patterns found.', 'wp-agent' );

			return [
				'success' => true,
				'data'    => [
					'total'      => 0,
					'patterns'   => [],
					'blueprints' => $blueprints,
				],
				'message' => $message,
			];
		}

		$message = sprintf(
			/* translators: %d: result count */
			__( 'Found %d pattern(s).', 'wp-agent' ),
			count( $patterns )
		);

		if ( $category ) {
			$message .= sprintf(
				/* translators: %s: category name */
				__( ' Category: %s.', 'wp-agent' ),
				$category
			);
		}

		return [
			'success' => true,
			'data'    => [
				'total'      => count( $patterns ),
				'patterns'   => $patterns,
				'blueprints' => $blueprints,
			],
			'message' => $message,
		];
	}
}
