<?php
/**
 * Manage SEO Action.
 *
 * Gets or updates SEO meta fields (title, description, Open Graph,
 * robots, canonical URL) on a post. Auto-detects installed SEO plugins
 * (Yoast, AIOSEO, SEO Framework, Rank Math) or falls back to native
 * post meta with wp_head output.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Manage_Seo
 *
 * @since 1.0.0
 */
class Manage_Seo implements Action_Interface {

	/**
	 * SEO fields we manage.
	 *
	 * @var string[]
	 */
	const SEO_FIELDS = [
		'meta_title',
		'meta_description',
		'og_title',
		'og_description',
		'og_image',
		'canonical_url',
		'robots',
		'focus_keyword',
	];

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'manage_seo';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Get or update SEO metadata for a post/page. Supports meta title, meta description, '
			. 'Open Graph (og:title, og:description, og:image), canonical URL, robots directives, '
			. 'and focus keyword. Auto-detects Yoast SEO, AIOSEO, Rank Math, or SEO Framework. '
			. 'Falls back to native meta if no SEO plugin is active.';
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
				'operation' => [
					'type'        => 'string',
					'enum'        => [ 'get', 'update' ],
					'description' => 'Operation to perform: "get" reads current SEO values, "update" sets new values.',
				],
				'post_id'   => [
					'type'        => 'integer',
					'description' => 'The post ID to manage SEO for.',
				],
				'fields'    => [
					'type'        => 'object',
					'description' => 'SEO fields to update. Keys: meta_title, meta_description, og_title, og_description, og_image, canonical_url, robots, focus_keyword.',
					'properties'  => [
						'meta_title'       => [ 'type' => 'string', 'description' => 'SEO title tag (50-60 characters recommended).' ],
						'meta_description' => [ 'type' => 'string', 'description' => 'Meta description (120-160 characters recommended).' ],
						'og_title'         => [ 'type' => 'string', 'description' => 'Open Graph title for social sharing.' ],
						'og_description'   => [ 'type' => 'string', 'description' => 'Open Graph description for social sharing.' ],
						'og_image'         => [ 'type' => 'string', 'description' => 'Open Graph image URL for social sharing.' ],
						'canonical_url'    => [ 'type' => 'string', 'description' => 'Canonical URL to prevent duplicate content.' ],
						'robots'           => [ 'type' => 'string', 'description' => 'Robots directive (e.g. "index, follow" or "noindex, nofollow").' ],
						'focus_keyword'    => [ 'type' => 'string', 'description' => 'Primary keyword this page targets.' ],
					],
				],
			],
			'required'   => [ 'operation', 'post_id' ],
		];
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'edit_posts';
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
		$operation = $params['operation'] ?? '';
		$post_id   = isset( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Invalid or missing post ID.', 'wp-agent' ),
			];
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'You do not have permission to edit this post.', 'wp-agent' ),
			];
		}

		$provider = $this->detect_seo_provider();

		if ( 'get' === $operation ) {
			return $this->get_seo( $post_id, $provider );
		}

		if ( 'update' === $operation ) {
			$fields = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : [];
			if ( empty( $fields ) ) {
				return [
					'success' => false,
					'data'    => null,
					'message' => __( 'No SEO fields provided to update.', 'wp-agent' ),
				];
			}
			return $this->update_seo( $post_id, $fields, $provider );
		}

		return [
			'success' => false,
			'data'    => null,
			'message' => __( 'Invalid operation. Use "get" or "update".', 'wp-agent' ),
		];
	}

	/**
	 * Detect which SEO plugin is active.
	 *
	 * @since 1.0.0
	 * @return string Provider key: yoast, aioseo, seo_framework, rank_math, or native.
	 */
	private function detect_seo_provider() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			return 'aioseo';
		}
		if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
			return 'seo_framework';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'rank_math';
		}
		return 'native';
	}

	/**
	 * Get SEO meta for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $provider SEO provider key.
	 * @return array Execution result.
	 */
	private function get_seo( $post_id, $provider ) {
		$meta_map = $this->get_meta_keys( $provider );
		$data     = [ 'provider' => $provider ];

		foreach ( self::SEO_FIELDS as $field ) {
			if ( ! empty( $meta_map[ $field ] ) ) {
				$value = get_post_meta( $post_id, $meta_map[ $field ], true );
				$data[ $field ] = is_string( $value ) ? $value : '';
			} else {
				$data[ $field ] = '';
			}
		}

		return [
			'success' => true,
			'data'    => $data,
			'message' => sprintf(
				/* translators: 1: post ID, 2: SEO provider */
				__( 'SEO metadata for post #%1$d (provider: %2$s).', 'wp-agent' ),
				$post_id,
				$provider
			),
		];
	}

	/**
	 * Update SEO meta for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id  Post ID.
	 * @param array  $fields   Fields to update.
	 * @param string $provider SEO provider key.
	 * @return array Execution result.
	 */
	private function update_seo( $post_id, array $fields, $provider ) {
		$meta_map = $this->get_meta_keys( $provider );
		$updated  = [];

		foreach ( $fields as $field => $value ) {
			if ( ! in_array( $field, self::SEO_FIELDS, true ) ) {
				continue;
			}

			if ( empty( $meta_map[ $field ] ) ) {
				continue;
			}

			$sanitized = $this->sanitize_field( $field, $value );
			update_post_meta( $post_id, $meta_map[ $field ], $sanitized );
			$updated[ $field ] = $sanitized;
		}

		if ( empty( $updated ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'No valid SEO fields were updated.', 'wp-agent' ),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'post_id'  => $post_id,
				'provider' => $provider,
				'updated'  => $updated,
			],
			'message' => sprintf(
				/* translators: 1: field count, 2: post ID */
				__( 'Updated %1$d SEO field(s) on post #%2$d.', 'wp-agent' ),
				count( $updated ),
				$post_id
			),
		];
	}

	/**
	 * Get meta key mapping for each provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider SEO provider key.
	 * @return array<string, string> Field-to-meta-key mapping.
	 */
	private function get_meta_keys( $provider ) {
		switch ( $provider ) {
			case 'yoast':
				return [
					'meta_title'       => '_yoast_wpseo_title',
					'meta_description' => '_yoast_wpseo_metadesc',
					'og_title'         => '_yoast_wpseo_opengraph-title',
					'og_description'   => '_yoast_wpseo_opengraph-description',
					'og_image'         => '_yoast_wpseo_opengraph-image',
					'canonical_url'    => '_yoast_wpseo_canonical',
					'robots'           => '_yoast_wpseo_meta-robots-noindex',
					'focus_keyword'    => '_yoast_wpseo_focuskw',
				];

			case 'aioseo':
				return [
					'meta_title'       => '_aioseo_title',
					'meta_description' => '_aioseo_description',
					'og_title'         => '_aioseo_og_title',
					'og_description'   => '_aioseo_og_description',
					'og_image'         => '_aioseo_og_image_url',
					'canonical_url'    => '_aioseo_canonical_url',
					'robots'           => '_aioseo_robots_noindex',
					'focus_keyword'    => '_aioseo_keyphrases',
				];

			case 'rank_math':
				return [
					'meta_title'       => 'rank_math_title',
					'meta_description' => 'rank_math_description',
					'og_title'         => 'rank_math_facebook_title',
					'og_description'   => 'rank_math_facebook_description',
					'og_image'         => 'rank_math_facebook_image',
					'canonical_url'    => 'rank_math_canonical_url',
					'robots'           => 'rank_math_robots',
					'focus_keyword'    => 'rank_math_focus_keyword',
				];

			case 'seo_framework':
				return [
					'meta_title'       => '_genesis_title',
					'meta_description' => '_genesis_description',
					'og_title'         => '_open_graph_title',
					'og_description'   => '_open_graph_description',
					'og_image'         => '_social_image_url',
					'canonical_url'    => '_genesis_canonical_uri',
					'robots'           => '_genesis_noindex',
					'focus_keyword'    => '',
				];

			default: // native
				return [
					'meta_title'       => '_wp_agent_meta_title',
					'meta_description' => '_wp_agent_meta_description',
					'og_title'         => '_wp_agent_og_title',
					'og_description'   => '_wp_agent_og_description',
					'og_image'         => '_wp_agent_og_image',
					'canonical_url'    => '_wp_agent_canonical_url',
					'robots'           => '_wp_agent_robots',
					'focus_keyword'    => '_wp_agent_focus_keyword',
				];
		}
	}

	/**
	 * Sanitize an SEO field value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Raw value.
	 * @return string Sanitized value.
	 */
	private function sanitize_field( $field, $value ) {
		$value = (string) $value;

		switch ( $field ) {
			case 'og_image':
			case 'canonical_url':
				return esc_url_raw( $value );

			case 'meta_description':
			case 'og_description':
				return sanitize_textarea_field( substr( $value, 0, 320 ) );

			default:
				return sanitize_text_field( substr( $value, 0, 200 ) );
		}
	}
}
