<?php
/**
 * AI Client Adapter.
 *
 * Multi-provider AI client supporting Anthropic, OpenAI, and Google directly.
 * Drop-in alternative to Open_Router_Client — same interface, different backend.
 * OpenRouter remains fully functional and is NOT modified.
 *
 * @package JarvisAI\AI
 * @since   1.0.0
 */

namespace JarvisAI\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class AI_Client_Adapter
 *
 * @since 1.0.0
 */
class AI_Client_Adapter {

	/**
	 * Instance
	 *
	 * @access private
	 * @var AI_Client_Adapter|null Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Provider API endpoints.
	 *
	 * @var array<string, string>
	 */
	const ENDPOINTS = array(
		'anthropic' => 'https://api.anthropic.com/v1/messages',
		'openai'    => 'https://api.openai.com/v1/chat/completions',
		'google'    => 'https://generativelanguage.googleapis.com/v1beta/models/',
	);

	/**
	 * Option keys for encrypted API keys.
	 *
	 * @var array<string, string>
	 */
	const KEY_OPTIONS = array(
		'anthropic' => 'jarvis_ai_anthropic_api_key',
		'openai'    => 'jarvis_ai_openai_api_key',
		'google'    => 'jarvis_ai_google_api_key',
	);

	/**
	 * Model ID mapping: OpenRouter format -> native provider format.
	 *
	 * @var array<string, array{provider: string, model: string}>
	 */
	const MODEL_MAP = array(
		// Anthropic models.
		'anthropic/claude-sonnet-4'      => array(
			'provider' => 'anthropic',
			'model'    => 'claude-sonnet-4-20250514',
		),
		'anthropic/claude-sonnet-4-0514' => array(
			'provider' => 'anthropic',
			'model'    => 'claude-sonnet-4-20250514',
		),
		'anthropic/claude-haiku-3.5'     => array(
			'provider' => 'anthropic',
			'model'    => 'claude-3-5-haiku-20241022',
		),
		'anthropic/claude-opus-4'        => array(
			'provider' => 'anthropic',
			'model'    => 'claude-opus-4-20250514',
		),
		// OpenAI models.
		'openai/gpt-4o-mini'             => array(
			'provider' => 'openai',
			'model'    => 'gpt-4o-mini',
		),
		'openai/gpt-4o'                  => array(
			'provider' => 'openai',
			'model'    => 'gpt-4o',
		),
		'openai/gpt-4.1-mini'            => array(
			'provider' => 'openai',
			'model'    => 'gpt-4.1-mini',
		),
		// Google models.
		'google/gemini-2.0-flash-001'    => array(
			'provider' => 'google',
			'model'    => 'gemini-2.0-flash',
		),
		'google/gemini-2.5-pro-preview'  => array(
			'provider' => 'google',
			'model'    => 'gemini-2.5-pro-preview-05-06',
		),
	);

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return AI_Client_Adapter Initialized object of class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Send a streaming chat completion request via native provider API.
	 *
	 * Same signature as Open_Router_Client::stream() for drop-in compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $messages    Formatted messages array (OpenAI format).
	 * @param string   $model       Model ID (OpenRouter or native format).
	 * @param array    $tools       Tool definitions (OpenAI format).
	 * @param callable $callback    Callback receiving typed chunks.
	 * @param float    $temperature Sampling temperature.
	 * @param int      $max_tokens  Maximum tokens in the response.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function stream( array $messages, $model, array $tools = array(), callable $callback = null, $temperature = 0.7, $max_tokens = 4096 ) {
		$resolved = $this->resolve_model( $model );

		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$provider     = $resolved['provider'];
		$native_model = $resolved['model'];

		$api_key = $this->get_api_key( $provider );
		if ( is_wp_error( $api_key ) ) {
			return $api_key;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$tool_count = count( $tools );
			$msg_count  = count( $messages );
			error_log( "JARVIS AI [{$provider}] stream: model={$native_model}, tools={$tool_count}, messages={$msg_count}, temperature={$temperature}, max_tokens={$max_tokens}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		switch ( $provider ) {
			case 'anthropic':
				return $this->stream_anthropic( $messages, $native_model, $tools, $callback, $api_key, $temperature, $max_tokens );
			case 'openai':
				return $this->stream_openai( $messages, $native_model, $tools, $callback, $api_key, $temperature, $max_tokens );
			case 'google':
				return $this->stream_google( $messages, $native_model, $tools, $callback, $api_key, $temperature, $max_tokens );
			default:
				return new \WP_Error( 'unsupported_provider', "Provider '{$provider}' is not supported." );
		}
	}

	/**
	 * Send a non-streaming chat completion request via native provider API.
	 *
	 * Same signature as Open_Router_Client::chat() for drop-in compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $messages    Formatted messages array (OpenAI format).
	 * @param string $model       Model ID (OpenRouter or native format).
	 * @param array  $tools       Tool definitions (OpenAI format).
	 * @param float  $temperature Sampling temperature.
	 * @param int    $max_tokens  Maximum tokens in the response.
	 * @return array|\WP_Error Parsed response or WP_Error.
	 */
	public function chat( array $messages, $model, array $tools = array(), $temperature = 0.7, $max_tokens = 4096 ) {
		$resolved = $this->resolve_model( $model );

		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$provider     = $resolved['provider'];
		$native_model = $resolved['model'];

		$api_key = $this->get_api_key( $provider );
		if ( is_wp_error( $api_key ) ) {
			return $api_key;
		}

		switch ( $provider ) {
			case 'anthropic':
				return $this->chat_anthropic( $messages, $native_model, $tools, $api_key, $temperature, $max_tokens );
			case 'openai':
				return $this->chat_openai( $messages, $native_model, $tools, $api_key, $temperature, $max_tokens );
			case 'google':
				return $this->chat_google( $messages, $native_model, $tools, $api_key, $temperature, $max_tokens );
			default:
				return new \WP_Error( 'unsupported_provider', "Provider '{$provider}' is not supported." );
		}
	}

	/**
	 * Validate an API key for a specific provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key      The API key to validate.
	 * @param string $provider Provider name (anthropic, openai, google).
	 * @return true|\WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_api_key( $key, $provider ) {
		$key = trim( $key );

		if ( empty( $key ) ) {
			return new \WP_Error( 'empty_api_key', __( 'API key cannot be empty.', 'jarvis-ai' ) );
		}

		switch ( $provider ) {
			case 'anthropic':
				return $this->validate_anthropic_key( $key );
			case 'openai':
				return $this->validate_openai_key( $key );
			case 'google':
				return $this->validate_google_key( $key );
			default:
				return new \WP_Error( 'unsupported_provider', "Provider '{$provider}' is not supported." );
		}
	}

	/**
	 * Check which providers have valid keys configured.
	 *
	 * @since 1.0.0
	 * @return array<string, bool>
	 */
	public function get_configured_providers() {
		$providers = array();
		foreach ( self::KEY_OPTIONS as $provider => $option_key ) {
			$encrypted              = get_option( $option_key, '' );
			$providers[ $provider ] = ! empty( $encrypted );
		}
		return $providers;
	}

	/**
	 * Determine which provider to use for a given model, with fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model Model ID.
	 * @return array{provider: string, model: string}|\WP_Error
	 */
	private function resolve_model( $model ) {
		// Check if it's a mapped OpenRouter-style model ID.
		if ( isset( self::MODEL_MAP[ $model ] ) ) {
			$mapping  = self::MODEL_MAP[ $model ];
			$provider = $mapping['provider'];

			// Check if provider has a key configured.
			$encrypted = get_option( self::KEY_OPTIONS[ $provider ] ?? '', '' );
			if ( ! empty( $encrypted ) ) {
				return $mapping;
			}

			// Fallback: try other providers that have keys.
			return $this->find_fallback_provider( $model );
		}

		// Try to detect provider from model ID prefix (e.g., "claude-..." = anthropic).
		if ( 0 === strpos( $model, 'claude-' ) ) {
			return array(
				'provider' => 'anthropic',
				'model'    => $model,
			);
		}
		if ( 0 === strpos( $model, 'gpt-' ) ) {
			return array(
				'provider' => 'openai',
				'model'    => $model,
			);
		}
		if ( 0 === strpos( $model, 'gemini-' ) ) {
			return array(
				'provider' => 'google',
				'model'    => $model,
			);
		}

		return new \WP_Error(
			'unknown_model',
			sprintf(
				/* translators: %s: Model ID */
				__( 'Cannot determine provider for model: %s', 'jarvis-ai' ),
				$model
			)
		);
	}

	/**
	 * Find a fallback provider when the preferred one has no key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $original_model Original model ID.
	 * @return array{provider: string, model: string}|\WP_Error
	 */
	private function find_fallback_provider( $original_model ) {
		$preferred_order = get_option( 'jarvis_ai_preferred_provider', array( 'anthropic', 'openai', 'google' ) );

		if ( ! is_array( $preferred_order ) ) {
			$preferred_order = array( 'anthropic', 'openai', 'google' );
		}

		foreach ( $preferred_order as $provider ) {
			$option_key = self::KEY_OPTIONS[ $provider ] ?? '';
			$encrypted  = get_option( $option_key, '' );

			if ( ! empty( $encrypted ) ) {
				// Find the best model for this provider.
				$fallback_model = $this->get_default_model_for_provider( $provider );
				if ( $fallback_model ) {
					return array(
						'provider' => $provider,
						'model'    => $fallback_model,
					);
				}
			}
		}

		return new \WP_Error(
			'no_provider_available',
			__( 'No AI provider is configured. Please add at least one API key in JARVIS AI settings.', 'jarvis-ai' )
		);
	}

	/**
	 * Get the default/best model for a given provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider Provider name.
	 * @return string|null Native model ID or null.
	 */
	private function get_default_model_for_provider( $provider ) {
		$defaults = array(
			'anthropic' => 'claude-sonnet-4-20250514',
			'openai'    => 'gpt-4o-mini',
			'google'    => 'gemini-2.0-flash',
		);

		return $defaults[ $provider ] ?? null;
	}

	// =========================================================================
	// Anthropic Messages API
	// =========================================================================

	/**
	 * Stream via Anthropic Messages API.
	 *
	 * @param array    $messages     Messages in OpenAI format.
	 * @param string   $native_model Anthropic model ID.
	 * @param array    $tools        Tools in OpenAI format.
	 * @param callable $callback     Chunk callback.
	 * @param string   $api_key      Decrypted API key.
	 * @param float    $temperature  Temperature.
	 * @param int      $max_tokens   Max tokens.
	 * @return true|\WP_Error
	 */
	private function stream_anthropic( $messages, $native_model, $tools, $callback, $api_key, $temperature, $max_tokens ) {
		$converted = $this->convert_messages_to_anthropic( $messages );
		$body      = array(
			'model'       => $native_model,
			'max_tokens'  => (int) $max_tokens,
			'temperature' => (float) $temperature,
			'stream'      => true,
			'messages'    => $converted['messages'],
		);

		if ( ! empty( $converted['system'] ) ) {
			$body['system'] = $converted['system'];
		}

		if ( ! empty( $tools ) ) {
			$body['tools'] = $this->convert_tools_to_anthropic( $tools );
		}

		$headers = array(
			'Content-Type: application/json',
			'x-api-key: ' . $api_key,
			'anthropic-version: 2023-06-01',
		);

		return $this->stream_sse(
			self::ENDPOINTS['anthropic'],
			$headers,
			$body,
			function ( $event_type, $data ) use ( $callback ) {
				return $this->parse_anthropic_stream_event( $event_type, $data, $callback );
			}
		);
	}

	/**
	 * Non-streaming Anthropic chat.
	 *
	 * @param array  $messages     Messages in OpenAI format.
	 * @param string $native_model Anthropic model ID.
	 * @param array  $tools        Tools in OpenAI format.
	 * @param string $api_key      Decrypted API key.
	 * @param float  $temperature  Temperature.
	 * @param int    $max_tokens   Max tokens.
	 * @return array|\WP_Error Normalized response.
	 */
	private function chat_anthropic( $messages, $native_model, $tools, $api_key, $temperature, $max_tokens ) {
		$converted = $this->convert_messages_to_anthropic( $messages );
		$body      = array(
			'model'       => $native_model,
			'max_tokens'  => (int) $max_tokens,
			'temperature' => (float) $temperature,
			'messages'    => $converted['messages'],
		);

		if ( ! empty( $converted['system'] ) ) {
			$body['system'] = $converted['system'];
		}

		if ( ! empty( $tools ) ) {
			$body['tools'] = $this->convert_tools_to_anthropic( $tools );
		}

		$response = wp_remote_post(
			self::ENDPOINTS['anthropic'],
			array(
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$msg = $data['error']['message'] ?? "HTTP {$code}";
			return new \WP_Error( 'api_error', sprintf( __( 'AI request failed: %s', 'jarvis-ai' ), $msg ), array( 'status' => $code ) );
		}

		return $this->normalize_anthropic_response( $data, $native_model );
	}

	/**
	 * Convert OpenAI-format messages to Anthropic format.
	 *
	 * Extracts system prompt, converts tool calls and tool results.
	 *
	 * @param array $messages Messages in OpenAI format.
	 * @return array{system: string, messages: array}
	 */
	private function convert_messages_to_anthropic( array $messages ) {
		$system         = '';
		$anthropic_msgs = array();

		foreach ( $messages as $msg ) {
			$role = $msg['role'] ?? '';

			if ( 'system' === $role ) {
				$system = $msg['content'] ?? '';
				continue;
			}

			if ( 'assistant' === $role ) {
				$content = array();

				// Add text content if present.
				if ( ! empty( $msg['content'] ) ) {
					$content[] = array(
						'type' => 'text',
						'text' => $msg['content'],
					);
				}

				// Convert tool_calls to tool_use blocks.
				if ( ! empty( $msg['tool_calls'] ) ) {
					foreach ( $msg['tool_calls'] as $tc ) {
						$arguments = $tc['function']['arguments'] ?? '{}';
						if ( is_string( $arguments ) ) {
							$arguments = json_decode( $arguments, true );
						}
						// Anthropic requires input to be an object (dict), never array or null.
						if ( ! is_array( $arguments ) || array_is_list( $arguments ) ) {
							$arguments = new \stdClass();
						}

						$content[] = array(
							'type'  => 'tool_use',
							'id'    => $tc['id'] ?? 'toolu_' . wp_generate_uuid4(),
							'name'  => $tc['function']['name'] ?? '',
							'input' => $arguments,
						);
					}
				}

				if ( ! empty( $content ) ) {
					$anthropic_msgs[] = array(
						'role'    => 'assistant',
						'content' => $content,
					);
				}
				continue;
			}

			if ( 'tool' === $role ) {
				// Anthropic expects tool results as user messages with tool_result content.
				$result_content = $msg['content'] ?? '';
				if ( is_string( $result_content ) ) {
					// Try to parse as JSON for structured result.
					$parsed = json_decode( $result_content, true );
					if ( null !== $parsed ) {
						$result_content = wp_json_encode( $parsed );
					}
				}

				$tool_result = array(
					'type'        => 'tool_result',
					'tool_use_id' => $msg['tool_call_id'] ?? '',
					'content'     => (string) $result_content,
				);

				// Merge consecutive tool results into a single user message.
				$last_idx = count( $anthropic_msgs ) - 1;
				if ( $last_idx >= 0
					&& 'user' === $anthropic_msgs[ $last_idx ]['role']
					&& is_array( $anthropic_msgs[ $last_idx ]['content'] )
					&& ! empty( $anthropic_msgs[ $last_idx ]['content'][0]['type'] )
					&& 'tool_result' === $anthropic_msgs[ $last_idx ]['content'][0]['type']
				) {
					$anthropic_msgs[ $last_idx ]['content'][] = $tool_result;
				} else {
					$anthropic_msgs[] = array(
						'role'    => 'user',
						'content' => array( $tool_result ),
					);
				}
				continue;
			}

			if ( 'user' === $role ) {
				$anthropic_msgs[] = array(
					'role'    => 'user',
					'content' => $msg['content'] ?? '',
				);
				continue;
			}
		}

		return array(
			'system'   => $system,
			'messages' => $anthropic_msgs,
		);
	}

	/**
	 * Convert OpenAI-format tools to Anthropic format.
	 *
	 * @param array $tools Tools in OpenAI format.
	 * @return array Tools in Anthropic format.
	 */
	private function convert_tools_to_anthropic( array $tools ) {
		$anthropic_tools = array();

		foreach ( $tools as $tool ) {
			if ( 'function' !== ( $tool['type'] ?? '' ) ) {
				continue;
			}

			$fn = $tool['function'] ?? array();

			$anthropic_tool = array(
				'name'         => $fn['name'] ?? '',
				'description'  => $fn['description'] ?? '',
				'input_schema' => $fn['parameters'] ?? array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			);

			$anthropic_tools[] = $anthropic_tool;
		}

		return $anthropic_tools;
	}

	/**
	 * Parse Anthropic SSE stream events into normalized chunks.
	 *
	 * Anthropic SSE events:
	 * - message_start: metadata
	 * - content_block_start: starts text or tool_use block
	 * - content_block_delta: text_delta or input_json_delta
	 * - content_block_stop: ends block
	 * - message_delta: stop_reason + usage
	 * - message_stop: stream complete
	 *
	 * @param string   $event_type SSE event type.
	 * @param array    $data       Parsed JSON event data.
	 * @param callable $callback   Chunk callback.
	 */
	private function parse_anthropic_stream_event( $event_type, $data, $callback ) {
		static $content_blocks = array();

		if ( ! $callback ) {
			return;
		}

		switch ( $event_type ) {
			case 'message_start':
				// Anthropic sends input token count at message start.
				$usage = $data['message']['usage'] ?? array();
				if ( ! empty( $usage ) ) {
					$input  = (int) ( $usage['input_tokens'] ?? 0 );
					$output = (int) ( $usage['output_tokens'] ?? 0 );
					call_user_func(
						$callback,
						array(
							'type'              => 'usage',
							'prompt_tokens'     => $input,
							'completion_tokens' => $output,
							'total_tokens'      => $input + $output,
						)
					);
				}
				break;

			case 'content_block_start':
				$index = $data['index'] ?? 0;
				$block = $data['content_block'] ?? array();

				$content_blocks[ $index ] = $block;

				// If this is a tool_use block, emit the initial tool_call chunk.
				if ( 'tool_use' === ( $block['type'] ?? '' ) ) {
					call_user_func(
						$callback,
						array(
							'type'     => 'tool_call',
							'index'    => $index,
							'id'       => $block['id'] ?? '',
							'function' => array(
								'name'      => $block['name'] ?? '',
								'arguments' => '',
							),
						)
					);
				}
				break;

			case 'content_block_delta':
				$index = $data['index'] ?? 0;
				$delta = $data['delta'] ?? array();

				if ( 'text_delta' === ( $delta['type'] ?? '' ) ) {
					call_user_func(
						$callback,
						array(
							'type'    => 'content',
							'content' => $delta['text'] ?? '',
						)
					);
				} elseif ( 'input_json_delta' === ( $delta['type'] ?? '' ) ) {
					// Tool call argument fragment.
					call_user_func(
						$callback,
						array(
							'type'     => 'tool_call',
							'index'    => $index,
							'id'       => null,
							'function' => array(
								'name'      => '',
								'arguments' => $delta['partial_json'] ?? '',
							),
						)
					);
				}
				break;

			case 'message_delta':
				$stop_reason = $data['delta']['stop_reason'] ?? '';
				if ( ! empty( $stop_reason ) ) {
					// Map Anthropic stop_reason to OpenAI finish_reason.
					$finish_reason = 'end_turn' === $stop_reason ? 'stop' : $stop_reason;
					call_user_func(
						$callback,
						array(
							'type'          => 'finish',
							'finish_reason' => $finish_reason,
						)
					);
				}

				// Anthropic sends output token count at message end.
				$usage = $data['usage'] ?? array();
				if ( ! empty( $usage ) ) {
					call_user_func(
						$callback,
						array(
							'type'              => 'usage',
							'prompt_tokens'     => 0,
							'completion_tokens' => (int) ( $usage['output_tokens'] ?? 0 ),
							'total_tokens'      => (int) ( $usage['output_tokens'] ?? 0 ),
						)
					);
				}
				break;

			case 'message_stop':
				call_user_func( $callback, array( 'type' => 'done' ) );
				$content_blocks = array(); // Reset for next stream.
				break;

			case 'error':
				$error_msg = $data['error']['message'] ?? 'Unknown Anthropic error';
				call_user_func(
					$callback,
					array(
						'type'    => 'error',
						'message' => $error_msg,
					)
				);
				break;
		}
	}

	/**
	 * Normalize Anthropic non-streaming response to OpenAI format.
	 *
	 * @param array  $data         Raw Anthropic response.
	 * @param string $native_model Model ID used.
	 * @return array Normalized response.
	 */
	private function normalize_anthropic_response( $data, $native_model ) {
		$content    = '';
		$tool_calls = array();
		$tc_index   = 0;

		foreach ( $data['content'] ?? array() as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$content .= $block['text'] ?? '';
			} elseif ( 'tool_use' === ( $block['type'] ?? '' ) ) {
				$tool_calls[] = array(
					'id'       => $block['id'] ?? '',
					'type'     => 'function',
					'function' => array(
						'name'      => $block['name'] ?? '',
						'arguments' => wp_json_encode( ! empty( $block['input'] ) ? $block['input'] : new \stdClass() ),
					),
				);
				++$tc_index;
			}
		}

		$stop          = $data['stop_reason'] ?? '';
		$finish_reason = 'end_turn' === $stop ? 'stop' : $stop;

		return array(
			'content'       => $content,
			'tool_calls'    => $tool_calls,
			'model'         => $data['model'] ?? $native_model,
			'usage'         => array(
				'prompt_tokens'     => $data['usage']['input_tokens'] ?? 0,
				'completion_tokens' => $data['usage']['output_tokens'] ?? 0,
				'total_tokens'      => ( $data['usage']['input_tokens'] ?? 0 ) + ( $data['usage']['output_tokens'] ?? 0 ),
			),
			'finish_reason' => $finish_reason,
		);
	}

	// =========================================================================
	// OpenAI Chat Completions API
	// =========================================================================

	/**
	 * Stream via OpenAI Chat Completions API.
	 *
	 * @param array    $messages     Messages (already in OpenAI format).
	 * @param string   $native_model OpenAI model ID.
	 * @param array    $tools        Tools in OpenAI format.
	 * @param callable $callback     Chunk callback.
	 * @param string   $api_key      Decrypted API key.
	 * @param float    $temperature  Temperature.
	 * @param int      $max_tokens   Max tokens.
	 * @return true|\WP_Error
	 */
	private function stream_openai( $messages, $native_model, $tools, $callback, $api_key, $temperature, $max_tokens ) {
		$body = array(
			'model'               => $native_model,
			'messages'            => $messages,
			'stream'              => true,
			'stream_options'      => array( 'include_usage' => true ),
			'max_tokens'          => (int) $max_tokens,
			'temperature'         => (float) $temperature,
			'parallel_tool_calls' => false,
		);

		if ( ! empty( $tools ) ) {
			$body['tools'] = $tools;
		}

		$headers = array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . $api_key,
		);

		return $this->stream_sse(
			self::ENDPOINTS['openai'],
			$headers,
			$body,
			function ( $event_type, $data ) use ( $callback ) {
				// OpenAI uses the same SSE format as OpenRouter.
				$this->parse_openai_stream_chunk( $data, $callback );
			}
		);
	}

	/**
	 * Non-streaming OpenAI chat.
	 *
	 * @param array  $messages     Messages (already in OpenAI format).
	 * @param string $native_model OpenAI model ID.
	 * @param array  $tools        Tools in OpenAI format.
	 * @param string $api_key      Decrypted API key.
	 * @param float  $temperature  Temperature.
	 * @param int    $max_tokens   Max tokens.
	 * @return array|\WP_Error Normalized response.
	 */
	private function chat_openai( $messages, $native_model, $tools, $api_key, $temperature, $max_tokens ) {
		$body = array(
			'model'               => $native_model,
			'messages'            => $messages,
			'max_tokens'          => (int) $max_tokens,
			'temperature'         => (float) $temperature,
			'parallel_tool_calls' => false,
		);

		if ( ! empty( $tools ) ) {
			$body['tools'] = $tools;
		}

		$response = wp_remote_post(
			self::ENDPOINTS['openai'],
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$msg = $data['error']['message'] ?? "HTTP {$code}";
			return new \WP_Error( 'api_error', sprintf( __( 'AI request failed: %s', 'jarvis-ai' ), $msg ), array( 'status' => $code ) );
		}

		$choice = $data['choices'][0] ?? array();

		return array(
			'content'       => $choice['message']['content'] ?? '',
			'tool_calls'    => $choice['message']['tool_calls'] ?? array(),
			'model'         => $data['model'] ?? $native_model,
			'usage'         => $data['usage'] ?? array(),
			'finish_reason' => $choice['finish_reason'] ?? '',
		);
	}

	/**
	 * Parse OpenAI SSE chunk (same format as OpenRouter).
	 *
	 * @param array    $data     Parsed JSON data.
	 * @param callable $callback Chunk callback.
	 */
	private function parse_openai_stream_chunk( $data, $callback ) {
		if ( ! $callback ) {
			return;
		}

		if ( ! empty( $data['error'] ) ) {
			$msg = is_array( $data['error'] ) ? ( $data['error']['message'] ?? 'Unknown error' ) : $data['error'];
			call_user_func(
				$callback,
				array(
					'type'    => 'error',
					'message' => $msg,
				)
			);
			return;
		}

		if ( empty( $data['choices'] ) ) {
			return;
		}

		$choice = $data['choices'][0];
		$delta  = $choice['delta'] ?? array();

		if ( ! empty( $delta['content'] ) ) {
			call_user_func(
				$callback,
				array(
					'type'    => 'content',
					'content' => $delta['content'],
				)
			);
		}

		if ( ! empty( $delta['tool_calls'] ) ) {
			foreach ( $delta['tool_calls'] as $tc ) {
				call_user_func(
					$callback,
					array(
						'type'     => 'tool_call',
						'index'    => $tc['index'] ?? 0,
						'id'       => $tc['id'] ?? null,
						'function' => $tc['function'] ?? array(),
					)
				);
			}
		}

		if ( ! empty( $choice['finish_reason'] ) ) {
			call_user_func(
				$callback,
				array(
					'type'          => 'finish',
					'finish_reason' => $choice['finish_reason'],
				)
			);
		}

		// Usage data (sent in the final chunk when stream_options.include_usage is set).
		if ( ! empty( $data['usage'] ) ) {
			call_user_func(
				$callback,
				array(
					'type'              => 'usage',
					'prompt_tokens'     => (int) ( $data['usage']['prompt_tokens'] ?? 0 ),
					'completion_tokens' => (int) ( $data['usage']['completion_tokens'] ?? 0 ),
					'total_tokens'      => (int) ( $data['usage']['total_tokens'] ?? 0 ),
				)
			);
		}
	}

	// =========================================================================
	// Google Gemini API
	// =========================================================================

	/**
	 * Stream via Google Gemini API.
	 *
	 * @param array    $messages     Messages in OpenAI format.
	 * @param string   $native_model Google model ID.
	 * @param array    $tools        Tools in OpenAI format.
	 * @param callable $callback     Chunk callback.
	 * @param string   $api_key      Decrypted API key.
	 * @param float    $temperature  Temperature.
	 * @param int      $max_tokens   Max tokens.
	 * @return true|\WP_Error
	 */
	private function stream_google( $messages, $native_model, $tools, $callback, $api_key, $temperature, $max_tokens ) {
		$converted = $this->convert_messages_to_google( $messages );
		$body      = array(
			'contents'         => $converted['contents'],
			'generationConfig' => array(
				'temperature'     => (float) $temperature,
				'maxOutputTokens' => (int) $max_tokens,
			),
		);

		if ( ! empty( $converted['system_instruction'] ) ) {
			$body['systemInstruction'] = array(
				'parts' => array( array( 'text' => $converted['system_instruction'] ) ),
			);
		}

		if ( ! empty( $tools ) ) {
			$body['tools'] = array( array( 'functionDeclarations' => $this->convert_tools_to_google( $tools ) ) );
		}

		$url     = self::ENDPOINTS['google'] . $native_model . ':streamGenerateContent?alt=sse&key=' . $api_key;
		$headers = array( 'Content-Type: application/json' );

		return $this->stream_sse(
			$url,
			$headers,
			$body,
			function ( $event_type, $data ) use ( $callback ) {
				$this->parse_google_stream_event( $data, $callback );
			}
		);
	}

	/**
	 * Non-streaming Google Gemini chat.
	 *
	 * @param array  $messages     Messages in OpenAI format.
	 * @param string $native_model Google model ID.
	 * @param array  $tools        Tools in OpenAI format.
	 * @param string $api_key      Decrypted API key.
	 * @param float  $temperature  Temperature.
	 * @param int    $max_tokens   Max tokens.
	 * @return array|\WP_Error Normalized response.
	 */
	private function chat_google( $messages, $native_model, $tools, $api_key, $temperature, $max_tokens ) {
		$converted = $this->convert_messages_to_google( $messages );
		$body      = array(
			'contents'         => $converted['contents'],
			'generationConfig' => array(
				'temperature'     => (float) $temperature,
				'maxOutputTokens' => (int) $max_tokens,
			),
		);

		if ( ! empty( $converted['system_instruction'] ) ) {
			$body['systemInstruction'] = array(
				'parts' => array( array( 'text' => $converted['system_instruction'] ) ),
			);
		}

		if ( ! empty( $tools ) ) {
			$body['tools'] = array( array( 'functionDeclarations' => $this->convert_tools_to_google( $tools ) ) );
		}

		$url = self::ENDPOINTS['google'] . $native_model . ':generateContent?key=' . $api_key;

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$msg = $data['error']['message'] ?? "HTTP {$code}";
			return new \WP_Error( 'api_error', sprintf( __( 'AI request failed: %s', 'jarvis-ai' ), $msg ), array( 'status' => $code ) );
		}

		return $this->normalize_google_response( $data, $native_model );
	}

	/**
	 * Convert OpenAI-format messages to Google Gemini format.
	 *
	 * @param array $messages Messages in OpenAI format.
	 * @return array{system_instruction: string, contents: array}
	 */
	private function convert_messages_to_google( array $messages ) {
		$system   = '';
		$contents = array();

		foreach ( $messages as $msg ) {
			$role = $msg['role'] ?? '';

			if ( 'system' === $role ) {
				$system = $msg['content'] ?? '';
				continue;
			}

			if ( 'assistant' === $role ) {
				$parts = array();
				if ( ! empty( $msg['content'] ) ) {
					$parts[] = array( 'text' => $msg['content'] );
				}
				if ( ! empty( $msg['tool_calls'] ) ) {
					foreach ( $msg['tool_calls'] as $tc ) {
						$args = $tc['function']['arguments'] ?? '{}';
						if ( is_string( $args ) ) {
							$args = json_decode( $args, true ) ?? array();
						}
						$parts[] = array(
							'functionCall' => array(
								'name' => $tc['function']['name'] ?? '',
								'args' => $args,
							),
						);
					}
				}
				if ( ! empty( $parts ) ) {
					$contents[] = array(
						'role'  => 'model',
						'parts' => $parts,
					);
				}
				continue;
			}

			if ( 'tool' === $role ) {
				$result = $msg['content'] ?? '';
				$parsed = is_string( $result ) ? ( json_decode( $result, true ) ?? array( 'result' => $result ) ) : $result;

				$contents[] = array(
					'role'  => 'user',
					'parts' => array(
						array(
							'functionResponse' => array(
								'name'     => $msg['name'] ?? 'tool_result',
								'response' => $parsed,
							),
						),
					),
				);
				continue;
			}

			if ( 'user' === $role ) {
				$contents[] = array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $msg['content'] ?? '' ) ),
				);
			}
		}

		return array(
			'system_instruction' => $system,
			'contents'           => $contents,
		);
	}

	/**
	 * Convert OpenAI-format tools to Google Gemini format.
	 *
	 * @param array $tools Tools in OpenAI format.
	 * @return array Function declarations.
	 */
	private function convert_tools_to_google( array $tools ) {
		$declarations = array();
		foreach ( $tools as $tool ) {
			if ( 'function' !== ( $tool['type'] ?? '' ) ) {
				continue;
			}
			$fn             = $tool['function'] ?? array();
			$declarations[] = array(
				'name'        => $fn['name'] ?? '',
				'description' => $fn['description'] ?? '',
				'parameters'  => $fn['parameters'] ?? array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
			);
		}
		return $declarations;
	}

	/**
	 * Parse Google Gemini stream event.
	 *
	 * @param array    $data     Parsed JSON data.
	 * @param callable $callback Chunk callback.
	 */
	private function parse_google_stream_event( $data, $callback ) {
		if ( ! $callback || empty( $data['candidates'] ) ) {
			return;
		}

		$candidate = $data['candidates'][0];
		$parts     = $candidate['content']['parts'] ?? array();

		foreach ( $parts as $index => $part ) {
			if ( isset( $part['text'] ) ) {
				call_user_func(
					$callback,
					array(
						'type'    => 'content',
						'content' => $part['text'],
					)
				);
			} elseif ( isset( $part['functionCall'] ) ) {
				call_user_func(
					$callback,
					array(
						'type'     => 'tool_call',
						'index'    => $index,
						'id'       => 'call_' . wp_generate_uuid4(),
						'function' => array(
							'name'      => $part['functionCall']['name'] ?? '',
							'arguments' => wp_json_encode( ! empty( $part['functionCall']['args'] ) ? $part['functionCall']['args'] : new \stdClass() ),
						),
					)
				);
			}
		}

		$finish_reason = $candidate['finishReason'] ?? '';
		if ( ! empty( $finish_reason ) ) {
			$mapped = 'STOP' === $finish_reason ? 'stop' : strtolower( $finish_reason );
			call_user_func(
				$callback,
				array(
					'type'          => 'finish',
					'finish_reason' => $mapped,
				)
			);
		}

		// Google sends usageMetadata with token counts.
		$usage = $data['usageMetadata'] ?? array();
		if ( ! empty( $usage ) ) {
			$prompt     = (int) ( $usage['promptTokenCount'] ?? 0 );
			$completion = (int) ( $usage['candidatesTokenCount'] ?? 0 );
			call_user_func(
				$callback,
				array(
					'type'              => 'usage',
					'prompt_tokens'     => $prompt,
					'completion_tokens' => $completion,
					'total_tokens'      => (int) ( $usage['totalTokenCount'] ?? ( $prompt + $completion ) ),
				)
			);
		}

		if ( ! empty( $finish_reason ) ) {
			call_user_func( $callback, array( 'type' => 'done' ) );
		}
	}

	/**
	 * Normalize Google Gemini non-streaming response.
	 *
	 * @param array  $data         Raw response.
	 * @param string $native_model Model ID.
	 * @return array Normalized response.
	 */
	private function normalize_google_response( $data, $native_model ) {
		$content    = '';
		$tool_calls = array();
		$candidate  = $data['candidates'][0] ?? array();

		foreach ( $candidate['content']['parts'] ?? array() as $part ) {
			if ( isset( $part['text'] ) ) {
				$content .= $part['text'];
			} elseif ( isset( $part['functionCall'] ) ) {
				$tool_calls[] = array(
					'id'       => 'call_' . wp_generate_uuid4(),
					'type'     => 'function',
					'function' => array(
						'name'      => $part['functionCall']['name'] ?? '',
						'arguments' => wp_json_encode( ! empty( $part['functionCall']['args'] ) ? $part['functionCall']['args'] : new \stdClass() ),
					),
				);
			}
		}

		$finish_reason = $candidate['finishReason'] ?? '';
		$mapped        = 'STOP' === $finish_reason ? 'stop' : strtolower( $finish_reason );

		$usage = $data['usageMetadata'] ?? array();

		return array(
			'content'       => $content,
			'tool_calls'    => $tool_calls,
			'model'         => $native_model,
			'usage'         => array(
				'prompt_tokens'     => $usage['promptTokenCount'] ?? 0,
				'completion_tokens' => $usage['candidatesTokenCount'] ?? 0,
				'total_tokens'      => $usage['totalTokenCount'] ?? 0,
			),
			'finish_reason' => $mapped,
		);
	}

	// =========================================================================
	// Shared SSE Streaming Engine
	// =========================================================================

	/**
	 * Generic SSE streaming via cURL.
	 *
	 * Handles Anthropic (event: type + data:) and OpenAI/Google (data: only) formats.
	 *
	 * @param string   $url      API endpoint.
	 * @param array    $headers  cURL-format headers.
	 * @param array    $body     Request body.
	 * @param callable $handler  Callback(event_type, parsed_data).
	 * @return true|\WP_Error
	 */
	private function stream_sse( $url, array $headers, array $body, callable $handler ) {
		$buffer        = '';
		$raw_body      = '';
		$current_event = 'message'; // Default SSE event type.

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- cURL required for streaming.
		$ch = curl_init();

		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt -- cURL required for streaming.
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 180 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 15 );
		curl_setopt( $ch, CURLOPT_TCP_KEEPALIVE, 1 );
		curl_setopt( $ch, CURLOPT_TCP_KEEPIDLE, 30 );
		curl_setopt( $ch, CURLOPT_TCP_KEEPINTVL, 15 );

		// SSL verification.
		$ca_bundle = ABSPATH . WPINC . '/certificates/ca-bundle.crt';
		if ( file_exists( $ca_bundle ) ) {
			curl_setopt( $ch, CURLOPT_CAINFO, $ca_bundle );
		}

		curl_setopt(
			$ch,
			CURLOPT_WRITEFUNCTION,
			function ( $ch, $data ) use ( &$buffer, &$raw_body, &$current_event, $handler ) {
				$buffer   .= $data;
				$raw_body .= $data;

				while ( false !== ( $newline_pos = strpos( $buffer, "\n" ) ) ) {
					$line   = substr( $buffer, 0, $newline_pos );
					$buffer = substr( $buffer, $newline_pos + 1 );
					$line   = trim( $line );

					if ( '' === $line ) {
						continue;
					}

					// SSE event type line (Anthropic uses "event: content_block_start" etc.).
					if ( 0 === strpos( $line, 'event: ' ) ) {
						$current_event = trim( substr( $line, 7 ) );
						continue;
					}

					// SSE comments.
					if ( 0 === strpos( $line, ':' ) ) {
						continue;
					}

					// SSE data line.
					if ( 0 !== strpos( $line, 'data: ' ) ) {
						continue;
					}

					$json_str = substr( $line, 6 );

					if ( '[DONE]' === $json_str ) {
						// OpenAI-style stream termination.
						call_user_func( $handler, 'done', array() );
						$current_event = 'message';
						continue;
					}

					$parsed = json_decode( $json_str, true );
					if ( null === $parsed ) {
						continue;
					}

					call_user_func( $handler, $current_event, $parsed );
					$current_event = 'message'; // Reset after each data event.
				}

				return strlen( $data );
			}
		);
		// phpcs:enable

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec -- cURL required for streaming.
		$result = curl_exec( $ch );

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
			$upstream_message = "HTTP {$http_code}";
			$raw_decoded      = json_decode( trim( $raw_body ), true );
			if ( ! empty( $raw_decoded['error']['message'] ) ) {
				$upstream_message = $raw_decoded['error']['message'];
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "JARVIS AI stream error: HTTP {$http_code} — {$upstream_message}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return new \WP_Error(
				'api_error',
				sprintf( __( 'AI request failed: %s', 'jarvis-ai' ), $upstream_message ),
				array( 'status' => $http_code )
			);
		}

		return true;
	}

	// =========================================================================
	// API Key Management
	// =========================================================================

	/**
	 * Get the decrypted API key for a provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider Provider name.
	 * @return string|\WP_Error The API key or WP_Error.
	 */
	private function get_api_key( $provider ) {
		$option_key = self::KEY_OPTIONS[ $provider ] ?? '';

		if ( empty( $option_key ) ) {
			return new \WP_Error( 'unsupported_provider', "Provider '{$provider}' is not supported." );
		}

		$encrypted = get_option( $option_key, '' );

		if ( empty( $encrypted ) ) {
			return new \WP_Error(
				'no_api_key',
				sprintf(
					/* translators: %s: Provider name */
					__( '%s API key is not configured. Please add your API key in JARVIS AI settings.', 'jarvis-ai' ),
					ucfirst( $provider )
				)
			);
		}

		$key = Open_Router_Client::decrypt_api_key( $encrypted );

		if ( false === $key || empty( $key ) ) {
			return new \WP_Error(
				'decrypt_failed',
				__( 'Failed to decrypt API key. You may need to re-enter it in settings.', 'jarvis-ai' )
			);
		}

		return $key;
	}

	// =========================================================================
	// Provider Key Validation
	// =========================================================================

	/**
	 * Validate Anthropic API key.
	 *
	 * @param string $key API key.
	 * @return true|\WP_Error
	 */
	private function validate_anthropic_key( $key ) {
		$response = wp_remote_post(
			self::ENDPOINTS['anthropic'],
			array(
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $key,
					'anthropic-version' => '2023-06-01',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => 'claude-haiku-4-5-20251001',
						'max_tokens' => 1,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => 'Hi',
							),
						),
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error( 'invalid_api_key', __( 'The Anthropic API key is invalid or has been revoked.', 'jarvis-ai' ) );
		}

		// 200 or 429 (rate limit) both mean the key is valid.
		if ( $code < 400 || 429 === $code ) {
			return true;
		}

		$msg = $body['error']['message'] ?? sprintf( 'HTTP %d', $code );
		return new \WP_Error(
			'api_error',
			/* translators: %s: API error detail */
			sprintf( __( 'Anthropic API validation failed: %s', 'jarvis-ai' ), $msg )
		);
	}

	/**
	 * Validate OpenAI API key.
	 *
	 * @param string $key API key.
	 * @return true|\WP_Error
	 */
	private function validate_openai_key( $key ) {
		$response = wp_remote_get(
			'https://api.openai.com/v1/models',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error( 'invalid_api_key', __( 'The OpenAI API key is invalid or has been revoked.', 'jarvis-ai' ) );
		}

		if ( $code < 400 ) {
			return true;
		}

		return new \WP_Error(
			'api_error',
			sprintf( __( 'OpenAI API returned HTTP %d during validation.', 'jarvis-ai' ), $code )
		);
	}

	/**
	 * Validate Google API key.
	 *
	 * @param string $key API key.
	 * @return true|\WP_Error
	 */
	private function validate_google_key( $key ) {
		$response = wp_remote_get(
			self::ENDPOINTS['google'] . '?key=' . $key,
			array( 'timeout' => 15 )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error( 'invalid_api_key', __( 'The Google API key is invalid or has been revoked.', 'jarvis-ai' ) );
		}

		if ( $code < 400 ) {
			return true;
		}

		return new \WP_Error(
			'api_error',
			sprintf( __( 'Google API returned HTTP %d during validation.', 'jarvis-ai' ), $code )
		);
	}
}
