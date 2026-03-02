<?php
/**
 * Install Plugin Action.
 *
 * Installs a plugin from the WordPress.org repository. Accepts a slug
 * or search term — if the exact slug is not found, falls back to a
 * keyword search and installs the best match.
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
		return 'Install a plugin from the WordPress.org repository. Pass the plugin slug '
			. '(e.g. "contact-form-7") or a search term (e.g. "ultimate addons elementor"). '
			. 'If the exact slug is not found, searches WordPress.org and installs the best match. '
			. 'Does not activate — use activate_plugin after installing.';
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
					'description' => 'The WordPress.org plugin slug (e.g. "contact-form-7") or a search term '
						. '(e.g. "ultimate addons elementor"). The system will search if the exact slug is not found.',
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

		if ( empty( $slug ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Plugin slug or search term is required.', 'wp-agent' ),
			];
		}

		// Load required upgrader files.
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// Normalize: lowercase, trim, convert spaces to hyphens for slug attempt.
		$normalized_slug = strtolower( trim( $slug ) );
		$slug_attempt    = preg_replace( '/[^a-z0-9\-]/', '-', $normalized_slug );
		$slug_attempt    = preg_replace( '/-+/', '-', $slug_attempt );
		$slug_attempt    = trim( $slug_attempt, '-' );

		// Check if already installed by slug.
		$existing = $this->find_installed_plugin( $slug_attempt );
		if ( $existing ) {
			return [
				'success' => false,
				'data'    => $existing,
				'message' => sprintf(
					/* translators: %s: plugin name */
					__( 'Plugin "%s" is already installed. Use activate_plugin to activate it.', 'wp-agent' ),
					$existing['name']
				),
			];
		}

		// Try exact slug lookup first.
		$api = plugins_api(
			'plugin_information',
			[
				'slug'   => $slug_attempt,
				'fields' => [
					'sections' => false,
					'versions' => false,
				],
			]
		);

		// If exact slug fails, search WordPress.org.
		if ( is_wp_error( $api ) ) {
			$search_result = $this->search_and_resolve( $normalized_slug );

			if ( is_wp_error( $search_result ) ) {
				return [
					'success' => false,
					'data'    => null,
					'message' => sprintf(
						/* translators: 1: search term, 2: error */
						__( 'Could not find plugin "%1$s" on WordPress.org. Searched by slug and keyword. %2$s', 'wp-agent' ),
						$slug,
						$search_result->get_error_message()
					),
				];
			}

			$api = $search_result;
		}

		// Check if the resolved plugin is already installed.
		$resolved_slug = $api->slug ?? $slug_attempt;
		$existing      = $this->find_installed_plugin( $resolved_slug );
		if ( $existing ) {
			return [
				'success' => false,
				'data'    => $existing,
				'message' => sprintf(
					/* translators: 1: plugin name, 2: original search */
					__( 'Plugin "%1$s" (matched from "%2$s") is already installed. Use activate_plugin to activate it.', 'wp-agent' ),
					$existing['name'],
					$slug
				),
			];
		}

		return $this->install_from_api( $api, $slug );
	}

	/**
	 * Search WordPress.org for a plugin by keyword and return the best match.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query Search term.
	 * @return object|\WP_Error Plugin API object or error.
	 */
	private function search_and_resolve( $query ) {
		$search = plugins_api(
			'query_plugins',
			[
				'keyword'  => $query,
				'per_page' => 5,
				'page'     => 1,
				'fields'   => [
					'sections'        => false,
					'versions'        => false,
					'active_installs' => true,
				],
			]
		);

		if ( is_wp_error( $search ) ) {
			return $search;
		}

		$plugins = $search->plugins ?? [];

		if ( empty( $plugins ) ) {
			return new \WP_Error(
				'no_results',
				__( 'No plugins found matching that search term.', 'wp-agent' )
			);
		}

		// Return the top result — WordPress.org already ranks by relevance.
		// Results may be arrays or objects depending on WP version.
		$best      = $plugins[0];
		$best_slug = is_object( $best ) ? $best->slug : ( $best['slug'] ?? '' );

		if ( empty( $best_slug ) ) {
			return new \WP_Error(
				'no_slug',
				__( 'Search returned results but could not determine plugin slug.', 'wp-agent' )
			);
		}

		// Fetch full plugin information for the best match.
		return plugins_api(
			'plugin_information',
			[
				'slug'   => $best_slug,
				'fields' => [
					'sections' => false,
					'versions' => false,
				],
			]
		);
	}

	/**
	 * Install a plugin from its API object.
	 *
	 * @since 1.0.0
	 *
	 * @param object $api           Plugin API object with download_link.
	 * @param string $original_slug The original slug/term the user searched for.
	 * @return array Execution result.
	 */
	private function install_from_api( $api, $original_slug ) {
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
					$api->slug ?? $original_slug
				),
			];
		}

		// Find the installed plugin file.
		$plugin_file = $upgrader->plugin_info();

		// Re-read plugins to get the installed plugin data.
		$all_plugins = get_plugins();
		$plugin_data = isset( $all_plugins[ $plugin_file ] ) ? $all_plugins[ $plugin_file ] : [];

		$name    = isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : ( $api->name ?? $original_slug );
		$version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '';

		$data = [
			'slug'        => $api->slug ?? $original_slug,
			'name'        => $name,
			'version'     => $version,
			'plugin_file' => $plugin_file,
		];

		// Note if we resolved from a different slug.
		$resolved_slug = $api->slug ?? '';
		$message       = sprintf(
			/* translators: 1: plugin name, 2: plugin version */
			__( 'Installed "%1$s" (v%2$s) successfully. Use activate_plugin to activate it.', 'wp-agent' ),
			$name,
			$version ? $version : 'unknown'
		);

		if ( $resolved_slug && $resolved_slug !== $original_slug ) {
			$data['resolved_from'] = $original_slug;
			$message              .= sprintf(
				/* translators: 1: original search, 2: resolved slug */
				__( ' (Resolved "%1$s" → slug "%2$s")', 'wp-agent' ),
				$original_slug,
				$resolved_slug
			);
		}

		return [
			'success' => true,
			'data'    => $data,
			'message' => $message,
		];
	}

	/**
	 * Check if a plugin is already installed by matching slug to plugin directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Plugin slug to check.
	 * @return array|false Plugin data array if found, false if not installed.
	 */
	private function find_installed_plugin( $slug ) {
		$installed_plugins = get_plugins();

		foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
			if ( 0 === strpos( $plugin_file, $slug . '/' ) ) {
				return [
					'slug'        => $slug,
					'name'        => $plugin_data['Name'],
					'plugin_file' => $plugin_file,
				];
			}
		}

		return false;
	}
}
