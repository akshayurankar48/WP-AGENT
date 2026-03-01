<?php
/**
 * OpenRouter Client.
 *
 * Core AI client with streaming (cURL WRITEFUNCTION) and non-streaming
 * (wp_remote_post) transports. Handles API key encryption, SSE parsing,
 * and response normalization.
 *
 * @package WPAgent\AI
 * @since   1.0.0
 */

namespace WPAgent\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class Open_Router_Client
 *
 * @since 1.0.0
 */
class Open_Router_Client {

	/**
	 * Instance
	 *
	 * @access private
	 * @var Open_Router_Client|null Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * OpenRouter API endpoint.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

	/**
	 * OpenRouter models endpoint (for API key validation).
	 *
	 * @var string
	 */
	const MODELS_ENDPOINT = 'https://openrouter.ai/api/v1/models';

	/**
	 * Option key for the encrypted API key.
	 *
	 * @var string
	 */
	const API_KEY_OPTION = 'wp_agent_openrouter_api_key';

	/**
	 * Encryption cipher.
	 *
	 * @var string
	 */
	const CIPHER = 'aes-256-cbc';

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return Open_Router_Client Initialized object of class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Send a streaming chat completion request.
	 *
	 * Uses cURL with CURLOPT_WRITEFUNCTION for real-time SSE processing.
	 * wp_remote_post does not support streaming callbacks.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $messages    Formatted messages array.
	 * @param string   $model       OpenRouter model ID.
	 * @param array    $tools       Tool definitions (optional).
	 * @param callable $callback    Callback receiving typed chunks:
	 *                              {type: 'content'|'tool_call'|'error'|'finish'|'done', ...}.
	 * @param float    $temperature Sampling temperature (0.0–2.0). Lower = more deterministic.
	 * @param int      $max_tokens  Maximum tokens in the response.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function stream( array $messages, $model, array $tools = [], callable $callback = null, $temperature = 0.7, $max_tokens = 4096 ) {
		$api_key = $this->get_api_key();

		if ( is_wp_error( $api_key ) ) {
			return $api_key;
		}

		$body = [
			'model'                => $model,
			'messages'             => $messages,
			'stream'               => true,
			'max_tokens'           => (int) $max_tokens,
			'temperature'          => (float) $temperature,
			'parallel_tool_calls'  => false,
			'provider'             => [
				'allow_fallbacks'    => true,
				'data_collection'    => 'deny',
			],
		];

		if ( ! empty( $tools ) ) {
			$body['tools'] = $tools;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$tool_count   = count( $tools );
			$msg_count    = count( $messages );
			$system_len   = ! empty( $messages[0]['content'] ) ? strlen( $messages[0]['content'] ) : 0;
			error_log( "WP Agent stream: model={$model}, tools={$tool_count}, messages={$msg_count}, system_prompt_chars={$system_len}, temperature={$temperature}, max_tokens={$max_tokens}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		$headers    = $this->get_request_headers( $api_key );
		$buffer     = '';
		$raw_body   = '';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- cURL required for streaming; wp_remote_post does not support WRITEFUNCTION callbacks.
		$ch = curl_init();

		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt -- cURL required for streaming.
		curl_setopt( $ch, CURLOPT_URL, self::API_ENDPOINT );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $this->format_headers_for_curl( $headers ) );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 120 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( $ch, CURLOPT_TCP_KEEPALIVE, 1 );
		curl_setopt( $ch, CURLOPT_TCP_KEEPIDLE, 30 );
		curl_setopt( $ch, CURLOPT_TCP_KEEPINTVL, 15 );

		// SSL verification — respect WP's CA bundle.
		$ca_bundle = ABSPATH . WPINC . '/certificates/ca-bundle.crt';
		if ( file_exists( $ca_bundle ) ) {
			curl_setopt( $ch, CURLOPT_CAINFO, $ca_bundle );
		}

		// Streaming callback — processes SSE data as it arrives.
		curl_setopt(
			$ch,
			CURLOPT_WRITEFUNCTION,
			function ( $ch, $data ) use ( &$buffer, &$raw_body, $callback ) {
				$buffer   .= $data;
				$raw_body .= $data;

				// Process complete SSE lines from the buffer.
				while ( false !== ( $newline_pos = strpos( $buffer, "\n" ) ) ) {
					$line   = substr( $buffer, 0, $newline_pos );
					$buffer = substr( $buffer, $newline_pos + 1 );
					$line   = trim( $line );

					if ( '' === $line ) {
						continue;
					}

					// SSE comments (e.g. ": OPENROUTER PROCESSING") — log in debug mode.
					if ( 0 === strpos( $line, ':' ) ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'WP Agent SSE comment: ' . trim( substr( $line, 1 ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						}
						continue;
					}

					// SSE data lines start with "data: ".
					if ( 0 !== strpos( $line, 'data: ' ) ) {
						continue;
					}

					$json_str = substr( $line, 6 );

					// Stream termination signal.
					if ( '[DONE]' === $json_str ) {
						if ( $callback ) {
							call_user_func( $callback, [ 'type' => 'done' ] );
						}
						continue;
					}

					$parsed = json_decode( $json_str, true );
					if ( null === $parsed ) {
						continue;
					}

					if ( $callback ) {
						$chunks = $this->parse_stream_chunk( $parsed );
						foreach ( $chunks as $chunk ) {
							call_user_func( $callback, $chunk );
						}
					}
				}

				return strlen( $data );
			}
		);
		// phpcs:enable

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec -- cURL required for streaming.
		$result = curl_exec( $ch );

		// Flush any remaining data in the buffer (last chunk may lack trailing newline).
		if ( $callback && ! empty( trim( $buffer ) ) ) {
			$line = trim( $buffer );
			if ( 0 === strpos( $line, 'data: ' ) ) {
				$json_str = substr( $line, 6 );
				if ( '[DONE]' !== $json_str ) {
					$parsed = json_decode( $json_str, true );
					if ( null !== $parsed ) {
						$chunks = $this->parse_stream_chunk( $parsed );
						foreach ( $chunks as $chunk ) {
							call_user_func( $callback, $chunk );
						}
					}
				}
			}
		}

		if ( false === $result ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error -- cURL required for streaming.
			$error = curl_error( $ch );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close -- cURL required for streaming.
			curl_close( $ch );

			return new \WP_Error( 'stream_error', $error );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo -- cURL required for streaming.
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close -- cURL required for streaming.
		curl_close( $ch );

		if ( $http_code >= 400 ) {
			// Try to extract the actual error message from the raw response body.
			$upstream_message = "HTTP {$http_code}";
			$raw_decoded      = json_decode( trim( $raw_body ), true );
			if ( ! empty( $raw_decoded['error']['message'] ) ) {
				$upstream_message = $raw_decoded['error']['message'];
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "WP Agent stream error: HTTP {$http_code} — {$upstream_message}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'WP Agent stream raw body: ' . substr( $raw_body, 0, 1000 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %s: Error message from OpenRouter */
					__( 'AI request failed: %s', 'wp-agent' ),
					$upstream_message
				),
				[ 'status' => $http_code ]
			);
		}

		// Log success details when debugging.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$body_len = strlen( $raw_body );
			error_log( "WP Agent stream complete: HTTP {$http_code}, body_length={$body_len}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return true;
	}

	/**
	 * Send a non-streaming chat completion request.
	 *
	 * Uses wp_remote_post for standard WordPress HTTP handling
	 * (respects proxy settings and SSL configuration).
	 *
	 * @since 1.0.0
	 *
	 * @param array  $messages    Formatted messages array.
	 * @param string $model       OpenRouter model ID.
	 * @param array  $tools       Tool definitions (optional).
	 * @param float  $temperature Sampling temperature (0.0–2.0). Lower = more deterministic.
	 * @param int    $max_tokens  Maximum tokens in the response.
	 * @return array|\WP_Error Parsed response or WP_Error.
	 *     @type string $content       Response text content.
	 *     @type array  $tool_calls    Tool calls from the model (if any).
	 *     @type string $model         Model ID that was used.
	 *     @type array  $usage         Token usage {prompt_tokens, completion_tokens, total_tokens}.
	 *     @type string $finish_reason Why the model stopped.
	 */
	public function chat( array $messages, $model, array $tools = [], $temperature = 0.7, $max_tokens = 4096 ) {
		$api_key = $this->get_api_key();

		if ( is_wp_error( $api_key ) ) {
			return $api_key;
		}

		$body = [
			'model'                => $model,
			'messages'             => $messages,
			'stream'               => false,
			'max_tokens'           => (int) $max_tokens,
			'temperature'          => (float) $temperature,
			'parallel_tool_calls'  => false,
			'provider'             => [
				'allow_fallbacks'    => true,
				'data_collection'    => 'deny',
			],
		];

		if ( ! empty( $tools ) ) {
			$body['tools'] = $tools;
		}

		$response = wp_remote_post(
			self::API_ENDPOINT,
			[
				'headers' => $this->get_request_headers( $api_key ),
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			$upstream_message = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP {$code}";
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "WP Agent chat error: HTTP {$code} — {$upstream_message}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'WP Agent chat raw body: ' . substr( $body, 0, 1000 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %s: Error message from the AI provider */
					__( 'AI request failed: %s', 'wp-agent' ),
					$upstream_message
				),
				[ 'status' => $code ]
			);
		}

		if ( null === $data || empty( $data['choices'] ) ) {
			return new \WP_Error( 'invalid_response', __( 'Invalid response from OpenRouter API.', 'wp-agent' ) );
		}

		$choice = $data['choices'][0];

		return [
			'content'       => isset( $choice['message']['content'] ) ? $choice['message']['content'] : '',
			'tool_calls'    => isset( $choice['message']['tool_calls'] ) ? $choice['message']['tool_calls'] : [],
			'model'         => isset( $data['model'] ) ? $data['model'] : $model,
			'usage'         => isset( $data['usage'] ) ? $data['usage'] : [],
			'finish_reason' => isset( $choice['finish_reason'] ) ? $choice['finish_reason'] : '',
		];
	}

	/**
	 * Validate an OpenRouter API key by making a test request to /models.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The API key to validate.
	 * @return true|\WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_api_key( $key ) {
		$key = trim( $key );

		if ( empty( $key ) ) {
			return new \WP_Error( 'empty_api_key', __( 'API key cannot be empty.', 'wp-agent' ) );
		}

		$response = wp_remote_get(
			self::MODELS_ENDPOINT,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $key,
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error( 'invalid_api_key', __( 'The API key is invalid or has been revoked.', 'wp-agent' ) );
		}

		if ( $code >= 400 ) {
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'OpenRouter API returned HTTP %d during validation.', 'wp-agent' ),
					$code
				)
			);
		}

		return true;
	}

	/**
	 * Check if OpenSSL encryption is available.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_encryption_available() {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' );
	}

	/**
	 * Encrypt an API key for storage.
	 *
	 * Uses AES-256-CBC with a SHA-256 hash of wp_salt('auth') as the key
	 * and a random IV prepended to the ciphertext.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The plaintext API key.
	 * @return string|false Base64-encoded (IV + ciphertext), or false on failure.
	 */
	public static function encrypt_api_key( $key ) {
		if ( ! self::is_encryption_available() ) {
			return false;
		}

		$encryption_key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv             = openssl_random_pseudo_bytes( 16 );

		$encrypted = openssl_encrypt( $key, self::CIPHER, $encryption_key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return false;
		}

		// Prepend IV to ciphertext so decrypt can extract it.
		return base64_encode( $iv . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored API key.
	 *
	 * Extracts the 16-byte IV from the front of the stored value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $stored The base64-encoded (IV + ciphertext).
	 * @return string|false The plaintext API key, or false on failure.
	 */
	public static function decrypt_api_key( $stored ) {
		if ( ! self::is_encryption_available() ) {
			return false;
		}

		$raw = base64_decode( $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $raw || strlen( $raw ) < 17 ) {
			return false;
		}

		$iv             = substr( $raw, 0, 16 );
		$ciphertext     = substr( $raw, 16 );
		$encryption_key = hash( 'sha256', wp_salt( 'auth' ), true );

		$decrypted = openssl_decrypt( $ciphertext, self::CIPHER, $encryption_key, OPENSSL_RAW_DATA, $iv );

		return false !== $decrypted ? $decrypted : false;
	}

	/**
	 * Check if an API key is configured.
	 *
	 * @since 1.0.0
	 * @return bool True if an encrypted API key exists in options.
	 */
	public function has_api_key() {
		$encrypted = get_option( self::API_KEY_OPTION, '' );
		return ! empty( $encrypted );
	}

	/**
	 * Get the decrypted API key.
	 *
	 * @since 1.0.0
	 * @return string|\WP_Error The API key or WP_Error if not configured.
	 */
	private function get_api_key() {
		if ( ! $this->has_api_key() ) {
			return new \WP_Error(
				'no_api_key',
				__( 'OpenRouter API key is not configured. Please add your API key in WP Agent settings.', 'wp-agent' )
			);
		}

		$encrypted = get_option( self::API_KEY_OPTION, '' );
		$key       = self::decrypt_api_key( $encrypted );

		if ( false === $key || empty( $key ) ) {
			return new \WP_Error(
				'decrypt_failed',
				__( 'Failed to decrypt API key. You may need to re-enter it in settings.', 'wp-agent' )
			);
		}

		return $key;
	}

	/**
	 * Get common request headers for OpenRouter API calls.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key The API key.
	 * @return array<string, string> Request headers.
	 */
	private function get_request_headers( $api_key ) {
		return [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
			'HTTP-Referer'  => home_url(),
			'X-Title'       => 'WP Agent',
		];
	}

	/**
	 * Format headers from associative array to cURL header format.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $headers Associative headers.
	 * @return string[] Array of "Key: Value" strings.
	 */
	private function format_headers_for_curl( array $headers ) {
		$formatted = [];
		foreach ( $headers as $key => $value ) {
			$formatted[] = "{$key}: {$value}";
		}
		return $formatted;
	}

	/**
	 * Parse a single SSE chunk from the streaming response.
	 *
	 * Extracts content deltas, tool call deltas, finish reasons,
	 * and errors from the parsed JSON data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Parsed JSON data from the SSE event.
	 * @return array[] Array of typed chunk objects.
	 */
	private function parse_stream_chunk( array $data ) {
		$chunks = [];

		// Handle API-level errors in the stream.
		if ( ! empty( $data['error'] ) ) {
			$chunks[] = [
				'type'    => 'error',
				'message' => is_array( $data['error'] )
					? ( isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error' )
					: $data['error'],
			];
			return $chunks;
		}

		if ( empty( $data['choices'] ) ) {
			return $chunks;
		}

		$choice = $data['choices'][0];
		$delta  = isset( $choice['delta'] ) ? $choice['delta'] : [];

		// Content delta.
		if ( ! empty( $delta['content'] ) ) {
			$chunks[] = [
				'type'    => 'content',
				'content' => $delta['content'],
			];
		}

		// Tool call deltas.
		if ( ! empty( $delta['tool_calls'] ) ) {
			foreach ( $delta['tool_calls'] as $tool_call ) {
				$chunks[] = [
					'type'      => 'tool_call',
					'index'     => isset( $tool_call['index'] ) ? $tool_call['index'] : 0,
					'id'        => isset( $tool_call['id'] ) ? $tool_call['id'] : null,
					'function'  => isset( $tool_call['function'] ) ? $tool_call['function'] : [],
				];
			}
		}

		// Finish reason.
		if ( ! empty( $choice['finish_reason'] ) ) {
			// Mid-stream error — provider failed after partial output.
			if ( 'error' === $choice['finish_reason'] ) {
				$chunks[] = [
					'type'    => 'error',
					'message' => 'The AI provider encountered an error mid-response. Please try again.',
				];
			}

			$chunks[] = [
				'type'          => 'finish',
				'finish_reason' => $choice['finish_reason'],
			];
		}

		return $chunks;
	}
}
