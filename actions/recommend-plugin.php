<?php
/**
 * Recommend Plugin Action.
 *
 * Searches the WordPress.org plugin repository and returns plugin
 * recommendations with ratings, install counts, and compatibility
 * information.
 *
 * @package WPAgent\Actions
 * @since   1.1.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Recommend_Plugin
 *
 * @since 1.1.0
 */
class Recommend_Plugin implements Action_Interface {

	/**
	 * Maximum results to return.
	 *
	 * @var int
	 */
	const MAX_RESULTS = 10;

	/**
	 * Get the action name.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function get_name(): string {
		return 'recommend_plugin';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Search and recommend WordPress plugins from the official repository. Operations: "search" finds plugins '
			. 'by keyword, "recommend" suggests best-fit plugins for a use case. Returns name, slug, rating, active installs, '
			. 'last updated date, tested WP version, and description. Use this to find the correct slug before calling '
			. 'install_plugin, or install_plugin can search automatically.';
	}

	/**
	 * Get the JSON Schema for parameters.
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_parameters(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'operation' => [
					'type'        => 'string',
					'enum'        => [ 'search', 'recommend' ],
					'description' => 'Operation to perform.',
				],
				'query'     => [
					'type'        => 'string',
					'description' => 'Search keyword or use case description.',
				],
				'tag'       => [
					'type'        => 'string',
					'description' => 'Optional plugin tag to filter by (e.g. "seo", "ecommerce", "security").',
				],
				'per_page'  => [
					'type'        => 'integer',
					'description' => 'Number of results (max 10). Defaults to 5.',
				],
			],
			'required'   => [ 'operation', 'query' ],
		];
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'install_plugins';
	}

	/**
	 * Whether this action is reversible.
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	public function is_reversible(): bool {
		return false;
	}

	/**
	 * Execute the action.
	 *
	 * @since 1.1.0
	 *
	 * @param array $params Validated parameters.
	 * @return array Execution result.
	 */
	public function execute( array $params ): array {
		$operation = $params['operation'] ?? '';
		$query     = ! empty( $params['query'] ) ? sanitize_text_field( $params['query'] ) : '';

		if ( empty( $query ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Search query is required.', 'wp-agent' ),
			];
		}

		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		$per_page = isset( $params['per_page'] ) ? min( absint( $params['per_page'] ), self::MAX_RESULTS ) : 5;

		switch ( $operation ) {
			case 'search':
				return $this->search_plugins( $query, $per_page, $params );

			case 'recommend':
				return $this->recommend_plugins( $query, $per_page, $params );

			default:
				return [
					'success' => false,
					'data'    => null,
					'message' => __( 'Invalid operation. Use "search" or "recommend".', 'wp-agent' ),
				];
		}
	}

	/**
	 * Search plugins by keyword.
	 *
	 * @since 1.1.0
	 *
	 * @param string $query    Search query.
	 * @param int    $per_page Results per page.
	 * @param array  $params   Full parameters.
	 * @return array Execution result.
	 */
	private function search_plugins( $query, $per_page, $params ) {
		$args = [
			'keyword'  => $query,
			'per_page' => $per_page,
			'page'     => 1,
			'fields'   => [
				'short_description' => true,
				'icons'             => false,
				'banners'           => false,
				'compatibility'     => false,
				'sections'          => false,
				'downloaded'        => true,
				'last_updated'      => true,
				'active_installs'   => true,
				'rating'            => true,
				'num_ratings'       => true,
				'tested'            => true,
			],
		];

		if ( ! empty( $params['tag'] ) ) {
			$args['tag'] = sanitize_key( $params['tag'] );
		}

		$api_result = plugins_api( 'query_plugins', $args );

		if ( is_wp_error( $api_result ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Plugin search failed: %s', 'wp-agent' ),
					$api_result->get_error_message()
				),
			];
		}

		$plugins = $this->format_plugins( $api_result->plugins ?? [] );

		return [
			'success' => true,
			'data'    => [
				'query'   => $query,
				'plugins' => $plugins,
				'total'   => count( $plugins ),
			],
			'message' => sprintf(
				/* translators: 1: plugin count, 2: search query */
				__( 'Found %1$d plugin(s) for "%2$s".', 'wp-agent' ),
				count( $plugins ),
				$query
			),
		];
	}

	/**
	 * Recommend plugins for a use case, sorted by rating and installs.
	 *
	 * @since 1.1.0
	 *
	 * @param string $query    Use case description.
	 * @param int    $per_page Results per page.
	 * @param array  $params   Full parameters.
	 * @return array Execution result.
	 */
	private function recommend_plugins( $query, $per_page, $params ) {
		$args = [
			'keyword'  => $query,
			'per_page' => $per_page * 2, // Fetch more so we can filter.
			'page'     => 1,
			'fields'   => [
				'short_description' => true,
				'icons'             => false,
				'banners'           => false,
				'compatibility'     => false,
				'sections'          => false,
				'downloaded'        => true,
				'last_updated'      => true,
				'active_installs'   => true,
				'rating'            => true,
				'num_ratings'       => true,
				'tested'            => true,
			],
		];

		if ( ! empty( $params['tag'] ) ) {
			$args['tag'] = sanitize_key( $params['tag'] );
		}

		$api_result = plugins_api( 'query_plugins', $args );

		if ( is_wp_error( $api_result ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Plugin recommendation failed: %s', 'wp-agent' ),
					$api_result->get_error_message()
				),
			];
		}

		$raw_plugins = $api_result->plugins ?? [];

		// Sort by a score combining rating and active installs.
		usort( $raw_plugins, function ( $a, $b ) {
			$a = (array) $a;
			$b = (array) $b;
			$score_a = ( $a['rating'] ?? 0 ) * 0.4 + min( ( $a['active_installs'] ?? 0 ) / 100000, 100 ) * 0.6;
			$score_b = ( $b['rating'] ?? 0 ) * 0.4 + min( ( $b['active_installs'] ?? 0 ) / 100000, 100 ) * 0.6;
			return $score_b <=> $score_a;
		} );

		$plugins = $this->format_plugins( array_slice( $raw_plugins, 0, $per_page ) );

		return [
			'success' => true,
			'data'    => [
				'query'          => $query,
				'plugins'        => $plugins,
				'total'          => count( $plugins ),
				'recommendation' => __( 'Sorted by combined rating and popularity score.', 'wp-agent' ),
			],
			'message' => sprintf(
				/* translators: 1: plugin count, 2: use case */
				__( 'Recommending %1$d plugin(s) for "%2$s".', 'wp-agent' ),
				count( $plugins ),
				$query
			),
		];
	}

	/**
	 * Format plugin API results into a clean array.
	 *
	 * @since 1.1.0
	 *
	 * @param array $raw_plugins Raw plugin objects from plugins_api.
	 * @return array Formatted plugin data.
	 */
	private function format_plugins( array $raw_plugins ) {
		$plugins = [];

		foreach ( $raw_plugins as $plugin ) {
			// Results may be arrays or objects depending on WP version.
			$p = (array) $plugin;

			$plugins[] = [
				'name'              => sanitize_text_field( $p['name'] ?? '' ),
				'slug'              => sanitize_key( $p['slug'] ?? '' ),
				'rating'            => round( ( $p['rating'] ?? 0 ) / 20, 1 ), // Convert to 5-star scale.
				'num_ratings'       => (int) ( $p['num_ratings'] ?? 0 ),
				'active_installs'   => (int) ( $p['active_installs'] ?? 0 ),
				'last_updated'      => sanitize_text_field( $p['last_updated'] ?? '' ),
				'tested'            => sanitize_text_field( $p['tested'] ?? '' ),
				'short_description' => wp_trim_words( sanitize_text_field( $p['short_description'] ?? '' ), 30 ),
			];
		}

		return $plugins;
	}
}
