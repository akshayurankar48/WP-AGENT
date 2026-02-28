<?php
/**
 * Action Interface.
 *
 * Contract for all AI-executable actions. Each action the AI can call
 * must implement this interface to be registered in the Action_Registry.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Interface Action_Interface
 *
 * @since 1.0.0
 */
interface Action_Interface {

	/**
	 * Get the action's unique name.
	 *
	 * Used as the function name in tool calls (e.g. 'create_post').
	 * Must be alphanumeric with underscores, max 64 chars.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Get a human-readable description of what this action does.
	 *
	 * This is what the AI sees when deciding which tool to call.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * Get the JSON Schema for this action's parameters.
	 *
	 * Returned array must be a valid JSON Schema object definition.
	 * Example:
	 *   [
	 *       'type'       => 'object',
	 *       'properties' => [
	 *           'title'   => ['type' => 'string', 'description' => 'Post title'],
	 *           'content' => ['type' => 'string', 'description' => 'Post content'],
	 *       ],
	 *       'required'   => ['title'],
	 *   ]
	 *
	 * @since 1.0.0
	 * @return array JSON Schema for parameters.
	 */
	public function get_parameters(): array;

	/**
	 * Get the WordPress capability required to execute this action.
	 *
	 * The capability is checked via current_user_can() before execution.
	 * Examples: 'edit_posts', 'manage_options', 'edit_users'.
	 *
	 * @since 1.0.0
	 * @return string WordPress capability string.
	 */
	public function get_capabilities_required(): string;

	/**
	 * Whether this action is reversible (needs checkpoint snapshots).
	 *
	 * Actions that modify site data should return true so the Orchestrator
	 * can create checkpoint snapshots before execution.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_reversible(): bool;

	/**
	 * Execute the action with the given parameters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Validated parameters matching get_parameters() schema.
	 * @return array {
	 *     Execution result.
	 *
	 *     @type bool   $success Whether the action succeeded.
	 *     @type mixed  $data    Action-specific return data.
	 *     @type string $message Human-readable result description.
	 * }
	 */
	public function execute( array $params ): array;
}
