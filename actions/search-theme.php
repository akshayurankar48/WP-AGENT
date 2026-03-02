<?php
/**
 * Search Theme Action.
 *
 * Searches the WordPress.org theme repository and returns theme
 * recommendations with ratings, install counts, and compatibility
 * information. Mirrors recommend_plugin for themes.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Search_Theme
 *
 * @since 1.0.0
 */
class Search_Theme implements Action_Interface {

	/**
	 * Maximum results to return.
	 *
	 * @var int
	 */
	const MAX_RESULTS = 10;

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'search_theme';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Search the WordPress.org theme repository by keyword. Returns theme name, slug, '
			. 'rating, active installs, preview URL, and description. Use this to find the correct '
			. 'slug before installing with install_theme, or to recommend themes to the user.';
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
				'query'    => [
					'type'        => 'string',
					'description' => 'Search keyword (e.g. "elementor", "minimal blog", "ecommerce").',
				],
				'tag'      => [
					'type'        => 'string',
					'description' => 'Optional theme feature tag to filter by (e.g. "full-site-editing", '
						. '"blog", "e-commerce", "one-column", "custom-colors").',
				],
				'per_page' => [
					'type'        => 'integer',
					'description' => 'Number of results (max 10). Defaults to 5.',
				],
			],
			'required'   => [ 'query' ],
		];
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'install_themes';
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
		$query = ! empty( $params['query'] ) ? sanitize_text_field( $params['query'] ) : '';

		if ( empty( $query ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Search query is required.', 'wp-agent' ),
			];
		}

		require_once ABSPATH . 'wp-admin/includes/theme-install.php';

		$per_page = isset( $params['per_page'] ) ? min( absint( $params['per_page'] ), self::MAX_RESULTS ) : 5;

		$args = [
			'search'   => $query,
			'per_page' => $per_page,
			'page'     => 1,
			'fields'   => [
				'description'   => true,
				'sections'      => false,
				'versions'      => false,
				'rating'        => true,
				'num_ratings'   => true,
				'active_installs' => true,
				'screenshot_url'  => true,
				'preview_url'     => true,
			],
		];

		if ( ! empty( $params['tag'] ) ) {
			$args['tag'] = sanitize_key( $params['tag'] );
		}

		$api_result = themes_api( 'query_themes', $args );

		if ( is_wp_error( $api_result ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Theme search failed: %s', 'wp-agent' ),
					$api_result->get_error_message()
				),
			];
		}

		$themes    = $api_result->themes ?? [];
		$installed = wp_get_themes();
		$results   = [];

		foreach ( $themes as $theme ) {
			// Results may be arrays or objects depending on WP version.
			$t            = (array) $theme;
			$slug         = $t['slug'] ?? '';
			$is_installed = isset( $installed[ $slug ] );

			$results[] = [
				'name'            => sanitize_text_field( $t['name'] ?? '' ),
				'slug'            => sanitize_key( $slug ),
				'rating'          => round( ( $t['rating'] ?? 0 ) / 20, 1 ),
				'num_ratings'     => (int) ( $t['num_ratings'] ?? 0 ),
				'active_installs' => (int) ( $t['active_installs'] ?? 0 ),
				'description'     => wp_trim_words( sanitize_text_field( $t['description'] ?? '' ), 30 ),
				'preview_url'     => esc_url( $t['preview_url'] ?? '' ),
				'screenshot_url'  => esc_url( $t['screenshot_url'] ?? '' ),
				'is_installed'    => $is_installed,
			];
		}

		return [
			'success' => true,
			'data'    => [
				'query'  => $query,
				'themes' => $results,
				'total'  => count( $results ),
			],
			'message' => sprintf(
				/* translators: 1: theme count, 2: search query */
				__( 'Found %1$d theme(s) for "%2$s". Use install_theme with the slug to install.', 'wp-agent' ),
				count( $results ),
				$query
			),
		];
	}
}
