<?php
/**
 * WordPress Abilities Bridge.
 *
 * Registers all JARVIS actions as WordPress Abilities (WP 6.9+).
 * Conditional — only loads when Abilities API is available.
 *
 * @package WPAgent\Integrations
 * @since   1.0.0
 */

namespace WPAgent\Integrations;

use WPAgent\Actions\Action_Registry;

defined( 'ABSPATH' ) || exit;

class Abilities_Bridge {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );

		// Fallback: register on init if the dedicated hook doesn't exist yet.
		if ( ! has_action( 'wp_abilities_api_init' ) ) {
			add_action( 'init', [ $this, 'register_abilities' ], 20 );
		}
	}

	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// Register category.
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category( 'wp-agent', [
				'label'       => __( 'WP Agent (JARVIS)', 'wp-agent' ),
				'description' => __( 'AI-powered WordPress management actions.', 'wp-agent' ),
			] );
		}

		$registry = Action_Registry::get_instance();
		$actions  = $registry->get_all_actions();

		foreach ( $actions as $name => $action ) {
			$description = '';
			if ( method_exists( $action, 'get_description' ) ) {
				$description = $action->get_description();
			}

			$parameters = [ 'type' => 'object', 'properties' => new \stdClass() ];
			if ( method_exists( $action, 'get_parameters' ) ) {
				$parameters = $action->get_parameters();
			}

			$capability = 'manage_options';
			if ( method_exists( $action, 'get_capabilities_required' ) ) {
				$capability = $action->get_capabilities_required();
			}

			wp_register_ability( "wp-agent/{$name}", [
				'label'               => $this->get_first_sentence( $description ),
				'description'         => $description,
				'category'            => 'wp-agent',
				'input_schema'        => $parameters,
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'data'    => [ 'type' => 'object' ],
						'message' => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => function ( $input ) use ( $registry, $name ) {
					return $registry->dispatch( $name, $input );
				},
				'permission_callback' => function () use ( $capability ) {
					return current_user_can( $capability );
				},
				'meta'                => [
					'show_in_rest' => true,
					'mcp'          => [ 'public' => true, 'type' => 'tool' ],
				],
			] );
		}
	}

	private function get_first_sentence( $text ) {
		$pos = strpos( $text, '.' );
		if ( false !== $pos && $pos < 120 ) {
			return substr( $text, 0, $pos + 1 );
		}
		return substr( $text, 0, 120 );
	}
}
