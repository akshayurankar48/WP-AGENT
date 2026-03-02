<?php
/**
 * Install Theme Action.
 *
 * Installs a theme from the WordPress.org repository. Accepts a slug
 * or search term — if the exact slug is not found, falls back to a
 * keyword search and installs the best match.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Install_Theme
 *
 * @since 1.0.0
 */
class Install_Theme implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'install_theme';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Install a theme from the WordPress.org repository. Pass the theme slug '
			. '(e.g. "hello-elementor") or a search term (e.g. "hello elementor", "astra"). '
			. 'If the exact slug is not found, searches WordPress.org and installs the best match. '
			. 'Does not activate — use manage_theme with operation "switch" after installing.';
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
					'description' => 'The WordPress.org theme slug (e.g. "hello-elementor", "astra") or a search '
						. 'term (e.g. "elementor theme", "flavor theme"). The system will search if the exact slug is not found.',
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
		return 'install_themes';
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
				'message' => __( 'Theme slug or search term is required.', 'wp-agent' ),
			];
		}

		// Load required files.
		require_once ABSPATH . 'wp-admin/includes/theme-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		// Normalize: lowercase, trim, convert spaces to hyphens for slug attempt.
		$normalized = strtolower( trim( $slug ) );
		$slug_attempt = preg_replace( '/[^a-z0-9\-]/', '-', $normalized );
		$slug_attempt = preg_replace( '/-+/', '-', $slug_attempt );
		$slug_attempt = trim( $slug_attempt, '-' );

		// Check if already installed.
		$installed_themes = wp_get_themes();
		if ( isset( $installed_themes[ $slug_attempt ] ) ) {
			$theme = $installed_themes[ $slug_attempt ];
			return [
				'success' => false,
				'data'    => [
					'slug'    => $slug_attempt,
					'name'    => $theme->get( 'Name' ),
					'version' => $theme->get( 'Version' ),
				],
				'message' => sprintf(
					/* translators: %s: theme name */
					__( 'Theme "%s" is already installed. Use manage_theme with operation "switch" to activate it.', 'wp-agent' ),
					$theme->get( 'Name' )
				),
			];
		}

		// Try exact slug lookup first.
		$api = themes_api(
			'theme_information',
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
			$search_result = $this->search_and_resolve( $normalized );

			if ( is_wp_error( $search_result ) ) {
				return [
					'success' => false,
					'data'    => null,
					'message' => sprintf(
						/* translators: 1: search term, 2: error */
						__( 'Could not find theme "%1$s" on WordPress.org. Searched by slug and keyword. %2$s', 'wp-agent' ),
						$slug,
						$search_result->get_error_message()
					),
				];
			}

			$api = $search_result;
		}

		// Check if the resolved theme is already installed.
		$resolved_slug = $api->slug ?? $slug_attempt;
		if ( isset( $installed_themes[ $resolved_slug ] ) ) {
			$theme = $installed_themes[ $resolved_slug ];
			return [
				'success' => false,
				'data'    => [
					'slug'    => $resolved_slug,
					'name'    => $theme->get( 'Name' ),
					'version' => $theme->get( 'Version' ),
				],
				'message' => sprintf(
					/* translators: 1: theme name, 2: original search */
					__( 'Theme "%1$s" (matched from "%2$s") is already installed. Use manage_theme with operation "switch" to activate it.', 'wp-agent' ),
					$theme->get( 'Name' ),
					$slug
				),
			];
		}

		return $this->install_from_api( $api, $slug );
	}

	/**
	 * Search WordPress.org for a theme by keyword and return the best match.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query Search term.
	 * @return object|\WP_Error Theme API object or error.
	 */
	private function search_and_resolve( $query ) {
		$search = themes_api(
			'query_themes',
			[
				'search'   => $query,
				'per_page' => 5,
				'page'     => 1,
				'fields'   => [
					'sections' => false,
					'versions' => false,
				],
			]
		);

		if ( is_wp_error( $search ) ) {
			return $search;
		}

		$themes = $search->themes ?? [];

		if ( empty( $themes ) ) {
			return new \WP_Error(
				'no_results',
				__( 'No themes found matching that search term.', 'wp-agent' )
			);
		}

		// Return the top result — may be array or object.
		$best      = $themes[0];
		$best_slug = is_object( $best ) ? $best->slug : ( $best['slug'] ?? '' );

		if ( empty( $best_slug ) ) {
			return new \WP_Error(
				'no_slug',
				__( 'Search returned results but could not determine theme slug.', 'wp-agent' )
			);
		}

		// Fetch full theme information.
		return themes_api(
			'theme_information',
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
	 * Install a theme from its API object.
	 *
	 * @since 1.0.0
	 *
	 * @param object $api           Theme API object with download_link.
	 * @param string $original_slug The original slug/term the user searched for.
	 * @return array Execution result.
	 */
	private function install_from_api( $api, $original_slug ) {
		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
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
					/* translators: %s: theme slug */
					__( 'Failed to install theme "%s". Check filesystem permissions.', 'wp-agent' ),
					$api->slug ?? $original_slug
				),
			];
		}

		// Get the installed theme info.
		$theme_info = $upgrader->theme_info();
		$stylesheet = $theme_info ? $theme_info->get_stylesheet() : ( $api->slug ?? $original_slug );
		$theme      = wp_get_theme( $stylesheet );

		$name    = $theme->exists() ? $theme->get( 'Name' ) : ( $api->name ?? $original_slug );
		$version = $theme->exists() ? $theme->get( 'Version' ) : '';

		$data = [
			'slug'           => $stylesheet,
			'name'           => $name,
			'version'        => $version,
			'is_block_theme' => $theme->exists() ? $theme->is_block_theme() : false,
		];

		$resolved_slug = $api->slug ?? '';
		$message       = sprintf(
			/* translators: 1: theme name, 2: theme version */
			__( 'Installed "%1$s" (v%2$s) successfully. Use manage_theme with operation "switch" to activate it.', 'wp-agent' ),
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
}
