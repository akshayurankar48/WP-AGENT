<?php
/**
 * MCP Server Registration.
 *
 * Exposes all JARVIS abilities as MCP tools for Claude Desktop, VS Code, etc.
 * Conditional — only loads when MCP Adapter is available.
 *
 * @package WPAgent\Integrations
 * @since   1.0.0
 */

namespace WPAgent\Integrations;

use WPAgent\Actions\Action_Registry;

defined( 'ABSPATH' ) || exit;

class MCP_Server {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_mcp_server' ] );
	}

	public function register_mcp_server() {
		if ( ! class_exists( 'WP\\MCP\\Core\\McpAdapter' ) ) {
			return;
		}

		try {
			$adapter = \WP\MCP\Core\McpAdapter::instance();
			$server  = $adapter->create_server( 'wp-agent-mcp', [
				'name'        => 'WP Agent (JARVIS)',
				'description' => 'AI-powered WordPress management — 70+ actions available as MCP tools.',
				'version'     => defined( 'WP_AGENT_VER' ) ? WP_AGENT_VER : '1.0.0',
			] );

			if ( ! $server ) {
				return;
			}

			$registry = Action_Registry::get_instance();
			$actions  = $registry->get_all_actions();

			foreach ( $actions as $name => $action ) {
				$description = method_exists( $action, 'get_description' ) ? $action->get_description() : '';
				$parameters  = method_exists( $action, 'get_parameters' ) ? $action->get_parameters() : [];
				$capability  = method_exists( $action, 'get_capabilities_required' ) ? $action->get_capabilities_required() : 'manage_options';

				$tool = new \WP\MCP\Domain\Tools\McpTool(
					$name,
					$description,
					$parameters,
					function ( $input ) use ( $registry, $name, $capability ) {
						if ( ! current_user_can( $capability ) ) {
							return [
								'success' => false,
								'message' => 'Insufficient permissions.',
							];
						}
						$result = $registry->dispatch( $name, $input );
						if ( is_wp_error( $result ) ) {
							return [
								'success' => false,
								'message' => $result->get_error_message(),
							];
						}
						return $result;
					}
				);

				$server->register_tool( $tool );
			}
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WP Agent MCP Server error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}
}
