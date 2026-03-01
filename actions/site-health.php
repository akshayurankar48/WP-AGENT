<?php
/**
 * Site Health Action.
 *
 * Runs WordPress Site Health direct tests and returns diagnostic results.
 * Read-only — does not modify the site. Skips async tests (loopback, dotorg)
 * to avoid slow external requests.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Site_Health
 *
 * @since 1.0.0
 */
class Site_Health implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'site_health';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Run WordPress Site Health diagnostics and return the results. '
			. 'Read-only — does not modify the site. Can filter by category: "security", "performance", or "all".';
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
					'description' => 'Filter results by category: "security", "performance", or "all". Defaults to "all".',
					'enum'        => [ 'all', 'security', 'performance' ],
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
		return 'view_site_health_checks';
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
		$category = isset( $params['category'] ) ? sanitize_text_field( $params['category'] ) : 'all';

		// Load admin includes that Site Health tests depend on.
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		if ( ! function_exists( 'got_url_rewrite' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		}

		$site_health = \WP_Site_Health::get_instance();
		$all_tests   = \WP_Site_Health::get_tests();

		// Only run direct tests (skip async tests which make external requests).
		if ( empty( $all_tests['direct'] ) || ! is_array( $all_tests['direct'] ) ) {
			return [
				'success' => true,
				'data'    => [
					'total'       => 0,
					'good'        => 0,
					'recommended' => 0,
					'critical'    => 0,
					'results'     => [],
				],
				'message' => __( 'No direct Site Health tests available.', 'wp-agent' ),
			];
		}

		$results     = [];
		$good        = 0;
		$recommended = 0;
		$critical    = 0;

		foreach ( $all_tests['direct'] as $test_key => $test_info ) {
			// Each direct test has a callback that returns a result array.
			$test_function = false;
			if ( isset( $test_info['test'] ) && is_string( $test_info['test'] ) ) {
				$method = 'get_test_' . $test_info['test'];
				if ( method_exists( $site_health, $method ) ) {
					$test_function = [ $site_health, $method ];
				}
			} elseif ( isset( $test_info['test'] ) && is_callable( $test_info['test'] ) ) {
				$test_function = $test_info['test'];
			}

			if ( ! $test_function || ! is_callable( $test_function ) ) {
				continue;
			}

			// Run the test safely — skip if it throws.
			try {
				$result = call_user_func( $test_function );
			} catch ( \Throwable $e ) {
				continue;
			}

			if ( ! is_array( $result ) || empty( $result['status'] ) ) {
				continue;
			}

			// Apply category filter based on badge label.
			if ( 'all' !== $category ) {
				$badge_label = isset( $result['badge']['label'] ) ? strtolower( $result['badge']['label'] ) : '';
				if ( $badge_label !== $category ) {
					continue;
				}
			}

			// Strip HTML from description and label.
			$label       = isset( $result['label'] ) ? wp_strip_all_tags( $result['label'] ) : $test_key;
			$badge_label = isset( $result['badge']['label'] ) ? wp_strip_all_tags( $result['badge']['label'] ) : '';

			$results[] = [
				'test'        => $test_key,
				'label'       => $label,
				'status'      => $result['status'],
				'badge_label' => $badge_label,
			];

			// Count by status.
			switch ( $result['status'] ) {
				case 'good':
					++$good;
					break;
				case 'recommended':
					++$recommended;
					break;
				case 'critical':
					++$critical;
					break;
			}
		}

		$total = count( $results );

		return [
			'success' => true,
			'data'    => [
				'total'       => $total,
				'good'        => $good,
				'recommended' => $recommended,
				'critical'    => $critical,
				'results'     => $results,
			],
			'message' => sprintf(
				/* translators: 1: total tests, 2: good count, 3: recommended count, 4: critical count */
				__( 'Site Health: %1$d tests — %2$d good, %3$d recommended, %4$d critical.', 'wp-agent' ),
				$total,
				$good,
				$recommended,
				$critical
			),
		];
	}
}
