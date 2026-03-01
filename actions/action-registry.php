<?php
/**
 * Action Registry.
 *
 * Stores registered actions, checks user capabilities, and dispatches
 * tool calls from the AI. Uses lazy initialization via the
 * 'wp_agent_register_actions' hook so plugins can register actions.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Action_Registry
 *
 * @since 1.0.0
 */
class Action_Registry {

	/**
	 * Instance
	 *
	 * @access private
	 * @var Action_Registry|null Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Registered actions keyed by name.
	 *
	 * @var array<string, Action_Interface>
	 */
	private $actions = [];

	/**
	 * Whether lazy initialization has fired.
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return Action_Registry Initialized object of class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register an action.
	 *
	 * @since 1.0.0
	 *
	 * @param Action_Interface $action The action to register.
	 * @return void
	 */
	public function register( Action_Interface $action ) {
		$this->actions[ $action->get_name() ] = $action;
	}

	/**
	 * Get tool definitions for the AI, filtered by current user capabilities.
	 *
	 * Returns an array of action schemas suitable for passing to
	 * Prompt_Builder::build_tool_definitions().
	 *
	 * @since 1.0.0
	 * @return array Array of {name, description, parameters} for permitted actions.
	 */
	public function get_tool_definitions() {
		$this->maybe_init();

		$definitions = [];

		foreach ( $this->actions as $action ) {
			if ( ! current_user_can( $action->get_capabilities_required() ) ) {
				continue;
			}

			$definitions[] = [
				'name'        => $action->get_name(),
				'description' => $action->get_description(),
				'parameters'  => $action->get_parameters(),
			];
		}

		return $definitions;
	}

	/**
	 * Dispatch an action by name.
	 *
	 * Checks that the action exists and the current user has the required
	 * capability before executing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name   Action name (e.g. 'create_post').
	 * @param array  $params Parameters to pass to the action.
	 * @return array|\WP_Error Execution result array or WP_Error.
	 */
	public function dispatch( $name, array $params ) {
		$this->maybe_init();

		$name = sanitize_text_field( $name );

		if ( ! isset( $this->actions[ $name ] ) ) {
			return new \WP_Error(
				'unknown_action',
				sprintf(
					/* translators: %s: action name */
					__( 'Unknown action: %s', 'wp-agent' ),
					$name
				)
			);
		}

		$action = $this->actions[ $name ];

		if ( ! current_user_can( $action->get_capabilities_required() ) ) {
			return new \WP_Error(
				'insufficient_permissions',
				sprintf(
					/* translators: %s: action name */
					__( 'You do not have permission to execute: %s', 'wp-agent' ),
					$name
				),
				[ 'status' => 403 ]
			);
		}

		return $action->execute( $params );
	}

	/**
	 * Get a registered action by name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Action name.
	 * @return Action_Interface|null The action or null if not found.
	 */
	public function get_action( $name ) {
		$this->maybe_init();

		return isset( $this->actions[ $name ] ) ? $this->actions[ $name ] : null;
	}

	/**
	 * Lazy initialization.
	 *
	 * Fires the 'wp_agent_register_actions' hook on first access so
	 * plugins and extensions can register their actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function maybe_init() {
		if ( $this->initialized ) {
			return;
		}

		$this->initialized = true;

		/**
		 * Fires when the action registry is ready for registrations.
		 *
		 * @since 1.0.0
		 *
		 * @param Action_Registry $registry The action registry instance.
		 */
		do_action( 'wp_agent_register_actions', $this );
	}
}
