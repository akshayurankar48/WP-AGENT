<?php
/**
 * Screenshot Page Action.
 *
 * Captures a screenshot of any page on the site using the WordPress.com
 * mShots service, saves the result to the media library, and returns the
 * attachment ID and URL so the AI can self-critique and iterate on designs.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Screenshot_Page
 *
 * @since 1.0.0
 */
class Screenshot_Page implements Action_Interface {

	/**
	 * mShots service base URL.
	 *
	 * @var string
	 */
	const MSHOTS_BASE = 'https://s0.wp.com/mshots/v1/';

	/**
	 * Number of attempts to wait for a real screenshot.
	 *
	 * @var int
	 */
	const MAX_ATTEMPTS = 3;

	/**
	 * Seconds to wait between mShots polling attempts.
	 *
	 * @var int
	 */
	const RETRY_DELAY = 3;

	/**
	 * Minimum body size in bytes to treat a response as a real screenshot.
	 * mShots placeholders are tiny GIFs; real screenshots are much larger.
	 *
	 * @var int
	 */
	const MIN_SCREENSHOT_SIZE = 5120;

	/**
	 * Default viewport width in pixels.
	 *
	 * @var int
	 */
	const DEFAULT_WIDTH = 1280;

	/**
	 * Default viewport height in pixels.
	 *
	 * @var int
	 */
	const DEFAULT_HEIGHT = 960;

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'screenshot_page';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Capture a screenshot of any page on the site using the WordPress.com mShots service. '
			. 'Use this after building a page to SEE what it looks like, self-critique the design, and iterate. '
			. 'The screenshot is saved to the media library. '
			. 'Note: requires the page URL to be publicly accessible (does not work for localhost/private sites '
			. '— use the preview URL instead in those cases).';
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
				'url'             => [
					'type'        => 'string',
					'description' => 'Full URL to screenshot. If not provided, post_id is used to get the permalink. At least one of url or post_id is required.',
				],
				'post_id'         => [
					'type'        => 'integer',
					'description' => 'Post or page ID to screenshot. The permalink is resolved automatically. At least one of url or post_id is required.',
				],
				'viewport_width'  => [
					'type'        => 'integer',
					'description' => 'Viewport width in pixels (320–1920). Defaults to 1280.',
				],
				'viewport_height' => [
					'type'        => 'integer',
					'description' => 'Viewport height in pixels (480–1440). Defaults to 960.',
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
		// Resolve and validate URL.
		$url_result = $this->resolve_url( $params );
		if ( isset( $url_result['success'] ) && false === $url_result['success'] ) {
			return $url_result;
		}
		$url = $url_result;

		// Clamp viewport dimensions.
		$viewport_width  = isset( $params['viewport_width'] )
			? max( 320, min( 1920, absint( $params['viewport_width'] ) ) )
			: self::DEFAULT_WIDTH;
		$viewport_height = isset( $params['viewport_height'] )
			? max( 480, min( 1440, absint( $params['viewport_height'] ) ) )
			: self::DEFAULT_HEIGHT;

		// Bail early for local/private URLs with a helpful message.
		if ( $this->is_local_url( $url ) ) {
			return [
				'success' => true,
				'data'    => [
					'preview_url' => $url,
					'screenshot'  => null,
					'note'        => 'Site is on a local/private URL. Screenshot service requires a public URL. Open the preview URL in your browser to see the page.',
				],
				'message' => 'Cannot screenshot local URLs. Preview URL: ' . $url,
			];
		}

		// Build mShots request URL.
		$mshots_url = sprintf(
			'%s%s?w=%d&h=%d',
			self::MSHOTS_BASE,
			rawurlencode( $url ),
			$viewport_width,
			$viewport_height
		);

		// Poll mShots until we receive a real screenshot (not a placeholder).
		$body = $this->fetch_screenshot( $mshots_url );

		if ( is_wp_error( $body ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => $body->get_error_message(),
			];
		}

		// mShots never returned a full image; give the user the direct URL.
		if ( null === $body ) {
			return [
				'success' => true,
				'data'    => [
					'screenshot_url' => $mshots_url,
					'page_url'       => $url,
					'viewport'       => $viewport_width . 'x' . $viewport_height,
					'note'           => 'mShots is still processing. Open the screenshot_url directly to view it.',
				],
				'message' => 'Screenshot is still being generated. Check the URL manually: ' . $mshots_url,
			];
		}

		// Save the image body to the media library.
		return $this->save_to_media_library( $body, $url, $viewport_width, $viewport_height );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolve the target URL from params.
	 *
	 * Returns the URL string on success, or an error result array on failure.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Action parameters.
	 * @return string|array URL string, or error result array.
	 */
	private function resolve_url( array $params ) {
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );

		// Prefer post_id when provided.
		if ( ! empty( $params['post_id'] ) ) {
			$post_id = absint( $params['post_id'] );
			$post    = get_post( $post_id );

			if ( ! $post ) {
				return [
					'success' => false,
					'data'    => null,
					'message' => sprintf(
						/* translators: %d: post ID */
						__( 'Post #%d not found.', 'wp-agent' ),
						$post_id
					),
				];
			}

			$url = get_permalink( $post_id );
			if ( ! $url ) {
				return [
					'success' => false,
					'data'    => null,
					'message' => sprintf(
						/* translators: %d: post ID */
						__( 'Could not resolve permalink for post #%d.', 'wp-agent' ),
						$post_id
					),
				];
			}

			return $url;
		}

		// Fall back to explicit URL.
		if ( ! empty( $params['url'] ) ) {
			$url = esc_url_raw( trim( $params['url'] ) );

			if ( ! wp_http_validate_url( $url ) ) {
				return [
					'success' => false,
					'data'    => null,
					'message' => __( 'The provided URL is not valid.', 'wp-agent' ),
				];
			}

			// SSRF protection: only allow same-domain URLs.
			$url_host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $url_host !== $site_host ) {
				return [
					'success' => false,
					'data'    => null,
					'message' => sprintf(
						/* translators: %s: allowed hostname */
						__( 'Only URLs on this site (%s) can be screenshotted.', 'wp-agent' ),
						$site_host
					),
				];
			}

			return $url;
		}

		return [
			'success' => false,
			'data'    => null,
			'message' => __( 'Provide either url or post_id.', 'wp-agent' ),
		];
	}

	/**
	 * Check whether a URL points to a local or private network host.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to check.
	 * @return bool True if the host is local/private.
	 */
	private function is_local_url( $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );

		$local_patterns = [ 'localhost', '127.0.0.1', '.local', '.test' ];
		foreach ( $local_patterns as $pattern ) {
			if ( false !== strpos( $host, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Poll the mShots service until a full screenshot is returned or attempts are exhausted.
	 *
	 * Returns the raw image body on success, null if only a placeholder was received after
	 * all attempts, or a WP_Error on a hard failure.
	 *
	 * @since 1.0.0
	 *
	 * @param string $mshots_url Full mShots request URL.
	 * @return string|null|\WP_Error Image body, null (placeholder), or WP_Error.
	 */
	private function fetch_screenshot( $mshots_url ) {
		for ( $attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++ ) {
			// Sleep before every attempt except the first.
			if ( $attempt > 1 ) {
				sleep( self::RETRY_DELAY );
			}

			$response = wp_safe_remote_get( $mshots_url, [ 'timeout' => 30 ] );

			if ( is_wp_error( $response ) ) {
				return new \WP_Error(
					'mshots_request_failed',
					sprintf(
						/* translators: %s: error message */
						__( 'mShots request failed: %s', 'wp-agent' ),
						$response->get_error_message()
					)
				);
			}

			$content_type = wp_remote_retrieve_header( $response, 'content-type' );
			$body         = wp_remote_retrieve_body( $response );

			$is_image = (
				false !== strpos( $content_type, 'image/jpeg' ) ||
				false !== strpos( $content_type, 'image/png' )
			);

			if ( $is_image && strlen( $body ) > self::MIN_SCREENSHOT_SIZE ) {
				return $body;
			}
		}

		// All attempts yielded a placeholder.
		return null;
	}

	/**
	 * Write an image body to a temp file and sideload it into the media library.
	 *
	 * @since 1.0.0
	 *
	 * @param string $body            Raw image body.
	 * @param string $url             The page URL that was screenshotted.
	 * @param int    $viewport_width  Viewport width used.
	 * @param int    $viewport_height Viewport height used.
	 * @return array Execution result.
	 */
	private function save_to_media_library( $body, $url, $viewport_width, $viewport_height ) {
		// Require media handling functions.
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp_file = wp_tempnam( 'wp-agent-screenshot-' );
		if ( ! $tmp_file ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Could not create temporary file.', 'wp-agent' ),
			];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $tmp_file, $body );
		unset( $body );

		// Content-level validation: confirm the file is a real image.
		$image_info = @getimagesize( $tmp_file );
		if ( false === $image_info ) {
			@unlink( $tmp_file );
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'The screenshot response is not a valid image.', 'wp-agent' ),
			];
		}

		$host      = (string) wp_parse_url( $url, PHP_URL_HOST );
		$filename  = sanitize_file_name( 'screenshot-' . $host . '-' . time() . '.jpg' );

		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp_file,
		];

		$attachment_id = media_handle_sideload(
			$file_array,
			0,
			/* translators: %s: page URL */
			sprintf( __( 'Screenshot of %s', 'wp-agent' ), esc_url( $url ) )
		);

		if ( is_wp_error( $attachment_id ) ) {
			if ( file_exists( $tmp_file ) ) {
				@unlink( $tmp_file );
			}
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Failed to save screenshot to media library: %s', 'wp-agent' ),
					$attachment_id->get_error_message()
				),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'attachment_id'  => $attachment_id,
				'screenshot_url' => wp_get_attachment_url( $attachment_id ),
				'page_url'       => $url,
				'viewport'       => $viewport_width . 'x' . $viewport_height,
			],
			'message' => __( 'Screenshot captured and saved to media library.', 'wp-agent' ),
		];
	}
}
