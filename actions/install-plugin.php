<?php
/**
 * Install Plugin Action.
 *
 * Installs a plugin from the WordPress.org repository by slug.
 * Uses Plugin_Upgrader with a silent skin to suppress output.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Install_Plugin
 *
 * @since 1.0.0
 */
class Install_Plugin implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'install_plugin';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Install a plugin from the WordPress.org repository by its slug (e.g. "contact-form-7"). '
			. 'Does not activate the plugin — use activate_plugin for that.';
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
				'slug' => [
					'type'        => 'string',
					'description' => 'The WordPress.org plugin slug (e.g. "contact-form-7", "akismet").',
				],
			],
			'required'   => [ 'slug' ],
		];
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'install_plugins';
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
		$slug = sanitize_text_field( $params['slug'] );

		// Only allow alphanumeric characters and hyphens in slugs.
		if ( ! preg_match( '/^[a-z0-9\-]+$/', $slug ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Invalid plugin slug. Only lowercase letters, numbers, and hyphens are allowed.', 'wp-agent' ),
			];
		}

		// Load required upgrader files.
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// Check if plugin is already installed.
		$installed_plugins = get_plugins();
		foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
			if ( 0 === strpos( $plugin_file, $slug . '/' ) ) {
				return [
					'success' => false,
					'data'    => [
						'slug'        => $slug,
						'plugin_file' => $plugin_file,
					],
					'message' => sprintf(
						/* translators: %s: plugin name */
						__( 'Plugin "%s" is already installed.', 'wp-agent' ),
						$plugin_data['Name']
					),
				];
			}
		}

		// Fetch plugin information from WordPress.org API.
		$api = plugins_api(
			'plugin_information',
			[
				'slug'   => $slug,
				'fields' => [
					'sections' => false,
					'versions' => false,
				],
			]
		);

		if ( is_wp_error( $api ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: 1: plugin slug, 2: error message */
					__( 'Could not fetch plugin "%1$s" from WordPress.org: %2$s', 'wp-agent' ),
					$slug,
					$api->get_error_message()
				),
			];
		}

		// Install the plugin using silent skin.
		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => $result->get_error_message(),
			];
		}

		if ( is_wp_error( $skin->result ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => $skin->result->get_error_message(),
			];
		}

		if ( ! $result ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: plugin slug */
					__( 'Failed to install plugin "%s". Check filesystem permissions.', 'wp-agent' ),
					$slug
				),
			];
		}

		// Find the installed plugin file.
		$plugin_file = $upgrader->plugin_info();

		// Re-read plugins to get the installed plugin data.
		$all_plugins = get_plugins();
		$plugin_data = isset( $all_plugins[ $plugin_file ] ) ? $all_plugins[ $plugin_file ] : [];

		return [
			'success' => true,
			'data'    => [
				'slug'        => $slug,
				'name'        => isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : $slug,
				'version'     => isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '',
				'plugin_file' => $plugin_file,
			],
			'message' => sprintf(
				/* translators: 1: plugin name, 2: plugin version */
				__( 'Installed "%1$s" (v%2$s) successfully. Use activate_plugin to activate it.', 'wp-agent' ),
				isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : $slug,
				isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : 'unknown'
			),
		];
	}
}
