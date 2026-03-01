<?php
/**
 * Context Collector.
 *
 * Gathers site information for the AI system prompt. Results are cached
 * per-user via a 5-minute transient to avoid repeated WP API calls.
 *
 * Security: Never exposes passwords, API keys, wp-config values, or raw
 * option dumps.
 *
 * @package WPAgent\AI
 * @since   1.0.0
 */

namespace WPAgent\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class Context_Collector
 *
 * @since 1.0.0
 */
class Context_Collector {

	/**
	 * Instance
	 *
	 * @access private
	 * @var Context_Collector|null Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Cache TTL in seconds (5 minutes).
	 *
	 * @var int
	 */
	const CACHE_TTL = 300;

	/**
	 * Maximum number of active plugins to include.
	 *
	 * @var int
	 */
	const MAX_PLUGINS = 30;

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return Context_Collector Initialized object of class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Collect site context for the AI system prompt.
	 *
	 * Returns an associative array of safe, non-sensitive site information.
	 * Results are cached per-user for CACHE_TTL seconds.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $admin_page Current admin page slug (optional).
	 * @param int    $post_id    Current editor post ID (optional).
	 * @return array {
	 *     Site context data.
	 *
	 *     @type string $site_name           Site title.
	 *     @type string $site_url            Home URL.
	 *     @type string $wp_version          WordPress version.
	 *     @type string $php_version         PHP version.
	 *     @type string $locale              Site locale.
	 *     @type string $timezone            Timezone string.
	 *     @type string $permalink_structure Permalink structure.
	 *     @type array  $theme               Active theme {name, version}.
	 *     @type array  $active_plugins      Active plugins [{name, version}, ...].
	 *     @type array  $post_type_counts    Public post type counts {type => count}.
	 *     @type string $user_role           User's role(s).
	 *     @type string $user_name           User's display name.
	 *     @type string $admin_page          Current admin page slug.
	 *     @type array|null $current_post    Current post being edited {id, title, type, status}.
	 * }
	 */
	public function collect( $user_id, $admin_page = '', $post_id = 0 ) {
		$user_id   = (int) $user_id;
		$cache_key = 'wp_agent_ctx_' . $user_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			// Admin page and post context are request-specific, always override from cache.
			$cached['admin_page']   = sanitize_text_field( $admin_page );
			$cached['current_post'] = $this->get_post_context( $post_id );
			return $cached;
		}

		$context = [
			'site_name'           => substr( sanitize_text_field( get_bloginfo( 'name' ) ), 0, 100 ),
			'site_url'            => esc_url( home_url() ),
			'wp_version'          => sanitize_text_field( get_bloginfo( 'version' ) ),
			'php_version'         => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
			'locale'              => sanitize_text_field( get_locale() ),
			'timezone'            => $this->get_timezone_string(),
			'permalink_structure' => sanitize_text_field( get_option( 'permalink_structure', '' ) ),
			'theme'               => $this->get_theme_info(),
			'design_tokens'       => $this->get_theme_design_tokens(),
			'active_plugins'      => $this->get_active_plugins(),
			'post_type_counts'    => $this->get_post_type_counts(),
			'user_role'           => $this->get_user_role( $user_id ),
			'user_name'           => $this->get_user_name( $user_id ),
			'admin_page'          => sanitize_text_field( $admin_page ),
			'current_post'        => $this->get_post_context( $post_id ),
		];

		set_transient( $cache_key, $context, self::CACHE_TTL );

		return $context;
	}

	/**
	 * Invalidate the cached context for a user.
	 *
	 * Call this when site state changes significantly (e.g. after actions).
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return void
	 */
	public function invalidate_cache( $user_id ) {
		delete_transient( 'wp_agent_ctx_' . (int) $user_id );
	}

	/**
	 * Get the WordPress timezone string.
	 *
	 * @since 1.0.0
	 * @return string Timezone string (e.g. 'America/New_York' or 'UTC+5.5').
	 */
	private function get_timezone_string() {
		$timezone = wp_timezone_string();
		return sanitize_text_field( $timezone );
	}

	/**
	 * Get active theme info.
	 *
	 * @since 1.0.0
	 * @return array{name: string, version: string}
	 */
	private function get_theme_info() {
		$theme = wp_get_theme();

		return [
			'name'    => sanitize_text_field( $theme->get( 'Name' ) ),
			'version' => sanitize_text_field( $theme->get( 'Version' ) ),
		];
	}

	/**
	 * Get theme design tokens (colors, gradients, fonts, font sizes).
	 *
	 * Reads from theme.json via wp_get_global_settings() for block themes,
	 * falls back to get_theme_support() for classic themes.
	 *
	 * @since 1.0.0
	 * @return array Design token data for the AI prompt.
	 */
	private function get_theme_design_tokens() {
		$tokens = [];

		// Block themes (WP 5.9+): read from theme.json merged settings.
		if ( function_exists( 'wp_get_global_settings' ) ) {
			try {
				$settings = wp_get_global_settings();
			} catch ( \Throwable $e ) {
				return $tokens;
			}

			if ( ! is_array( $settings ) ) {
				return $tokens;
			}

			// Color palette — check theme, then default.
			$palette = ! empty( $settings['color']['palette']['theme'] )
				? $settings['color']['palette']['theme']
				: ( ! empty( $settings['color']['palette']['default'] ) ? $settings['color']['palette']['default'] : [] );

			if ( ! empty( $palette ) ) {
				$tokens['colors'] = array_values( array_map(
					function ( $color ) {
						return [
							'name'  => sanitize_text_field( $color['name'] ?? '' ),
							'slug'  => sanitize_text_field( $color['slug'] ?? '' ),
							'color' => sanitize_text_field( $color['color'] ?? '' ),
						];
					},
					array_slice( $palette, 0, 16 )
				) );
			}

			// Gradients.
			$gradients = ! empty( $settings['color']['gradients']['theme'] )
				? $settings['color']['gradients']['theme']
				: ( ! empty( $settings['color']['gradients']['default'] ) ? $settings['color']['gradients']['default'] : [] );

			if ( ! empty( $gradients ) ) {
				$tokens['gradients'] = array_values( array_map(
					function ( $g ) {
						return [
							'name'     => sanitize_text_field( $g['name'] ?? '' ),
							'slug'     => sanitize_text_field( $g['slug'] ?? '' ),
							'gradient' => sanitize_text_field( $g['gradient'] ?? '' ),
						];
					},
					array_slice( $gradients, 0, 8 )
				) );
			}

			// Font families.
			$font_families = ! empty( $settings['typography']['fontFamilies']['theme'] )
				? $settings['typography']['fontFamilies']['theme']
				: ( ! empty( $settings['typography']['fontFamilies']['default'] ) ? $settings['typography']['fontFamilies']['default'] : [] );

			if ( ! empty( $font_families ) ) {
				$tokens['fonts'] = array_values( array_map(
					function ( $f ) {
						return [
							'name'       => sanitize_text_field( $f['name'] ?? '' ),
							'slug'       => sanitize_text_field( $f['slug'] ?? '' ),
							'fontFamily' => sanitize_text_field( $f['fontFamily'] ?? '' ),
						];
					},
					array_slice( $font_families, 0, 8 )
				) );
			}

			// Font sizes.
			$font_sizes = ! empty( $settings['typography']['fontSizes']['theme'] )
				? $settings['typography']['fontSizes']['theme']
				: ( ! empty( $settings['typography']['fontSizes']['default'] ) ? $settings['typography']['fontSizes']['default'] : [] );

			if ( ! empty( $font_sizes ) ) {
				$tokens['fontSizes'] = array_values( array_map(
					function ( $s ) {
						return [
							'name' => sanitize_text_field( $s['name'] ?? '' ),
							'slug' => sanitize_text_field( $s['slug'] ?? '' ),
							'size' => sanitize_text_field( (string) ( $s['size'] ?? '' ) ),
						];
					},
					array_slice( $font_sizes, 0, 10 )
				) );
			}
		}

		// Fallback for classic themes: editor-color-palette support.
		if ( empty( $tokens['colors'] ) ) {
			$palette = get_theme_support( 'editor-color-palette' );
			if ( ! empty( $palette[0] ) && is_array( $palette[0] ) ) {
				$tokens['colors'] = array_values( array_map(
					function ( $color ) {
						return [
							'name'  => sanitize_text_field( $color['name'] ?? '' ),
							'slug'  => sanitize_text_field( $color['slug'] ?? '' ),
							'color' => sanitize_text_field( $color['color'] ?? '' ),
						];
					},
					array_slice( $palette[0], 0, 16 )
				) );
			}
		}

		// Fallback: editor-font-sizes support.
		if ( empty( $tokens['fontSizes'] ) ) {
			$sizes = get_theme_support( 'editor-font-sizes' );
			if ( ! empty( $sizes[0] ) && is_array( $sizes[0] ) ) {
				$tokens['fontSizes'] = array_values( array_map(
					function ( $s ) {
						return [
							'name' => sanitize_text_field( $s['name'] ?? '' ),
							'slug' => sanitize_text_field( $s['slug'] ?? '' ),
							'size' => sanitize_text_field( (string) ( $s['size'] ?? '' ) ),
						];
					},
					array_slice( $sizes[0], 0, 10 )
				) );
			}
		}

		return $tokens;
	}

	/**
	 * Get active plugins (name and version, capped at MAX_PLUGINS).
	 *
	 * @since 1.0.0
	 * @return array Array of {name: string, version: string}.
	 */
	private function get_active_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );
		$result         = [];

		foreach ( $active_plugins as $plugin_file ) {
			if ( count( $result ) >= self::MAX_PLUGINS ) {
				break;
			}

			if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
				continue;
			}

			$plugin   = $all_plugins[ $plugin_file ];
			$result[] = [
				'name'    => sanitize_text_field( $plugin['Name'] ),
				'version' => sanitize_text_field( $plugin['Version'] ),
			];
		}

		return $result;
	}

	/**
	 * Get published post counts for public post types.
	 *
	 * @since 1.0.0
	 * @return array<string, int> Post type slug => count.
	 */
	private function get_post_type_counts() {
		$post_types = get_post_types( [ 'public' => true ], 'names' );
		$counts     = [];

		foreach ( $post_types as $post_type ) {
			$count_obj = wp_count_posts( $post_type );
			$counts[ $post_type ] = isset( $count_obj->publish ) ? (int) $count_obj->publish : 0;
		}

		return $counts;
	}

	/**
	 * Get a user's role string.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string Comma-separated roles or 'none'.
	 */
	private function get_user_role( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user || empty( $user->roles ) ) {
			return 'none';
		}

		return sanitize_text_field( implode( ', ', $user->roles ) );
	}

	/**
	 * Get a user's display name.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string Display name or 'Unknown'.
	 */
	private function get_user_name( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return 'Unknown';
		}

		return substr( sanitize_text_field( $user->display_name ? $user->display_name : 'Unknown' ), 0, 60 );
	}

	/**
	 * Get context about the post currently being edited.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID (0 if not in editor).
	 * @return array|null Post context or null if no post.
	 */
	private function get_post_context( $post_id ) {
		$post_id = (int) $post_id;

		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return null;
		}

		return [
			'id'     => $post->ID,
			'title'  => substr( sanitize_text_field( $post->post_title ), 0, 200 ),
			'type'   => sanitize_text_field( $post->post_type ),
			'status' => sanitize_text_field( $post->post_status ),
		];
	}
}
