<?php
/**
 * Deactivate Plugin Action.
 *
 * Deactivates an active WordPress plugin by its plugin file path.
 * Validates the plugin exists and is currently active before deactivating.
 * Includes a self-deactivation guard to prevent WP Agent from deactivating itself.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Deactivate_Plugin
 *
 * @since 1.0.0
 */
class Deactivate_Plugin implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'deactivate_plugin';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Deactivate a currently active WordPress plugin. Requires the plugin file path '
			. '(e.g. "akismet/akismet.php", "hello.php"). Cannot deactivate WP Agent itself.';
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
				'plugin' => [
					'type'        => 'string',
					'description' => 'The plugin file path relative to the plugins directory (e.g. "akismet/akismet.php").',
				],
			],
			'required'   => [ 'plugin' ],
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
		$plugin = sanitize_text_field( $params['plugin'] );

		// Validate the file path is safe.
		if ( 0 !== validate_file( $plugin ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Invalid plugin file path.', 'wp-agent' ),
			];
		}

		// Self-deactivation guard: prevent deactivating WP Agent itself.
		if ( defined( 'WP_AGENT_BASE' ) && WP_AGENT_BASE === $plugin ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Cannot deactivate WP Agent through its own action system.', 'wp-agent' ),
			];
		}

		// Load plugin functions if not already available.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Validate the plugin exists in the installed plugins list.
		$installed_plugins = get_plugins();

		if ( ! isset( $installed_plugins[ $plugin ] ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: plugin file path */
					__( 'Plugin "%s" is not installed.', 'wp-agent' ),
					$plugin
				),
			];
		}

		// Check if already inactive.
		if ( ! is_plugin_active( $plugin ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: plugin name */
					__( 'Plugin "%s" is already inactive.', 'wp-agent' ),
					$installed_plugins[ $plugin ]['Name']
				),
			];
		}

		// Deactivate the plugin.
		deactivate_plugins( [ $plugin ] );

		// Verify deactivation succeeded.
		if ( is_plugin_active( $plugin ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: plugin name */
					__( 'Failed to deactivate "%s".', 'wp-agent' ),
					$installed_plugins[ $plugin ]['Name']
				),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'plugin' => $plugin,
				'name'   => $installed_plugins[ $plugin ]['Name'],
			],
			'message' => sprintf(
				/* translators: %s: plugin name */
				__( 'Deactivated plugin "%s" successfully.', 'wp-agent' ),
				$installed_plugins[ $plugin ]['Name']
			),
		];
	}
}
