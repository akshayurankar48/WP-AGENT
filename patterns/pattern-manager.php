<?php
/**
 * Pattern Manager.
 *
 * Loads, caches, and resolves curated section patterns and full-page
 * blueprints. Patterns are JSON files stored in patterns/library/ and
 * provide production-grade block structures that the AI can customize
 * with variable overrides and theme token substitution.
 *
 * @package WPAgent\Patterns
 * @since   1.0.0
 */

namespace WPAgent\Patterns;

defined( 'ABSPATH' ) || exit;

/**
 * Class Pattern_Manager
 *
 * @since 1.0.0
 */
class Pattern_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var Pattern_Manager|null
	 */
	private static $instance = null;

	/**
	 * In-memory pattern cache (keyed by pattern ID).
	 *
	 * @var array
	 */
	private $cache = [];

	/**
	 * Pattern metadata index (keyed by pattern ID).
	 *
	 * @var array|null
	 */
	private $index = null;

	/**
	 * Blueprint cache (keyed by blueprint ID).
	 *
	 * @var array
	 */
	private $blueprints = [];

	/**
	 * Base path for the pattern library.
	 *
	 * @var string
	 */
	private $library_path;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return Pattern_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->library_path = WP_AGENT_DIR . 'patterns/library/';
	}

	/**
	 * Get a single pattern with variable and theme token resolution.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id        Pattern ID (e.g. 'hero-dark').
	 * @param array  $overrides Optional variable overrides.
	 * @return array|null Pattern data with resolved blocks, or null if not found.
	 */
	public function get_pattern( $id, array $overrides = [] ) {
		$id = sanitize_key( $id );

		if ( isset( $this->cache[ $id ] ) ) {
			$pattern = $this->cache[ $id ];
		} else {
			$pattern = $this->load_pattern( $id );
			if ( ! $pattern ) {
				return null;
			}
			$this->cache[ $id ] = $pattern;
		}

		// Merge variable overrides with defaults.
		$variables = isset( $pattern['variables'] ) ? $pattern['variables'] : [];
		$variables = array_merge( $variables, $overrides );

		// Resolve theme tokens in variables.
		$variables = $this->resolve_theme_tokens( $variables );

		// Resolve variables in the blocks JSON.
		$blocks = $this->resolve_variables( $pattern['blocks'], $variables );

		return [
			'id'          => $pattern['id'],
			'name'        => $pattern['name'],
			'category'    => $pattern['category'],
			'description' => $pattern['description'],
			'variables'   => $variables,
			'blocks'      => $blocks,
		];
	}

	/**
	 * List all patterns, optionally filtered by category.
	 *
	 * Returns metadata only (no blocks) for AI catalog browsing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $category Optional category filter.
	 * @param string $search   Optional keyword search.
	 * @return array Array of pattern metadata.
	 */
	public function list_patterns( $category = '', $search = '' ) {
		$this->build_index();

		$results = [];
		foreach ( $this->index as $pattern ) {
			// Category filter.
			if ( $category && $pattern['category'] !== $category ) {
				continue;
			}

			// Keyword search (name + description).
			if ( $search ) {
				$haystack = strtolower( $pattern['name'] . ' ' . $pattern['description'] );
				if ( false === strpos( $haystack, strtolower( $search ) ) ) {
					continue;
				}
			}

			$results[] = [
				'id'          => $pattern['id'],
				'name'        => $pattern['name'],
				'category'    => $pattern['category'],
				'description' => $pattern['description'],
			];
		}

		return $results;
	}

	/**
	 * Get a full-page blueprint.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Blueprint ID (e.g. 'landing-page').
	 * @return array|null Blueprint data or null if not found.
	 */
	public function get_blueprint( $id ) {
		$id = sanitize_key( $id );

		if ( isset( $this->blueprints[ $id ] ) ) {
			return $this->blueprints[ $id ];
		}

		$file = $this->library_path . 'blueprints/' . $id . '.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}

		$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		if ( false === $raw ) {
			return null;
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['id'] ) || empty( $data['sections'] ) ) {
			return null;
		}

		$blueprint = [
			'id'          => sanitize_key( $data['id'] ),
			'name'        => sanitize_text_field( $data['name'] ?? '' ),
			'description' => sanitize_text_field( $data['description'] ?? '' ),
			'sections'    => array_map( 'sanitize_key', $data['sections'] ),
		];

		$this->blueprints[ $id ] = $blueprint;

		return $blueprint;
	}

	/**
	 * Get available categories.
	 *
	 * @since 1.0.0
	 * @return array List of category slugs.
	 */
	public function get_categories() {
		$this->build_index();

		$categories = [];
		foreach ( $this->index as $pattern ) {
			$categories[ $pattern['category'] ] = true;
		}

		return array_keys( $categories );
	}

	/**
	 * Build the metadata index by scanning library directories.
	 *
	 * @since 1.0.0
	 */
	private function build_index() {
		if ( null !== $this->index ) {
			return;
		}

		$this->index = [];

		$categories = glob( $this->library_path . '*', GLOB_ONLYDIR );
		if ( ! $categories ) {
			return;
		}

		foreach ( $categories as $cat_dir ) {
			$cat_name = basename( $cat_dir );

			// Skip blueprints — they are not section patterns.
			if ( 'blueprints' === $cat_name ) {
				continue;
			}

			$files = glob( $cat_dir . '/*.json' );
			if ( ! $files ) {
				continue;
			}

			foreach ( $files as $file ) {
				$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
				if ( false === $raw ) {
					continue;
				}

				$data = json_decode( $raw, true );
				if ( ! is_array( $data ) || empty( $data['id'] ) ) {
					continue;
				}

				$this->index[ $data['id'] ] = [
					'id'          => sanitize_key( $data['id'] ),
					'name'        => sanitize_text_field( $data['name'] ?? '' ),
					'category'    => sanitize_key( $data['category'] ?? $cat_name ),
					'description' => sanitize_text_field( $data['description'] ?? '' ),
					'file'        => $file,
				];
			}
		}
	}

	/**
	 * Load a single pattern from disk.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Pattern ID.
	 * @return array|null Parsed pattern data or null if not found.
	 */
	private function load_pattern( $id ) {
		$this->build_index();

		if ( ! isset( $this->index[ $id ] ) ) {
			return null;
		}

		$file = $this->index[ $id ]['file'];
		$raw  = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		if ( false === $raw ) {
			return null;
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['blocks'] ) ) {
			return null;
		}

		return $data;
	}

	/**
	 * Resolve theme token placeholders in variable values.
	 *
	 * Tokens like {{primary}}, {{primary_dark}}, {{secondary}} are replaced
	 * with the active theme's design token colors. Falls back to sensible
	 * defaults when theme tokens are unavailable.
	 *
	 * @since 1.0.0
	 *
	 * @param array $variables Variable key-value pairs.
	 * @return array Variables with tokens resolved.
	 */
	private function resolve_theme_tokens( array $variables ) {
		$tokens = $this->get_theme_design_tokens();

		foreach ( $variables as $key => $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}

			// Replace {{token_name}} with theme value or default.
			$variables[ $key ] = preg_replace_callback(
				'/\{\{(\w+)\}\}/',
				function ( $matches ) use ( $tokens ) {
					$token_name = $matches[1];
					return isset( $tokens[ $token_name ] ) ? $tokens[ $token_name ] : $matches[0];
				},
				$value
			);
		}

		return $variables;
	}

	/**
	 * Get theme design tokens with fallback defaults.
	 *
	 * @since 1.0.0
	 * @return array Token name => hex color mapping.
	 */
	private function get_theme_design_tokens() {
		static $tokens = null;

		if ( null !== $tokens ) {
			return $tokens;
		}

		// Defaults for when theme tokens are unavailable.
		$tokens = [
			'primary'        => '#6366f1',
			'primary_dark'   => '#0a0a0a',
			'primary_light'  => '#e0e7ff',
			'secondary'      => '#a855f7',
			'accent'         => '#818cf8',
			'text_dark'      => '#111827',
			'text_muted'     => '#6b7280',
			'text_light'     => '#9ca3af',
			'bg_dark'        => '#0f172a',
			'bg_light'       => '#f8fafc',
			'bg_white'       => '#ffffff',
			'border_light'   => '#e5e7eb',
		];

		// Try to pull real colors from the active theme's global styles.
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$settings = wp_get_global_settings( [ 'color', 'palette', 'theme' ] );
			if ( is_array( $settings ) && ! empty( $settings ) ) {
				foreach ( $settings as $color ) {
					if ( empty( $color['slug'] ) || empty( $color['color'] ) ) {
						continue;
					}
					$slug = sanitize_key( str_replace( '-', '_', $color['slug'] ) );
					$tokens[ $slug ] = sanitize_hex_color( $color['color'] ) ?: $color['color'];
				}
			}
		}

		return $tokens;
	}

	/**
	 * Resolve {{variable}} placeholders throughout the blocks structure.
	 *
	 * Recursively walks the blocks array and replaces string values
	 * containing {{variable_name}} with the resolved variable value.
	 * Applies context-aware escaping: esc_html for innerHTML (text),
	 * sanitize_hex_color for color attrs, esc_url for URL attrs.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $data      The data structure (blocks array, string, etc.).
	 * @param array  $variables Resolved variable key-value pairs.
	 * @param string $context   Current context: 'attr' or 'html'.
	 * @return mixed Data with placeholders replaced.
	 */
	private function resolve_variables( $data, array $variables, $context = 'attr' ) {
		if ( is_string( $data ) ) {
			return preg_replace_callback(
				'/\{\{(\w+)\}\}/',
				function ( $matches ) use ( $variables, $context ) {
					$var_name = $matches[1];
					if ( ! isset( $variables[ $var_name ] ) ) {
						return $matches[0];
					}
					$value = $variables[ $var_name ];

					// Apply context-aware escaping.
					if ( 'html' === $context ) {
						return esc_html( $value );
					}

					// Color values in attrs.
					if ( preg_match( '/color|bg_/', $var_name ) ) {
						$hex = sanitize_hex_color( $value );
						return $hex ? $hex : esc_attr( $value );
					}

					// URL values in attrs.
					if ( preg_match( '/url|image|src|href/', $var_name ) ) {
						return esc_url( $value );
					}

					return esc_attr( $value );
				},
				$data
			);
		}

		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				// innerHTML contains rendered text — escape as HTML.
				$child_context = ( 'innerHTML' === $key ) ? 'html' : $context;
				$data[ $key ]  = $this->resolve_variables( $value, $variables, $child_context );
			}
		}

		return $data;
	}
}
