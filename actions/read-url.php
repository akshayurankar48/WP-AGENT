<?php
/**
 * Read URL Action.
 *
 * Fetches and extracts content from external URLs for research,
 * competitor analysis, or content reference. Includes SSRF protection
 * to block private/local network addresses.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Read_Url
 *
 * @since 1.0.0
 */
class Read_Url implements Action_Interface {

	/**
	 * Maximum response body size in bytes (512 KB).
	 *
	 * @var int
	 */
	const MAX_BODY_SIZE = 512 * 1024;

	/**
	 * Maximum extracted text length in characters.
	 *
	 * @var int
	 */
	const MAX_TEXT_LENGTH = 10000;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	const REQUEST_TIMEOUT = 15;

	/**
	 * Allowed URL schemes.
	 *
	 * @var string[]
	 */
	const ALLOWED_SCHEMES = [ 'http', 'https' ];

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'read_url';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Fetch and extract content from an external URL. Returns text content, headings, links, and meta tags. '
			. 'Use this for research, reading articles, checking competitor pages, or pulling reference content. '
			. 'Cannot access private/local network URLs.';
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
				'url'     => [
					'type'        => 'string',
					'description' => 'The full URL to fetch (must be http or https).',
				],
				'extract' => [
					'type'        => 'string',
					'enum'        => [ 'text', 'headings', 'links', 'meta', 'all' ],
					'description' => 'What to extract from the page. "text" returns body text, "headings" returns h1-h6, '
						. '"links" returns anchor hrefs, "meta" returns title/description/og tags, "all" returns everything. Defaults to "all".',
				],
			],
			'required'   => [ 'url' ],
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
		$url = $this->validate_url( $params['url'] ?? '' );

		if ( is_wp_error( $url ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => $url->get_error_message(),
			];
		}

		$extract = ! empty( $params['extract'] ) ? sanitize_key( $params['extract'] ) : 'all';
		if ( ! in_array( $extract, [ 'text', 'headings', 'links', 'meta', 'all' ], true ) ) {
			$extract = 'all';
		}

		// Fetch the URL with SSRF protection.
		$response = wp_safe_remote_get( $url, [
			'timeout'             => self::REQUEST_TIMEOUT,
			'redirection'         => 3,
			'reject_unsafe_urls'  => true,
			'limit_response_size' => self::MAX_BODY_SIZE,
			'user-agent'          => 'WP-Agent/1.0 (WordPress)',
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Failed to fetch URL: %s', 'wp-agent' ),
					$response->get_error_message()
				),
			];
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code >= 400 ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %d: HTTP status code */
					__( 'URL returned HTTP %d.', 'wp-agent' ),
					$response_code
				),
			];
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'The URL returned an empty response.', 'wp-agent' ),
			];
		}

		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$is_html      = false !== strpos( $content_type, 'text/html' ) || false !== strpos( $content_type, 'application/xhtml' );

		if ( ! $is_html ) {
			// For non-HTML content, return raw text truncated.
			$text = $this->truncate_text( $body );
			return [
				'success' => true,
				'data'    => [
					'url'          => $url,
					'content_type' => $content_type,
					'text'         => $text,
				],
				'message' => sprintf(
					/* translators: 1: URL, 2: character count */
					__( 'Fetched %1$s (%2$d characters of text content).', 'wp-agent' ),
					$url,
					strlen( $text )
				),
			];
		}

		// Parse HTML and extract requested content.
		$data = $this->extract_content( $body, $extract, $url );

		return [
			'success' => true,
			'data'    => $data,
			'message' => sprintf(
				/* translators: %s: URL */
				__( 'Successfully extracted content from %s.', 'wp-agent' ),
				$url
			),
		];
	}

	/**
	 * Extract content from HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html    Raw HTML.
	 * @param string $extract What to extract.
	 * @param string $url     Source URL.
	 * @return array Extracted content.
	 */
	private function extract_content( $html, $extract, $url ) {
		$data = [ 'url' => $url ];

		// Extract meta tags (always useful as context).
		if ( 'meta' === $extract || 'all' === $extract ) {
			$data['meta'] = $this->extract_meta( $html );
		}

		// Extract headings.
		if ( 'headings' === $extract || 'all' === $extract ) {
			$data['headings'] = $this->extract_headings( $html );
		}

		// Extract links.
		if ( 'links' === $extract || 'all' === $extract ) {
			$data['links'] = $this->extract_links( $html, $url );
		}

		// Extract body text.
		if ( 'text' === $extract || 'all' === $extract ) {
			$data['text'] = $this->extract_text( $html );
		}

		return $data;
	}

	/**
	 * Extract meta tags from HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Raw HTML.
	 * @return array Meta tag values.
	 */
	private function extract_meta( $html ) {
		$meta = [];

		// Title tag.
		if ( preg_match( '/<title[^>]*>([^<]+)<\/title>/i', $html, $matches ) ) {
			$meta['title'] = trim( wp_strip_all_tags( $matches[1] ) );
		}

		// Meta description.
		if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches ) ) {
			$meta['description'] = trim( $matches[1] );
		}

		// Open Graph tags.
		$og_tags = [ 'og:title', 'og:description', 'og:image', 'og:type', 'og:url' ];
		foreach ( $og_tags as $tag ) {
			$escaped = preg_quote( $tag, '/' );
			if ( preg_match( '/<meta[^>]+property=["\']' . $escaped . '["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches ) ) {
				$key          = str_replace( 'og:', 'og_', $tag );
				$meta[ $key ] = trim( $matches[1] );
			}
		}

		return $meta;
	}

	/**
	 * Extract headings from HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Raw HTML.
	 * @return array Headings with level and text.
	 */
	private function extract_headings( $html ) {
		$headings = [];

		if ( preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( array_slice( $matches, 0, 30 ) as $match ) {
				$headings[] = [
					'level' => (int) $match[1],
					'text'  => trim( wp_strip_all_tags( $match[2] ) ),
				];
			}
		}

		return $headings;
	}

	/**
	 * Extract links from HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Raw HTML.
	 * @param string $base_url Base URL for resolving relative links.
	 * @return array Links with href and text.
	 */
	private function extract_links( $html, $base_url ) {
		$links = [];

		// Remove nav, header, footer to focus on content links.
		$content_html = preg_replace( '/<(nav|header|footer)[^>]*>.*?<\/\1>/is', '', $html );

		if ( preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content_html, $matches, PREG_SET_ORDER ) ) {
			foreach ( array_slice( $matches, 0, 30 ) as $match ) {
				$href = trim( $match[1] );
				$text = trim( wp_strip_all_tags( $match[2] ) );

				// Skip empty, anchor-only, and javascript links.
				if ( empty( $href ) || '#' === $href[0] || 0 === strpos( $href, 'javascript:' ) ) {
					continue;
				}

				// Resolve relative URLs.
				if ( 0 !== strpos( $href, 'http' ) ) {
					$href = trailingslashit( $base_url ) . ltrim( $href, '/' );
				}

				if ( $text ) {
					$links[] = [
						'href' => esc_url( $href ),
						'text' => substr( $text, 0, 100 ),
					];
				}
			}
		}

		return $links;
	}

	/**
	 * Extract body text from HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Raw HTML.
	 * @return string Cleaned body text.
	 */
	private function extract_text( $html ) {
		// Isolate body content.
		if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $html, $matches ) ) {
			$html = $matches[1];
		}

		// Remove script, style, nav, header, footer tags and their content.
		$html = preg_replace( '/<(script|style|nav|header|footer|aside|noscript)[^>]*>.*?<\/\1>/is', '', $html );

		// Remove all remaining HTML tags.
		$text = wp_strip_all_tags( $html );

		// Normalize whitespace.
		$text = preg_replace( '/[\t ]+/', ' ', $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );
		$text = trim( $text );

		return $this->truncate_text( $text );
	}

	/**
	 * Truncate text to the maximum allowed length.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Raw text.
	 * @return string Truncated text.
	 */
	private function truncate_text( $text ) {
		if ( function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $text, 'UTF-8' ) > self::MAX_TEXT_LENGTH ) {
				return mb_substr( $text, 0, self::MAX_TEXT_LENGTH, 'UTF-8' ) . "\n\n[Content truncated at " . number_format( self::MAX_TEXT_LENGTH ) . ' characters]';
			}
		} elseif ( strlen( $text ) > self::MAX_TEXT_LENGTH ) {
			return substr( $text, 0, self::MAX_TEXT_LENGTH ) . "\n\n[Content truncated at " . number_format( self::MAX_TEXT_LENGTH ) . ' characters]';
		}

		return $text;
	}

	/**
	 * Validate and sanitize the URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Raw URL input.
	 * @return string|\WP_Error Sanitized URL or error.
	 */
	private function validate_url( $url ) {
		if ( empty( $url ) ) {
			return new \WP_Error( 'missing_url', __( 'URL is required.', 'wp-agent' ) );
		}

		$url = esc_url_raw( trim( $url ) );

		if ( empty( $url ) ) {
			return new \WP_Error( 'invalid_url', __( 'The provided URL is not valid.', 'wp-agent' ) );
		}

		// Validate scheme.
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, self::ALLOWED_SCHEMES, true ) ) {
			return new \WP_Error( 'invalid_scheme', __( 'Only http and https URLs are allowed.', 'wp-agent' ) );
		}

		// Block localhost and private IPs (SSRF protection).
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return new \WP_Error( 'invalid_host', __( 'The URL must contain a valid hostname.', 'wp-agent' ) );
		}

		$ip = gethostbyname( $host );
		if ( $ip && $this->is_private_ip( $ip ) ) {
			return new \WP_Error( 'blocked_host', __( 'Cannot access private or local network addresses.', 'wp-agent' ) );
		}

		return $url;
	}

	/**
	 * Check if an IP address is private/reserved.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ip IP address.
	 * @return bool True if the IP is private or reserved.
	 */
	private function is_private_ip( $ip ) {
		return ! filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}
}
