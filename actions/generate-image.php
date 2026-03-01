<?php
/**
 * Generate Image Action.
 *
 * Generates an AI image from a text prompt using OpenAI DALL-E 3 via
 * the OpenRouter API, then downloads the result and saves it to the
 * WordPress media library. Returns the attachment ID and URL so the
 * image is immediately usable in blocks or as a featured image.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Generate_Image
 *
 * @since 1.0.0
 */
class Generate_Image implements Action_Interface {

	/**
	 * OpenRouter image generation endpoint.
	 *
	 * @var string
	 */
	const IMAGE_ENDPOINT = 'https://openrouter.ai/api/v1/images/generations';

	/**
	 * DALL-E 3 model identifier on OpenRouter.
	 *
	 * @var string
	 */
	const DALLE_MODEL = 'openai/dall-e-3';

	/**
	 * Maximum prompt length in characters (OpenAI DALL-E 3 limit).
	 *
	 * @var int
	 */
	const MAX_PROMPT_LENGTH = 4000;

	/**
	 * Maximum downloaded image size in bytes (10 MB).
	 *
	 * @var int
	 */
	const MAX_IMAGE_SIZE = 10 * 1024 * 1024;

	/**
	 * Allowed image MIME types for the downloaded file.
	 *
	 * @var string[]
	 */
	const ALLOWED_MIME_TYPES = [
		'image/png',
		'image/jpeg',
	];

	/**
	 * Allowed size values.
	 *
	 * @var string[]
	 */
	const ALLOWED_SIZES = [
		'1024x1024',
		'1792x1024',
		'1024x1792',
	];

	/**
	 * Allowed style values.
	 *
	 * @var string[]
	 */
	const ALLOWED_STYLES = [ 'vivid', 'natural' ];

	/**
	 * Allowed quality values.
	 *
	 * @var string[]
	 */
	const ALLOWED_QUALITIES = [ 'standard', 'hd' ];

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'generate_image';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Generate an AI image from a text prompt and save it to the WordPress media library. '
			. 'Uses OpenAI DALL-E 3 via the configured OpenRouter API. '
			. 'Returns the attachment ID and URL ready for use in blocks or as featured images. '
			. 'Use descriptive, detailed prompts for best results.';
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
				'prompt'  => [
					'type'        => 'string',
					'description' => 'Detailed description of the image to generate. The more descriptive, the better the result. Maximum 4000 characters.',
				],
				'size'    => [
					'type'        => 'string',
					'enum'        => self::ALLOWED_SIZES,
					'description' => 'Image dimensions. Use 1792x1024 for landscape (default), 1024x1792 for portrait, 1024x1024 for square.',
				],
				'style'   => [
					'type'        => 'string',
					'enum'        => self::ALLOWED_STYLES,
					'description' => 'Image style. "vivid" produces hyper-real and dramatic images (default). "natural" produces more photorealistic, less dramatic images.',
				],
				'quality' => [
					'type'        => 'string',
					'enum'        => self::ALLOWED_QUALITIES,
					'description' => 'Generation quality. "standard" is faster (default). "hd" produces finer details and more consistency across the image.',
				],
			],
			'required'   => [ 'prompt' ],
		];
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'upload_files';
	}

	/**
	 * Whether this action is reversible.
	 *
	 * The generated attachment can be deleted to undo this action.
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
		// --- 1. Validate and sanitize the prompt --------------------------------------------.
		$prompt = isset( $params['prompt'] ) ? trim( $params['prompt'] ) : '';

		if ( empty( $prompt ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'A prompt is required to generate an image.', 'wp-agent' ),
			];
		}

		if ( mb_strlen( $prompt ) > self::MAX_PROMPT_LENGTH ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %d: maximum allowed characters */
					__( 'Prompt must be %d characters or fewer.', 'wp-agent' ),
					self::MAX_PROMPT_LENGTH
				),
			];
		}

		// Sanitize for safe storage and meta usage; keep full fidelity for the API call.
		$safe_prompt = sanitize_textarea_field( $prompt );

		// --- 2. Validate optional parameters ------------------------------------------------.
		$size    = isset( $params['size'] ) ? sanitize_text_field( $params['size'] ) : '1792x1024';
		$style   = isset( $params['style'] ) ? sanitize_text_field( $params['style'] ) : 'vivid';
		$quality = isset( $params['quality'] ) ? sanitize_text_field( $params['quality'] ) : 'standard';

		if ( ! in_array( $size, self::ALLOWED_SIZES, true ) ) {
			$size = '1792x1024';
		}
		if ( ! in_array( $style, self::ALLOWED_STYLES, true ) ) {
			$style = 'vivid';
		}
		if ( ! in_array( $quality, self::ALLOWED_QUALITIES, true ) ) {
			$quality = 'standard';
		}

		// --- 3. Retrieve and decrypt the API key --------------------------------------------.
		$encrypted = get_option( \WPAgent\AI\Open_Router_Client::API_KEY_OPTION, '' );

		if ( empty( $encrypted ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'OpenRouter API key is not configured. Please add your API key in WP Agent settings.', 'wp-agent' ),
			];
		}

		$api_key = \WPAgent\AI\Open_Router_Client::decrypt_api_key( $encrypted );

		if ( false === $api_key || '' === $api_key ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Failed to decrypt API key. You may need to re-enter it in settings.', 'wp-agent' ),
			];
		}

		// --- 4. Call the OpenRouter image generation endpoint --------------------------------.
		$request_body = wp_json_encode( [
			'model'           => self::DALLE_MODEL,
			'prompt'          => $safe_prompt,
			'size'            => $size,
			'style'           => $style,
			'quality'         => $quality,
			'n'               => 1,
			'response_format' => 'url',
		] );

		$api_response = wp_remote_post(
			self::IMAGE_ENDPOINT,
			[
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
					'HTTP-Referer'  => home_url(),
					'X-Title'       => 'WP Agent',
				],
				'body'    => $request_body,
				'timeout' => 120,
			]
		);

		if ( is_wp_error( $api_response ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Image generation request failed: %s', 'wp-agent' ),
					$api_response->get_error_message()
				),
			];
		}

		$response_code = wp_remote_retrieve_response_code( $api_response );
		$response_body = wp_remote_retrieve_body( $api_response );
		$response_data = json_decode( $response_body, true );

		if ( $response_code >= 400 ) {
			$upstream_message = "HTTP {$response_code}";
			if ( ! empty( $response_data['error']['message'] ) ) {
				$upstream_message = $response_data['error']['message'];
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "WP Agent generate_image error: HTTP {$response_code} — {$upstream_message}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'WP Agent generate_image raw body: ' . substr( $response_body, 0, 1000 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: upstream error message */
					__( 'Image generation failed: %s', 'wp-agent' ),
					$upstream_message
				),
			];
		}

		// --- 5. Extract the image URL from the response -------------------------------------.
		if ( empty( $response_data['data'][0]['url'] ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Image generation succeeded but returned no image URL.', 'wp-agent' ),
			];
		}

		$image_url = esc_url_raw( $response_data['data'][0]['url'] );

		if ( empty( $image_url ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Image generation returned an invalid URL.', 'wp-agent' ),
			];
		}

		// --- 6. Load media handling functions ------------------------------------------------.
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// --- 7. Download the image via wp_safe_remote_get (SSRF-safe) -----------------------.
		$img_response = wp_safe_remote_get(
			$image_url,
			[
				'timeout'             => 60,
				'redirection'         => 3,
				'reject_unsafe_urls'  => true,
				'limit_response_size' => self::MAX_IMAGE_SIZE,
			]
		);

		if ( is_wp_error( $img_response ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Failed to download generated image: %s', 'wp-agent' ),
					$img_response->get_error_message()
				),
			];
		}

		$img_code = wp_remote_retrieve_response_code( $img_response );
		if ( 200 !== $img_code ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Generated image URL returned HTTP %d.', 'wp-agent' ),
					$img_code
				),
			];
		}

		$image_data = wp_remote_retrieve_body( $img_response );
		if ( empty( $image_data ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Downloaded image file is empty.', 'wp-agent' ),
			];
		}

		// --- 8. Write to a temp file ---------------------------------------------------------.
		$temp_file = wp_tempnam( 'wp-agent-dalle-' );
		if ( ! $temp_file ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Could not create a temporary file for the image.', 'wp-agent' ),
			];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $temp_file, $image_data );
		unset( $image_data );

		// --- 9. Validate the downloaded file -------------------------------------------------.
		$file_size = filesize( $temp_file );
		if ( false === $file_size ) {
			@unlink( $temp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Could not read the downloaded image file.', 'wp-agent' ),
			];
		}

		if ( $file_size > self::MAX_IMAGE_SIZE ) {
			@unlink( $temp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: maximum file size */
					__( 'Generated image exceeds maximum file size of %s.', 'wp-agent' ),
					size_format( self::MAX_IMAGE_SIZE )
				),
			];
		}

		// Content-level validation: confirm the file is actually an image.
		$image_info = @getimagesize( $temp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $image_info ) {
			@unlink( $temp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'The downloaded file is not a valid image.', 'wp-agent' ),
			];
		}

		// Determine extension from content-type and WordPress file type check.
		$content_type = wp_remote_retrieve_header( $img_response, 'content-type' );
		$ext          = $this->get_extension_from_content_type( $content_type );

		$file_type = wp_check_filetype_and_ext( $temp_file, 'image.' . $ext );
		$mime_type = ! empty( $file_type['type'] ) ? $file_type['type'] : '';

		if ( ! $mime_type || ! in_array( $mime_type, self::ALLOWED_MIME_TYPES, true ) ) {
			@unlink( $temp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Generated image is not a supported format (PNG or JPEG).', 'wp-agent' ),
			];
		}

		$final_ext = ! empty( $file_type['ext'] ) ? $file_type['ext'] : $ext;

		// --- 10. Sideload into the media library --------------------------------------------.
		$filename   = sanitize_file_name( 'jarvis-' . substr( md5( $safe_prompt ), 0, 8 ) . '.' . $final_ext );
		$file_array = [
			'name'     => $filename,
			'tmp_name' => $temp_file,
		];

		// post_id 0 = unattached; title = sanitized prompt for search discoverability.
		$attachment_id = media_handle_sideload( $file_array, 0, $safe_prompt );

		if ( is_wp_error( $attachment_id ) ) {
			if ( file_exists( $temp_file ) ) {
				@unlink( $temp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Failed to save generated image to media library: %s', 'wp-agent' ),
					$attachment_id->get_error_message()
				),
			];
		}

		// --- 11. Set alt text and retrieve metadata -----------------------------------------.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $safe_prompt );

		$local_url       = wp_get_attachment_url( $attachment_id );
		$attachment_meta = wp_get_attachment_metadata( $attachment_id );

		$data = [
			'attachment_id' => $attachment_id,
			'url'           => esc_url( $local_url ),
			'alt_text'      => $safe_prompt,
			'filename'      => $filename,
			'size'          => $size,
			'style'         => $style,
			'quality'       => $quality,
			'mime'          => $mime_type,
		];

		if ( is_array( $attachment_meta ) && ! empty( $attachment_meta['width'] ) ) {
			$data['width']  = absint( $attachment_meta['width'] );
			$data['height'] = absint( $attachment_meta['height'] );
		}

		return [
			'success' => true,
			'data'    => $data,
			'message' => sprintf(
				/* translators: 1: attachment ID, 2: image URL */
				__( 'Image generated and saved to media library (ID: %1$d). Use the URL in insert_blocks for core/image, core/cover, or set_featured_image.', 'wp-agent' ),
				$attachment_id
			),
		];
	}

	/**
	 * Determine the best file extension from a Content-Type header.
	 *
	 * Falls back to 'png' since DALL-E 3 returns PNG by default.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content_type The Content-Type header value.
	 * @return string File extension without leading dot.
	 */
	private function get_extension_from_content_type( $content_type ) {
		$content_type = strtolower( (string) $content_type );

		if ( false !== strpos( $content_type, 'jpeg' ) || false !== strpos( $content_type, 'jpg' ) ) {
			return 'jpg';
		}

		// Default to png — DALL-E 3 response_format="url" returns PNG.
		return 'png';
	}
}
