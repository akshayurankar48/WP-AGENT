<?php
/**
 * Settings REST Controller.
 *
 * Handles GET/POST /wp-agent/v1/settings for managing plugin configuration.
 * Requires manage_options capability (admin-only).
 *
 * @package WPAgent\REST
 * @since   1.0.0
 */

namespace WPAgent\REST;

use WPAgent\AI\Open_Router_Client;
use WPAgent\AI\AI_Client_Adapter;
use WPAgent\AI\Model_Router;
use WPAgent\AI\Rate_Limiter;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings_Controller
 *
 * @since 1.0.0
 */
class Settings_Controller {

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
	const ROUTE = '/settings';

	/**
	 * Option key for default model.
	 *
	 * @var string
	 */
	const MODEL_OPTION = 'wp_agent_default_model';

	/**
	 * Option key for allowed roles.
	 *
	 * @var string
	 */
	const ROLES_OPTION = 'wp_agent_allowed_roles';

	/**
	 * Option key for the encrypted Tavily API key.
	 *
	 * @var string
	 */
	const TAVILY_KEY_OPTION = 'wp_agent_tavily_api_key';

	/**
	 * Option key for brand presets.
	 *
	 * @var string
	 */
	const BRAND_OPTION = 'wp_agent_brand_presets';

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
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'check_permissions' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'check_permissions' ],
					'args'                => $this->get_update_args(),
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/verify-provider',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'verify_provider' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args'                => [
					'provider' => [
						'type'     => 'string',
						'required' => true,
						'enum'     => [ 'anthropic', 'openai', 'google' ],
					],
					'api_key'  => [
						'type'     => 'string',
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * Permission check — manage_options required.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return bool|\ WP_Error
	 */
	public function check_permissions( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage settings.', 'wp-agent' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * GET /wp-agent/v1/settings
	 *
	 * Returns current plugin configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function get_settings( $request ) {
		$client  = Open_Router_Client::get_instance();
		$adapter = AI_Client_Adapter::get_instance();
		$router  = Model_Router::get_instance();

		return rest_ensure_response( [
			'has_api_key'          => $client->has_api_key(),
			'has_tavily_key'       => ! empty( get_option( self::TAVILY_KEY_OPTION, '' ) ),
			'default_model'        => $router->get_default_model(),
			'allowed_roles'        => get_option( self::ROLES_OPTION, [ 'administrator' ] ),
			'brand'                => get_option( self::BRAND_OPTION, [] ),
			'rate_limit'           => (int) get_option( 'wp_agent_rate_limit', Rate_Limiter::DEFAULT_MINUTE_LIMIT ),
			'daily_limit'          => (int) get_option( 'wp_agent_daily_limit', Rate_Limiter::DEFAULT_DAILY_LIMIT ),
			'ai_backend'           => get_option( 'wp_agent_ai_backend', 'openrouter' ),
			'configured_providers' => $adapter->get_configured_providers(),
			'preferred_provider'   => get_option( 'wp_agent_preferred_provider', [ 'anthropic', 'openai', 'google' ] ),
		] );
	}

	/**
	 * POST /wp-agent/v1/settings
	 *
	 * Saves settings. Only provided fields are updated.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings( $request ) {
		$updated = [];

		// API key — validate and encrypt before storing.
		if ( $request->has_param( 'api_key' ) ) {
			$api_key = $request->get_param( 'api_key' );
			$result  = $this->save_api_key( $api_key );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$updated['api_key'] = true;
		}

		// Default model — validate against known models.
		if ( $request->has_param( 'default_model' ) ) {
			$model  = sanitize_text_field( $request->get_param( 'default_model' ) );
			$router = Model_Router::get_instance();

			if ( ! $router->is_valid_model( $model ) ) {
				return new \WP_Error(
					'invalid_model',
					__( 'The specified model is not available.', 'wp-agent' ),
					[ 'status' => 400 ]
				);
			}

			update_option( self::MODEL_OPTION, $model );
			$updated['default_model'] = $model;
		}

		// Tavily API key — encrypt before storing.
		if ( $request->has_param( 'tavily_api_key' ) ) {
			$tavily_key = $request->get_param( 'tavily_api_key' );
			$result     = $this->save_tavily_key( $tavily_key );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$updated['tavily_api_key'] = true;
		}

		// Allowed roles — validate against WordPress roles.
		if ( $request->has_param( 'allowed_roles' ) ) {
			$roles  = $request->get_param( 'allowed_roles' );
			$result = $this->save_allowed_roles( $roles );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$updated['allowed_roles'] = $roles;
		}

		// Brand presets — sanitize and store.
		if ( $request->has_param( 'brand' ) ) {
			$brand  = $request->get_param( 'brand' );
			$result = $this->save_brand_settings( $brand );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$updated['brand'] = true;
		}

		// AI backend selection.
		if ( $request->has_param( 'ai_backend' ) ) {
			$backend = sanitize_text_field( $request->get_param( 'ai_backend' ) );
			if ( in_array( $backend, [ 'openrouter', 'providers' ], true ) ) {
				update_option( 'wp_agent_ai_backend', $backend );
				$updated['ai_backend'] = $backend;
			}
		}

		// Provider API keys — validate, encrypt, store.
		foreach ( [ 'anthropic', 'openai', 'google' ] as $provider ) {
			$param_name = $provider . '_api_key';
			if ( $request->has_param( $param_name ) ) {
				$result = $this->save_provider_key( $provider, $request->get_param( $param_name ) );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				$updated[ $param_name ] = true;
			}
		}

		// Preferred provider order.
		if ( $request->has_param( 'preferred_provider' ) ) {
			$order = $request->get_param( 'preferred_provider' );
			if ( is_array( $order ) ) {
				$valid    = [ 'anthropic', 'openai', 'google' ];
				$filtered = array_values( array_intersect( $order, $valid ) );
				if ( ! empty( $filtered ) ) {
					update_option( 'wp_agent_preferred_provider', $filtered );
					$updated['preferred_provider'] = $filtered;
				}
			}
		}

		return rest_ensure_response( [
			'success' => true,
			'updated' => $updated,
		] );
	}

	/**
	 * POST /wp-agent/v1/verify-provider
	 *
	 * Validates a provider API key without saving it.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function verify_provider( $request ) {
		$provider = sanitize_text_field( $request->get_param( 'provider' ) );
		$api_key  = $request->get_param( 'api_key' );

		$adapter    = AI_Client_Adapter::get_instance();
		$validation = $adapter->validate_api_key( $api_key, $provider );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		return rest_ensure_response( [
			'success'  => true,
			'provider' => $provider,
			'message'  => sprintf(
				/* translators: %s: Provider name */
				__( '%s API key is valid.', 'wp-agent' ),
				ucfirst( $provider )
			),
		] );
	}

	/**
	 * Validate and save an API key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key The plaintext API key.
	 * @return true|\WP_Error
	 */
	private function save_api_key( $api_key ) {
		$api_key = trim( $api_key );

		if ( empty( $api_key ) ) {
			// Allow clearing the key.
			delete_option( Open_Router_Client::API_KEY_OPTION );
			return true;
		}

		// Validate with OpenRouter.
		$client     = Open_Router_Client::get_instance();
		$validation = $client->validate_api_key( $api_key );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Encrypt and store.
		$encrypted = Open_Router_Client::encrypt_api_key( $api_key );

		if ( false === $encrypted ) {
			return new \WP_Error(
				'encryption_failed',
				__( 'Failed to encrypt the API key. OpenSSL may not be available.', 'wp-agent' ),
				[ 'status' => 500 ]
			);
		}

		update_option( Open_Router_Client::API_KEY_OPTION, $encrypted );

		return true;
	}

	/**
	 * Validate and save allowed roles.
	 *
	 * @since 1.0.0
	 *
	 * @param array $roles Array of role slugs.
	 * @return true|\WP_Error
	 */
	private function save_allowed_roles( $roles ) {
		if ( ! is_array( $roles ) ) {
			return new \WP_Error(
				'invalid_roles',
				__( 'Allowed roles must be an array.', 'wp-agent' ),
				[ 'status' => 400 ]
			);
		}

		$valid_roles = array_keys( wp_roles()->get_names() );
		$sanitized   = [];

		foreach ( $roles as $role ) {
			$role = sanitize_text_field( $role );
			if ( in_array( $role, $valid_roles, true ) ) {
				$sanitized[] = $role;
			}
		}

		// Always include administrator.
		if ( ! in_array( 'administrator', $sanitized, true ) ) {
			array_unshift( $sanitized, 'administrator' );
		}

		update_option( self::ROLES_OPTION, $sanitized );

		return true;
	}

	/**
	 * Validate and save a Tavily API key.
	 *
	 * @since 1.1.0
	 *
	 * @param string $api_key The plaintext Tavily API key.
	 * @return true|\WP_Error
	 */
	private function save_tavily_key( $api_key ) {
		$api_key = trim( $api_key );

		if ( empty( $api_key ) ) {
			// Allow clearing the key.
			delete_option( self::TAVILY_KEY_OPTION );
			return true;
		}

		// Basic format validation — Tavily keys start with "tvly-".
		if ( 0 !== strpos( $api_key, 'tvly-' ) ) {
			return new \WP_Error(
				'invalid_tavily_key',
				__( 'Invalid Tavily API key format. Keys should start with "tvly-".', 'wp-agent' ),
				[ 'status' => 400 ]
			);
		}

		// Encrypt and store using the same encryption as OpenRouter keys.
		$encrypted = Open_Router_Client::encrypt_api_key( $api_key );

		if ( false === $encrypted ) {
			return new \WP_Error(
				'encryption_failed',
				__( 'Failed to encrypt the Tavily API key. OpenSSL may not be available.', 'wp-agent' ),
				[ 'status' => 500 ]
			);
		}

		update_option( self::TAVILY_KEY_OPTION, $encrypted );

		return true;
	}

	/**
	 * Validate and save brand preset settings.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $brand Brand data from the request.
	 * @return true|\WP_Error
	 */
	private function save_brand_settings( $brand ) {
		if ( ! is_array( $brand ) ) {
			return new \WP_Error(
				'invalid_brand',
				__( 'Brand settings must be an object.', 'wp-agent' ),
				[ 'status' => 400 ]
			);
		}

		$allowed_keys = [
			'brand_name',
			'tagline',
			'primary_color',
			'accent_color',
			'dark_color',
			'light_color',
			'tone',
			'font_preference',
		];

		$allowed_tones = [ '', 'professional', 'friendly', 'casual', 'authoritative', 'playful', 'minimal' ];
		$allowed_fonts = [ '', 'sans-serif', 'serif', 'monospace' ];

		$sanitized = [];

		foreach ( $allowed_keys as $key ) {
			if ( ! isset( $brand[ $key ] ) ) {
				continue;
			}

			$value = sanitize_text_field( $brand[ $key ] );

			// Validate color fields as hex codes.
			if ( str_ends_with( $key, '_color' ) && ! empty( $value ) ) {
				if ( ! preg_match( '/^#[0-9a-fA-F]{3,6}$/', $value ) ) {
					continue; // Skip invalid color values silently.
				}
			}

			// Validate enum fields.
			if ( 'tone' === $key && ! in_array( $value, $allowed_tones, true ) ) {
				continue;
			}
			if ( 'font_preference' === $key && ! in_array( $value, $allowed_fonts, true ) ) {
				continue;
			}

			// Limit text lengths.
			if ( 'brand_name' === $key ) {
				$value = substr( $value, 0, 100 );
			}
			if ( 'tagline' === $key ) {
				$value = substr( $value, 0, 200 );
			}

			if ( '' !== $value ) {
				$sanitized[ $key ] = $value;
			}
		}

		update_option( self::BRAND_OPTION, $sanitized );

		return true;
	}

	/**
	 * Define argument schema for POST /settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	/**
	 * Validate and save a provider API key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider Provider name (anthropic, openai, google).
	 * @param string $api_key  The plaintext API key.
	 * @return true|\WP_Error
	 */
	private function save_provider_key( $provider, $api_key ) {
		$api_key = trim( $api_key );
		$option  = AI_Client_Adapter::KEY_OPTIONS[ $provider ] ?? '';

		if ( empty( $option ) ) {
			return new \WP_Error( 'invalid_provider', __( 'Unknown provider.', 'wp-agent' ), [ 'status' => 400 ] );
		}

		if ( empty( $api_key ) ) {
			delete_option( $option );
			return true;
		}

		// Validate the key with the provider.
		$adapter    = AI_Client_Adapter::get_instance();
		$validation = $adapter->validate_api_key( $api_key, $provider );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Encrypt and store.
		$encrypted = Open_Router_Client::encrypt_api_key( $api_key );

		if ( false === $encrypted ) {
			return new \WP_Error(
				'encryption_failed',
				__( 'Failed to encrypt the API key. OpenSSL may not be available.', 'wp-agent' ),
				[ 'status' => 500 ]
			);
		}

		update_option( $option, $encrypted );

		return true;
	}

	private function get_update_args() {
		return [
			'api_key'             => [
				'type' => 'string',
			],
			'tavily_api_key'      => [
				'type' => 'string',
			],
			'default_model'       => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'allowed_roles'       => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'brand'               => [
				'type' => 'object',
			],
			'ai_backend'          => [
				'type' => 'string',
				'enum' => [ 'openrouter', 'providers' ],
			],
			'anthropic_api_key'   => [
				'type' => 'string',
			],
			'openai_api_key'      => [
				'type' => 'string',
			],
			'google_api_key'      => [
				'type' => 'string',
			],
			'preferred_provider'  => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
		];
	}
}
