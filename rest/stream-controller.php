<?php
/**
 * Stream REST Controller.
 *
 * SSE (Server-Sent Events) streaming endpoint. Sends AI response chunks
 * in real time as they arrive from the OpenRouter API.
 *
 * @package WPAgent\REST
 * @since   1.0.0
 */

namespace WPAgent\REST;

use WPAgent\AI\Orchestrator;

defined( 'ABSPATH' ) || exit;

/**
 * Class Stream_Controller
 *
 * @since 1.0.0
 */
class Stream_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'wp-agent/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	const ROUTE = '/stream';

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_stream' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args'                => $this->get_args(),
			]
		);
	}

	/**
	 * Permission check — edit_posts required.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return bool|\WP_Error
	 */
	public function check_permissions( $request ) {
		if ( ! current_user_can( 'edit_posts' ) || ! REST_Permissions::current_user_has_allowed_role() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to use the chat.', 'wp-agent' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * POST /wp-agent/v1/stream
	 *
	 * Opens an SSE connection and streams AI response chunks.
	 *
	 * This method bypasses WP_REST_Server::serve_request() by sending
	 * headers and data directly, then calling exit. This is the standard
	 * pattern for SSE in WordPress REST API.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return void|\WP_Error Returns WP_Error only on pre-stream failures.
	 */
	public function handle_stream( $request ) {
		$message         = $request->get_param( 'message' );
		$conversation_id = $request->get_param( 'conversation_id' );
		$user_id         = get_current_user_id();

		$options = [];

		if ( $request->has_param( 'post_id' ) ) {
			$options['post_id'] = (int) $request->get_param( 'post_id' );
		}

		if ( $request->has_param( 'model' ) ) {
			$options['model'] = sanitize_text_field( $request->get_param( 'model' ) );
		}

		// Set SSE headers.
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'X-Accel-Buffering: no' );
		header( 'Connection: keep-alive' );

		// Flush all existing output buffers.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- ob_end_flush may warn if no buffer exists.
		while ( ob_get_level() > 0 ) {
			@ob_end_flush();
		}

		// Remove PHP execution timeout for long-running streams.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- set_time_limit may be disabled.
		@set_time_limit( 0 );

		// Stream callback — sends SSE data frames to the client.
		$stream_callback = function ( $chunk ) {
			echo 'data: ' . wp_json_encode( $chunk ) . "\n\n";

			if ( ob_get_level() > 0 ) {
				ob_flush();
			}
			flush();

			// Reset timeout with each chunk to prevent timeout during active streaming.
			// Use 0 (unlimited) because tool loop iterations may have 30+ second gaps
			// between API calls, and a fixed timeout would kill the process mid-loop.
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- set_time_limit may be disabled.
			@set_time_limit( 0 );
		};

		$result = Orchestrator::get_instance()->handle_stream(
			$message,
			$user_id,
			$conversation_id ? (int) $conversation_id : null,
			$stream_callback,
			$options
		);

		// If the orchestrator returned an error before streaming started,
		// send it as an SSE error event so the client can handle it.
		if ( is_wp_error( $result ) ) {
			echo 'data: ' . wp_json_encode( [
				'type'    => 'error',
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			] ) . "\n\n";
		}

		// Send stream termination signal.
		echo "data: [DONE]\n\n";

		if ( ob_get_level() > 0 ) {
			ob_flush();
		}
		flush();

		exit;
	}

	/**
	 * Define argument schema for POST /stream.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_args() {
		return [
			'message'         => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'validate_callback' => function ( $value ) {
					if ( empty( trim( $value ) ) ) {
						return new \WP_Error(
							'empty_message',
							__( 'Message cannot be empty.', 'wp-agent' ),
							[ 'status' => 400 ]
						);
					}
					return true;
				},
			],
			'conversation_id' => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
			'post_id'         => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
			'model'           => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}
}
