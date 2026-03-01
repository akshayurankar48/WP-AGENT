<?php
/**
 * List Plugins Action.
 *
 * Lists all installed WordPress plugins with their status,
 * version, and description.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class List_Plugins
 *
 * @since 1.0.0
 */
class List_Plugins implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'list_plugins';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'List all installed WordPress plugins with name, slug, version, status (active/inactive), '
			. 'and description. Use this to check what plugins are installed before installing or activating.';
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
				'status' => [
					'type'        => 'string',
					'enum'        => [ 'all', 'active', 'inactive' ],
					'description' => 'Filter by plugin status. Defaults to "all".',
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
		return 'activate_plugins';
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
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$status_filter = ! empty( $params['status'] ) ? sanitize_key( $params['status'] ) : 'all';
		$all_plugins   = get_plugins();
		$results       = [];

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$is_active = is_plugin_active( $plugin_file );

			// Apply status filter.
			if ( 'active' === $status_filter && ! $is_active ) {
				continue;
			}
			if ( 'inactive' === $status_filter && $is_active ) {
				continue;
			}

			// Extract slug from plugin file path.
			$slug = dirname( $plugin_file );
			if ( '.' === $slug ) {
				$slug = basename( $plugin_file, '.php' );
			}

			$results[] = [
				'name'        => sanitize_text_field( $plugin_data['Name'] ?? '' ),
				'slug'        => sanitize_key( $slug ),
				'version'     => sanitize_text_field( $plugin_data['Version'] ?? '' ),
				'status'      => $is_active ? 'active' : 'inactive',
				'description' => sanitize_text_field( wp_trim_words( $plugin_data['Description'] ?? '', 20 ) ),
			];
		}

		$count_text = sprintf(
			__( '%1$d plugin(s) found (%2$d active, %3$d inactive).', 'wp-agent' ),
			count( $results ),
			count( array_filter( $results, fn( $p ) => 'active' === $p['status'] ) ),
			count( array_filter( $results, fn( $p ) => 'inactive' === $p['status'] ) )
		);

		return [
			'success' => true,
			'data'    => [
				'total'   => count( $results ),
				'plugins' => $results,
			],
			'message' => $count_text,
		];
	}
}
