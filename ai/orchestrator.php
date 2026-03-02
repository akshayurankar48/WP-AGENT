<?php
/**
 * Orchestrator.
 *
 * The main AI brain. Receives a user message, builds context, calls the
 * AI model, handles tool-call loops, persists messages, and returns the
 * response. Supports both non-streaming and streaming transports.
 *
 * This is a library class — REST endpoints (Commit 6) call it.
 *
 * @package WPAgent\AI
 * @since   1.0.0
 */

namespace WPAgent\AI;

use WPAgent\Actions\Action_Registry;
use WPAgent\Core\Checkpoint_Manager;
use WPAgent\Core\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Class Orchestrator
 *
 * @since 1.0.0
 */
class Orchestrator {

	/**
	 * Instance
	 *
	 * @access private
	 * @var Orchestrator|null Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Maximum tool-call loop iterations before bailing.
	 *
	 * @var int
	 */
	const MAX_TOOL_ITERATIONS = 25;

	/**
	 * Maximum retries for transient API errors within a single iteration.
	 *
	 * @var int
	 */
	const MAX_RETRIES = 2;

	/**
	 * Transient error codes that are safe to retry.
	 *
	 * @var string[]
	 */
	const RETRYABLE_ERRORS = [
		'http_request_failed',
		'api_timeout',
		'rate_limited',
		'server_error',
		'connection_reset',
	];

	/**
	 * Maximum number of history messages to load.
	 *
	 * @var int
	 */
	const MAX_HISTORY_MESSAGES = 20;

	/**
	 * Maximum user message length in characters.
	 *
	 * @var int
	 */
	const MAX_MESSAGE_LENGTH = 32000;

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return Orchestrator Initialized object of class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Handle a user message (non-streaming).
	 *
	 * Full lifecycle: rate limit, context, AI call, tool loops, DB persistence.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $user_message    The user's message text.
	 * @param int      $user_id         WordPress user ID.
	 * @param int|null $conversation_id Existing conversation ID, or null to create new.
	 * @param array    $options {
	 *     Optional overrides.
	 *
	 *     @type string $model      Override model ID.
	 *     @type int    $post_id    Associated post ID.
	 *     @type string $admin_page Current admin page slug.
	 * }
	 * @return array|\WP_Error {
	 *     @type int    $conversation_id Conversation ID.
	 *     @type string $content         Final assistant response text.
	 *     @type array  $actions_taken   List of actions executed [{name, params, result}, ...].
	 *     @type string $model           Model ID that was used.
	 *     @type array  $usage           Token usage.
	 * }
	 */
	public function handle( $user_message, $user_id, $conversation_id = null, array $options = [] ) {
		// Enforce message length limit.
		$user_message = $this->truncate_message( $user_message );

		// 1. Rate limit.
		$rate_check = Rate_Limiter::get_instance()->check_and_record( $user_id );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		// 2. Conversation (with ownership check).
		$conversation_id = $this->ensure_conversation( $conversation_id, $user_id, $options );
		if ( is_wp_error( $conversation_id ) ) {
			return $conversation_id;
		}

		// 3. Load history.
		$history = $this->load_history( $conversation_id );

		// 4. Context and tools.
		$admin_page = isset( $options['admin_page'] ) ? $options['admin_page'] : '';
		$post_id    = isset( $options['post_id'] ) ? (int) $options['post_id'] : 0;
		$context    = Context_Collector::get_instance()->collect( $user_id, $admin_page, $post_id );
		$actions    = Action_Registry::get_instance()->get_tool_definitions();

		// 5. Select model.
		$model = $this->resolve_model( $options, $user_message, $history );

		// 6. Build prompt.
		$prompt_builder = Prompt_Builder::get_instance();
		$system_prompt  = $prompt_builder->build_system_prompt( $context );
		$messages       = $prompt_builder->build_messages( $system_prompt, $history, $user_message );
		$tools          = $prompt_builder->build_tool_definitions( $actions );

		// Save user message to DB.
		$this->save_message( $conversation_id, 'user', $user_message );

		// 7. Resolve temperature and max_tokens based on context.
		$has_tools   = ! empty( $tools );
		$in_editor   = ! empty( $options['post_id'] );
		$temperature = $has_tools ? 0.2 : 0.7;

		if ( $in_editor && $has_tools ) {
			// Editor context: AI generates large block JSON structures.
			// Needs headroom for multi-section pages with chunked tool calls.
			$max_tokens = 32768;
		} elseif ( $has_tools ) {
			// Admin drawer: needs room for create_post + insert_blocks with full page content.
			$max_tokens = 16384;
		} else {
			$max_tokens = 4096;
		}

		// 8. AI call with tool loop.
		$client        = $this->get_ai_client();
		$actions_taken = [];
		$total_usage   = [ 'prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0 ];
		$final_content = '';
		$used_model    = $model;

		for ( $iteration = 0; $iteration < self::MAX_TOOL_ITERATIONS; $iteration++ ) {
			// Reset execution timer — each AI call can take 30+ seconds,
			// and multi-iteration tool loops would exceed PHP's max_execution_time.
			set_time_limit( 120 );

			$response = $this->retry_api_call(
				function ( $m ) use ( $client, $messages, $tools, $temperature, $max_tokens ) {
					return $client->chat( $messages, $m, $tools, $temperature, $max_tokens );
				},
				$iteration,
				$model,
				$used_model
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			// Accumulate usage.
			if ( ! empty( $response['usage'] ) ) {
				$total_usage['prompt_tokens']     += (int) ( $response['usage']['prompt_tokens'] ?? 0 );
				$total_usage['completion_tokens'] += (int) ( $response['usage']['completion_tokens'] ?? 0 );
				$total_usage['total_tokens']      += (int) ( $response['usage']['total_tokens'] ?? 0 );
			}

			$used_model = isset( $response['model'] ) ? $response['model'] : $model;

			// 8. Handle tool calls.
			if ( ! empty( $response['tool_calls'] ) ) {
				// Save assistant message with tool calls.
				$this->save_message(
					$conversation_id,
					'assistant',
					$response['content'],
					$used_model,
					0,
					[ 'tool_calls' => $response['tool_calls'] ]
				);

				// Append assistant message to context for next iteration.
				$messages[] = [
					'role'       => 'assistant',
					'content'    => $response['content'],
					'tool_calls' => $response['tool_calls'],
				];

				// Dispatch tool calls via shared method.
				$dispatch_result = $this->dispatch_tool_calls(
					$response['tool_calls'],
					$conversation_id,
					$user_id,
					$messages
				);

				$actions_taken = array_merge( $actions_taken, $dispatch_result );

				// Continue loop — AI needs to process tool results.
				continue;
			}

			// No tool calls — we have the final response.
			$final_content = $response['content'];
			break;
		}

		// 10. Save final assistant message.
		$this->save_message( $conversation_id, 'assistant', $final_content, $used_model );

		// 12. Update conversation.
		$this->update_conversation( $conversation_id, $used_model, $total_usage['total_tokens'] );

		return [
			'conversation_id' => $conversation_id,
			'content'         => $final_content,
			'actions_taken'   => $actions_taken,
			'model'           => $used_model,
			'usage'           => $total_usage,
		];
	}

	/**
	 * Handle a user message with streaming.
	 *
	 * Same lifecycle as handle() but uses SSE streaming for the AI response.
	 * Content chunks pass through to the callback. Tool call internals are
	 * NOT forwarded — only the final content reaches the caller.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $user_message    The user's message text.
	 * @param int      $user_id         WordPress user ID.
	 * @param int|null $conversation_id Existing conversation ID, or null to create new.
	 * @param callable $callback        Callback receiving typed chunks from the stream.
	 * @param array    $options         Optional overrides (same as handle()).
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function handle_stream( $user_message, $user_id, $conversation_id = null, callable $callback = null, array $options = [] ) {
		// Enforce message length limit.
		$user_message = $this->truncate_message( $user_message );

		// 1. Rate limit.
		$rate_check = Rate_Limiter::get_instance()->check_and_record( $user_id );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		// 2. Conversation (with ownership check).
		$conversation_id = $this->ensure_conversation( $conversation_id, $user_id, $options );
		if ( is_wp_error( $conversation_id ) ) {
			return $conversation_id;
		}

		// 3. Load history.
		$history = $this->load_history( $conversation_id );

		// 4. Context and tools.
		$admin_page = isset( $options['admin_page'] ) ? $options['admin_page'] : '';
		$post_id    = isset( $options['post_id'] ) ? (int) $options['post_id'] : 0;
		$context    = Context_Collector::get_instance()->collect( $user_id, $admin_page, $post_id );
		$actions    = Action_Registry::get_instance()->get_tool_definitions();

		// 5. Select model.
		$model = $this->resolve_model( $options, $user_message, $history );

		// 6. Build prompt.
		$prompt_builder = Prompt_Builder::get_instance();
		$system_prompt  = $prompt_builder->build_system_prompt( $context );
		$messages       = $prompt_builder->build_messages( $system_prompt, $history, $user_message );
		$tools          = $prompt_builder->build_tool_definitions( $actions );

		// Save user message to DB.
		$this->save_message( $conversation_id, 'user', $user_message );

		// 7. Resolve temperature and max_tokens based on context.
		$has_tools   = ! empty( $tools );
		$in_editor   = ! empty( $options['post_id'] );
		$temperature = $has_tools ? 0.2 : 0.7;

		if ( $in_editor && $has_tools ) {
			// Editor context: AI generates large block JSON structures.
			// Needs headroom for multi-section pages with chunked tool calls.
			$max_tokens = 32768;
		} elseif ( $has_tools ) {
			// Admin drawer: needs room for create_post + insert_blocks with full page content.
			$max_tokens = 16384;
		} else {
			$max_tokens = 4096;
		}

		// 8. Stream loop.
		$client     = $this->get_ai_client();
		$used_model = $model;

		for ( $iteration = 0; $iteration < self::MAX_TOOL_ITERATIONS; $iteration++ ) {
			// Reset execution timer — each AI call can take 30+ seconds,
			// and multi-iteration tool loops would exceed PHP's max_execution_time.
			set_time_limit( 120 );

			$accumulated_content = '';
			$tool_call_buffer    = [];
			$finish_reason       = '';

			$stream_callback = function ( $chunk ) use ( $callback, &$accumulated_content, &$tool_call_buffer, &$finish_reason ) {
				switch ( $chunk['type'] ) {
					case 'content':
						$accumulated_content .= $chunk['content'];
						// Pass content through to caller.
						if ( $callback ) {
							call_user_func( $callback, $chunk );
						}
						break;

					case 'tool_call':
						// Buffer tool call fragments — do NOT forward to caller.
						$index = isset( $chunk['index'] ) ? (int) $chunk['index'] : 0;

						if ( ! isset( $tool_call_buffer[ $index ] ) ) {
							$tool_call_buffer[ $index ] = [
								'id'       => '',
								'type'     => 'function',
								'function' => [
									'name'      => '',
									'arguments' => '',
								],
							];
						}

						if ( ! empty( $chunk['id'] ) ) {
							$tool_call_buffer[ $index ]['id'] = $chunk['id'];
						}
						if ( ! empty( $chunk['function']['name'] ) ) {
							$tool_call_buffer[ $index ]['function']['name'] .= $chunk['function']['name'];
						}
						if ( ! empty( $chunk['function']['arguments'] ) ) {
							$tool_call_buffer[ $index ]['function']['arguments'] .= $chunk['function']['arguments'];
						}
						break;

					case 'finish':
						$finish_reason = $chunk['finish_reason'];
						break;

					case 'error':
						if ( $callback ) {
							call_user_func( $callback, $chunk );
						}
						break;

					case 'done':
						// Stream complete signal — handled after curl returns.
						break;
				}
			};

			$result = $this->retry_api_call(
				function ( $m ) use ( $client, $messages, $tools, $stream_callback, $temperature, $max_tokens ) {
					return $client->stream( $messages, $m, $tools, $stream_callback, $temperature, $max_tokens );
				},
				$iteration,
				$model,
				$used_model
			);

			if ( is_wp_error( $result ) ) {
				// Emit error to client before returning.
				if ( $callback ) {
					call_user_func( $callback, [
						'type'    => 'error',
						'message' => $result->get_error_message(),
					] );
				}
				return $result;
			}

			// Handle tool calls from stream.
			if ( ! empty( $tool_call_buffer ) ) {
				$tool_calls = array_values( $tool_call_buffer );

				// Save assistant message with tool calls.
				$this->save_message(
					$conversation_id,
					'assistant',
					$accumulated_content,
					$used_model,
					0,
					[ 'tool_calls' => $tool_calls ]
				);

				// Append assistant message to context.
				$messages[] = [
					'role'       => 'assistant',
					'content'    => $accumulated_content,
					'tool_calls' => $tool_calls,
				];

				// Dispatch tool calls via shared method.
				$dispatch_results = $this->dispatch_tool_calls(
					$tool_calls,
					$conversation_id,
					$user_id,
					$messages,
					$callback
				);

				// Emit SSE chunks for client-side actions (e.g. insert_blocks).
				foreach ( $dispatch_results as $action_result ) {
					if ( ! empty( $action_result['result']['data']['execution'] )
						&& 'client' === $action_result['result']['data']['execution']
						&& $callback ) {
						call_user_func( $callback, [
							'type'   => 'action',
							'action' => $action_result['name'],
							'data'   => $action_result['result']['data'],
						] );
					}
				}

				// Continue loop — AI needs to process tool results.
				continue;
			}

			// No tool calls — check for empty response.
			if ( empty( trim( $accumulated_content ) ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'WP Agent: AI returned empty response (no content, no tool calls)' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				if ( $callback ) {
					call_user_func( $callback, [
						'type'    => 'error',
						'message' => 'The AI did not generate a response. Please try again.',
					] );
				}
			}

			// Save final assistant message and finish.
			$this->save_message( $conversation_id, 'assistant', $accumulated_content, $used_model );
			$this->update_conversation( $conversation_id, $used_model, 0 );

			// Signal done to caller.
			if ( $callback ) {
				call_user_func( $callback, [
					'type'            => 'done',
					'conversation_id' => $conversation_id,
				] );
			}

			break;
		}

		return true;
	}

	/**
	 * Dispatch tool calls from an AI response.
	 *
	 * Shared by both handle() and handle_stream() to ensure consistent
	 * dispatch logic, logging, and message persistence.
	 *
	 * @since 1.0.0
	 *
	 * @param array         $tool_calls      Array of tool call objects from the AI.
	 * @param int           $conversation_id Conversation ID.
	 * @param int           $user_id         WordPress user ID.
	 * @param array         &$messages       Messages array (modified in place).
	 * @param callable|null $callback        Optional SSE callback for progress events.
	 * @return array Actions taken [{name, params, result}, ...].
	 */
	private function dispatch_tool_calls( array $tool_calls, $conversation_id, $user_id, array &$messages, $callback = null ) {
		$actions_taken = [];
		$total_calls   = count( $tool_calls );
		$call_index    = 0;

		foreach ( $tool_calls as $tool_call ) {
			$call_index++;
			$fn_name = isset( $tool_call['function']['name'] ) ? $tool_call['function']['name'] : '';
			$fn_args = isset( $tool_call['function']['arguments'] ) ? $tool_call['function']['arguments'] : '{}';
			$call_id = isset( $tool_call['id'] ) ? $tool_call['id'] : '';
			$params  = json_decode( $fn_args, true );

			if ( ! is_array( $params ) ) {
				// Malformed JSON — likely the response was truncated.
				$result = [
					'success'    => false,
					'error_code' => 'malformed_arguments',
					'message'    => 'Tool call arguments were malformed or truncated by the model.',
					'recovery'   => 'Split the operation into smaller parts. For insert_blocks: use 2-3 sections per call (first call with position "replace", subsequent with "append"). For other tools: simplify the parameters and try again.',
				];

				$actions_taken[] = [
					'name'   => $fn_name,
					'params' => [],
					'result' => $result,
				];

				$this->log_action( $user_id, $conversation_id, $fn_name, [], $result );

				$tool_result_json = wp_json_encode( $result );

				$this->save_message(
					$conversation_id,
					'tool',
					$tool_result_json,
					'',
					0,
					[ 'tool_call_id' => $call_id, 'action_name' => $fn_name ]
				);

				$messages[] = [
					'role'         => 'tool',
					'tool_call_id' => $call_id,
					'content'      => $tool_result_json,
				];

				continue;
			}

			// Inject conversation_id for undo_action so it knows which conversation to query.
			if ( 'undo_action' === $fn_name && ! isset( $params['conversation_id'] ) ) {
				$params['conversation_id'] = $conversation_id;
			}

			// Capture checkpoint before reversible actions execute.
			$checkpoint_id    = 0;
			$checkpoint_data  = null;
			$action_obj       = Action_Registry::get_instance()->get_action( $fn_name );
			$checkpoint_mgr   = Checkpoint_Manager::get_instance();

			if ( $action_obj && $action_obj->is_reversible() ) {
				$checkpoint_data = $checkpoint_mgr->capture_before( $fn_name, $params );
				if ( $checkpoint_data ) {
					$checkpoint_id = $checkpoint_mgr->save_checkpoint(
						$conversation_id,
						0,
						$fn_name,
						$checkpoint_data['entity_type'],
						$checkpoint_data['entity_id'],
						$checkpoint_data['snapshot']
					);
				}
			}

			// Emit progress event before action execution.
			if ( $callback ) {
				call_user_func( $callback, [
					'type'   => 'progress',
					'stage'  => 'action_start',
					'action' => $fn_name,
					'index'  => $call_index,
					'total'  => $total_calls,
				] );
			}

			try {
				$result = Action_Registry::get_instance()->dispatch( $fn_name, $params );

				if ( is_wp_error( $result ) ) {
					$error_message = $result->get_error_message();
					$error_code    = $result->get_error_code();

					$tool_result = [
						'success'    => false,
						'message'    => $error_message,
						'error_code' => $error_code,
						'recovery'   => $this->get_recovery_hint( $fn_name, $error_code, $error_message ),
					];
				} else {
					$tool_result = $result;
				}
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'WP Agent: Action "%s" threw exception: %s', $fn_name, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				$tool_result = [
					'success'    => false,
					'message'    => 'Action encountered an unexpected error: ' . $e->getMessage(),
					'error_code' => 'action_exception',
					'recovery'   => $this->get_recovery_hint( $fn_name, 'action_exception', $e->getMessage() ),
				];
			}

			// For creation actions, update entity_id on success or clean up on failure.
			if ( $checkpoint_id && ! empty( $checkpoint_data['snapshot']['_creation'] ) ) {
				if ( ! empty( $tool_result['success'] ) && ! empty( $tool_result['data'] ) ) {
					$created_id = $this->extract_created_entity_id( $fn_name, $tool_result['data'] );
					if ( $created_id ) {
						$checkpoint_mgr->update_entity_id( $checkpoint_id, $created_id );
					} else {
						$checkpoint_mgr->delete_checkpoint( $checkpoint_id );
					}
				} else {
					$checkpoint_mgr->delete_checkpoint( $checkpoint_id );
				}
			}

			// Emit progress event after action execution.
			$action_success = ! empty( $tool_result['success'] );
			if ( $callback ) {
				$progress_event = [
					'type'    => 'progress',
					'stage'   => 'action_complete',
					'action'  => $fn_name,
					'index'   => $call_index,
					'total'   => $total_calls,
					'success' => $action_success,
				];
				if ( ! $action_success && ! empty( $tool_result['message'] ) ) {
					$progress_event['error'] = $tool_result['message'];
				}
				call_user_func( $callback, $progress_event );
			}

			$actions_taken[] = [
				'name'   => $fn_name,
				'params' => $params,
				'result' => $tool_result,
			];

			// Log to history table.
			$this->log_action( $user_id, $conversation_id, $fn_name, $params, $tool_result );

			$tool_result_json = wp_json_encode( $tool_result );

			// Save tool result message.
			$this->save_message(
				$conversation_id,
				'tool',
				$tool_result_json,
				'',
				0,
				[ 'tool_call_id' => $call_id, 'action_name' => $fn_name ]
			);

			// Append tool result to context.
			$messages[] = [
				'role'         => 'tool',
				'tool_call_id' => $call_id,
				'content'      => $tool_result_json,
			];
		}

		return $actions_taken;
	}

	/**
	 * Truncate a user message to the maximum allowed length.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Raw user message.
	 * @return string Truncated message.
	 */
	private function truncate_message( $message ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( (string) $message, 0, self::MAX_MESSAGE_LENGTH, 'UTF-8' );
		}
		return substr( (string) $message, 0, self::MAX_MESSAGE_LENGTH );
	}

	/**
	 * Ensure a conversation exists. Creates one if needed.
	 *
	 * Verifies ownership when a conversation_id is provided to prevent
	 * users from accessing other users' conversations.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $conversation_id Existing ID or null.
	 * @param int      $user_id         WordPress user ID.
	 * @param array    $options         Options with optional post_id.
	 * @return int|\WP_Error Conversation ID or WP_Error.
	 */
	private function ensure_conversation( $conversation_id, $user_id, array $options ) {
		if ( ! empty( $conversation_id ) ) {
			if ( ! $this->user_owns_conversation( (int) $conversation_id, (int) $user_id ) ) {
				return new \WP_Error(
					'forbidden',
					__( 'You do not have access to this conversation.', 'wp-agent' ),
					[ 'status' => 403 ]
				);
			}
			return (int) $conversation_id;
		}

		return $this->create_conversation( $user_id, $options );
	}

	/**
	 * Check if a user owns a conversation.
	 *
	 * @since 1.0.0
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $user_id         WordPress user ID.
	 * @return bool True if the user owns the conversation.
	 */
	private function user_owns_conversation( $conversation_id, $user_id ) {
		global $wpdb;

		$tables = Database::get_table_names();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$owner_id = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT user_id FROM {$tables['conversations']} WHERE id = %d LIMIT 1",
				$conversation_id
			)
		);

		return null !== $owner_id && (int) $owner_id === $user_id;
	}

	/**
	 * Create a new conversation row.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $options Options with optional post_id.
	 * @return int|\WP_Error New conversation ID or WP_Error.
	 */
	private function create_conversation( $user_id, array $options ) {
		global $wpdb;

		$tables  = Database::get_table_names();
		$post_id = isset( $options['post_id'] ) ? (int) $options['post_id'] : null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$tables['conversations'],
			[
				'user_id'    => (int) $user_id,
				'post_id'    => $post_id,
				'status'     => 'active',
				'created_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%s', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			return new \WP_Error( 'db_error', __( 'Failed to create conversation.', 'wp-agent' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Load conversation history (last N messages).
	 *
	 * @since 1.0.0
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return array Array of message entries for Prompt_Builder::build_messages().
	 */
	private function load_history( $conversation_id ) {
		global $wpdb;

		$tables = Database::get_table_names();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT role, content, metadata FROM {$tables['messages']}
				WHERE conversation_id = %d
				ORDER BY id DESC
				LIMIT %d",
				$conversation_id,
				self::MAX_HISTORY_MESSAGES
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return [];
		}

		// Reverse to chronological order.
		$rows = array_reverse( $rows );

		$history = [];
		foreach ( $rows as $row ) {
			$entry = [
				'role'    => $row['role'],
				'content' => $row['content'],
			];

			if ( ! empty( $row['metadata'] ) ) {
				$meta = json_decode( $row['metadata'], true );
				if ( is_array( $meta ) ) {
					if ( ! empty( $meta['tool_calls'] ) ) {
						$entry['tool_calls'] = $meta['tool_calls'];
					}
					if ( ! empty( $meta['tool_call_id'] ) ) {
						$entry['tool_call_id'] = $meta['tool_call_id'];
					}
				}
			}

			$history[] = $entry;
		}

		return $history;
	}

	/**
	 * Save a message to the messages table.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $role            Message role (user, assistant, tool).
	 * @param string $content         Message content.
	 * @param string $model           Model ID (optional).
	 * @param int    $tokens          Token count (optional).
	 * @param array  $metadata        Additional metadata (optional).
	 * @return int|false Inserted row ID or false on failure.
	 */
	private function save_message( $conversation_id, $role, $content, $model = '', $tokens = 0, array $metadata = [] ) {
		global $wpdb;

		$tables = Database::get_table_names();

		$meta_json = ! empty( $metadata ) ? wp_json_encode( $metadata ) : null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$tables['messages'],
			[
				'conversation_id' => (int) $conversation_id,
				'role'            => sanitize_text_field( $role ),
				'content'         => $content,
				'metadata'        => $meta_json,
				'tokens'          => (int) $tokens,
				'model'           => sanitize_text_field( $model ),
				'created_at'      => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%s', '%d', '%s', '%s' ]
		);

		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Log an action execution to the history table.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id         WordPress user ID.
	 * @param int    $conversation_id Conversation ID.
	 * @param string $action_name     Action name.
	 * @param array  $params          Action parameters.
	 * @param array  $result          Execution result.
	 * @return void
	 */
	private function log_action( $user_id, $conversation_id, $action_name, array $params, array $result ) {
		global $wpdb;

		$tables  = Database::get_table_names();
		$status  = ! empty( $result['success'] ) ? 'success' : 'error';
		$message = isset( $result['message'] ) ? $result['message'] : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$tables['history'],
			[
				'user_id'         => (int) $user_id,
				'conversation_id' => (int) $conversation_id,
				'action_type'     => sanitize_text_field( $action_name ),
				'action_data'     => wp_json_encode( $params ),
				'result_status'   => sanitize_text_field( $status ),
				'result_message'  => sanitize_text_field( $message ),
				'created_at'      => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Update conversation metadata after a response.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $model           Model ID used.
	 * @param int    $tokens_used     Additional tokens consumed.
	 * @return void
	 */
	private function update_conversation( $conversation_id, $model, $tokens_used ) {
		global $wpdb;

		$tables = Database::get_table_names();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$tables['conversations']}
				SET model = %s, tokens_used = tokens_used + %d, updated_at = %s
				WHERE id = %d",
				sanitize_text_field( $model ),
				(int) $tokens_used,
				current_time( 'mysql', true ),
				(int) $conversation_id
			)
		);
	}

	/**
	 * Resolve which model to use.
	 *
	 * Priority: explicit option > auto-route based on message complexity.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $options      Options with optional model override.
	 * @param string $user_message The user's message.
	 * @param array  $history      Conversation history.
	 * @return string OpenRouter model ID.
	 */
	/**
	 * Get the appropriate AI client based on backend setting.
	 *
	 * Returns AI_Client_Adapter when providers are configured,
	 * otherwise falls back to Open_Router_Client (default).
	 *
	 * @since 1.0.0
	 * @return Open_Router_Client|AI_Client_Adapter
	 */
	private function get_ai_client() {
		$backend = get_option( 'wp_agent_ai_backend', 'openrouter' );

		if ( 'providers' === $backend ) {
			$adapter    = AI_Client_Adapter::get_instance();
			$configured = $adapter->get_configured_providers();

			// At least one provider must have a key.
			if ( in_array( true, $configured, true ) ) {
				return $adapter;
			}
		}

		return Open_Router_Client::get_instance();
	}

	private function resolve_model( array $options, $user_message, array $history ) {
		if ( ! empty( $options['model'] ) ) {
			$router = Model_Router::get_instance();
			if ( $router->is_valid_model( $options['model'] ) ) {
				return sanitize_text_field( $options['model'] );
			}
		}

		return Model_Router::get_instance()->select_model( $user_message, $history );
	}

	/**
	 * Generate a recovery hint for a failed action.
	 *
	 * Returns an actionable suggestion the AI can use to explain the failure
	 * and propose an alternative approach to the user.
	 *
	 * @since 1.1.0
	 *
	 * @param string $action_name  The action that failed.
	 * @param string $error_code   WP_Error code.
	 * @param string $error_message The error message.
	 * @return string Recovery hint for the AI.
	 */
	private function get_recovery_hint( $action_name, $error_code, $error_message ) {
		// Action-specific recovery hints.
		$action_hints = [
			'insert_blocks'    => 'If blocks were truncated, split into smaller chunks (2-3 sections per call). If JSON was malformed, simplify the block structure.',
			'install_plugin'   => 'Verify the plugin slug is correct using list_plugins. The plugin may already be installed or the slug may be misspelled.',
			'activate_plugin'  => 'Check if the plugin is installed first with list_plugins. It may need to be installed before activation.',
			'deactivate_plugin' => 'Verify the plugin is currently active with list_plugins.',
			'create_post'      => 'Check if the post type exists and the user has permission to create it.',
			'edit_post'        => 'Verify the post ID exists by searching for it. The post may have been deleted.',
			'delete_post'      => 'Confirm the post ID exists and the user has permission to delete it.',
			'import_media'     => 'The URL may be inaccessible. Try a different image URL or use search_media to find existing images.',
			'manage_menus'     => 'Check the menu exists first with operation "list". Menu names are case-sensitive.',
			'update_settings'  => 'Verify the setting key is valid. Use site_health to check current configuration.',
			'get_pattern'      => 'The pattern slug may be wrong. Call list_patterns first to see available patterns and their exact slugs.',
			'screenshot_page'  => 'The page may not be published or accessible. Ensure the post is saved and has a permalink.',
			'manage_seo'       => 'No SEO plugin detected. The native fallback handles basic meta. Suggest installing Yoast or Rank Math for full SEO features.',
			'generate_image'   => 'Image generation may have failed due to API limits. Try using search_media or import_media as alternatives.',
			'web_search'       => 'Search may have failed due to API key issues. Check WP Agent > Settings to verify the Tavily API key is configured.',
		];

		if ( isset( $action_hints[ $action_name ] ) ) {
			return $action_hints[ $action_name ];
		}

		// Error-code-based hints.
		if ( 'rest_forbidden' === $error_code || 'forbidden' === $error_code ) {
			return 'The current user lacks permission for this action. Check the user role.';
		}

		if ( 'not_found' === $error_code || 'rest_post_invalid_id' === $error_code ) {
			return 'The requested resource was not found. Verify the ID or slug exists.';
		}

		return 'Explain the error to the user in simple terms and suggest an alternative approach.';
	}

	/**
	 * Check if a WP_Error is a transient error that can be retried.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_Error $error The error to check.
	 * @return bool True if the error is transient.
	 */
	private function is_retryable_error( $error ) {
		$code = $error->get_error_code();

		// Direct match.
		if ( in_array( $code, self::RETRYABLE_ERRORS, true ) ) {
			return true;
		}

		// Check for common transient patterns in the message.
		$message = strtolower( $error->get_error_message() );
		$patterns = [ 'timeout', 'rate limit', '429', '502', '503', '504', 'connection' ];

		foreach ( $patterns as $pattern ) {
			if ( false !== strpos( $message, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retry an API call with exponential backoff.
	 *
	 * @since 1.1.0
	 *
	 * @param callable $api_call Function that returns a response or WP_Error.
	 * @param int      $iteration Current tool-loop iteration (for fallback on first).
	 * @param string   &$model Current model (may be changed to fallback).
	 * @param string   &$used_model Tracking variable for used model.
	 * @return array|\WP_Error API response or error after exhausting retries.
	 */
	private function retry_api_call( callable $api_call, $iteration, &$model, &$used_model ) {
		$response = $api_call( $model );

		// First try: attempt model fallback.
		if ( is_wp_error( $response ) && 0 === $iteration ) {
			$fallback_model = Model_Router::get_instance()->get_fallback( $model );
			if ( ! empty( $fallback_model ) ) {
				$response = $api_call( $fallback_model );
				if ( ! is_wp_error( $response ) ) {
					$model      = $fallback_model;
					$used_model = $fallback_model;
					return $response;
				}
			}
		}

		// Retry transient errors with backoff.
		if ( is_wp_error( $response ) && $this->is_retryable_error( $response ) ) {
			for ( $retry = 1; $retry <= self::MAX_RETRIES; $retry++ ) {
				sleep( $retry ); // 1s, 2s backoff.
				$response = $api_call( $model );
				if ( ! is_wp_error( $response ) ) {
					return $response;
				}
				if ( ! $this->is_retryable_error( $response ) ) {
					break; // Non-retryable error, stop.
				}
			}
		}

		return $response;
	}

	/**
	 * Extract the created entity ID from a creation action's result data.
	 *
	 * Different actions return the ID under different keys. This method
	 * normalizes the extraction.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action_name The action that was executed.
	 * @param array  $data        The result data from the action.
	 * @return int The entity ID, or 0 if not found.
	 */
	private function extract_created_entity_id( $action_name, array $data ) {
		switch ( $action_name ) {
			case 'create_post':
			case 'create_pattern':
				return isset( $data['post_id'] ) ? absint( $data['post_id'] ) : 0;

			case 'clone_post':
				return isset( $data['new_post_id'] ) ? absint( $data['new_post_id'] ) : 0;

			case 'create_user':
				return isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0;

			case 'import_media':
				return isset( $data['id'] ) ? absint( $data['id'] ) : 0;

			case 'generate_image':
				return isset( $data['attachment_id'] ) ? absint( $data['attachment_id'] ) : 0;

			case 'install_plugin':
				// Plugins don't have a numeric ID — use 0.
				return 0;

			default:
				return 0;
		}
	}
}
