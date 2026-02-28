<?php
/**
 * Prompt Builder.
 *
 * System prompt assembly and message formatting for the OpenRouter API.
 * Handles identity, Plan-Confirm-Execute workflow, safety rules, and
 * tool definition formatting.
 *
 * @package WPAgent\AI
 * @since   1.0.0
 */

namespace WPAgent\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class Prompt_Builder
 *
 * @since 1.0.0
 */
class Prompt_Builder {

	/**
	 * Instance
	 *
	 * @access private
	 * @var Prompt_Builder|null Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return Prompt_Builder Initialized object of class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Build the system prompt.
	 *
	 * Assembles a ~1000-token prompt covering:
	 * - Agent identity and capabilities
	 * - Plan-Confirm-Execute workflow
	 * - Safety constraints and guardrails
	 * - Site context (name, URL, WP version, user role)
	 *
	 * @since 1.0.0
	 *
	 * @param array $context {
	 *     Optional. Site and user context.
	 *
	 *     @type string $site_name    Site title.
	 *     @type string $site_url     Site URL.
	 *     @type string $wp_version   WordPress version.
	 *     @type string $user_role    Current user's role.
	 *     @type string $user_name    Current user's display name.
	 *     @type string $php_version  PHP version.
	 *     @type string $locale       Site locale.
	 * }
	 * @return string The assembled system prompt.
	 */
	public function build_system_prompt( array $context = [] ) {
		$context = wp_parse_args( $context, $this->get_default_context() );

		$prompt  = $this->get_identity_section();
		$prompt .= $this->get_workflow_section();
		$prompt .= $this->get_safety_section();
		$prompt .= $this->get_context_section( $context );

		return $prompt;
	}

	/**
	 * Build the messages array for the OpenRouter API.
	 *
	 * Formats conversation history and the new user message into
	 * the OpenRouter chat completions format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $system_prompt The system prompt.
	 * @param array  $history       Conversation history. Each entry: {role: string, content: string}.
	 * @param string $user_message  The new user message.
	 * @return array Formatted messages array for the API.
	 */
	public function build_messages( $system_prompt, array $history, $user_message ) {
		$messages = [];

		// System message first.
		$messages[] = [
			'role'    => 'system',
			'content' => $system_prompt,
		];

		// Append conversation history.
		foreach ( $history as $entry ) {
			if ( empty( $entry['role'] ) || ! isset( $entry['content'] ) ) {
				continue;
			}

			$message = [
				'role'    => sanitize_text_field( $entry['role'] ),
				'content' => $entry['content'],
			];

			// Include tool_calls if present (assistant messages).
			if ( 'assistant' === $entry['role'] && ! empty( $entry['tool_calls'] ) && is_array( $entry['tool_calls'] ) ) {
				$message['tool_calls'] = $entry['tool_calls'];
			}

			// Include tool_call_id if present (tool response messages).
			if ( 'tool' === $entry['role'] && ! empty( $entry['tool_call_id'] ) ) {
				$message['tool_call_id'] = sanitize_text_field( (string) $entry['tool_call_id'] );
			}

			$messages[] = $message;
		}

		// New user message last.
		$messages[] = [
			'role'    => 'user',
			'content' => $user_message,
		];

		return $messages;
	}

	/**
	 * Build tool definitions for the OpenRouter API.
	 *
	 * Converts action schemas (internal format) to the OpenRouter
	 * function-calling format.
	 *
	 * @since 1.0.0
	 *
	 * @param array $actions Array of action definitions. Each: {
	 *     @type string $name        Function name.
	 *     @type string $description Function description.
	 *     @type array  $parameters  JSON Schema for parameters.
	 * }
	 * @return array Tool definitions in OpenRouter format.
	 */
	public function build_tool_definitions( array $actions ) {
		$tools = [];

		foreach ( $actions as $action ) {
			if ( empty( $action['name'] ) ) {
				continue;
			}

			// Validate tool name format (alphanumeric, underscores, hyphens, max 64 chars).
			if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $action['name'] ) ) {
				continue;
			}

			$tool = [
				'type'     => 'function',
				'function' => [
					'name'        => $action['name'],
					'description' => isset( $action['description'] ) ? $action['description'] : '',
				],
			];

			if ( ! empty( $action['parameters'] ) ) {
				$tool['function']['parameters'] = $action['parameters'];
			} else {
				// Default to empty object schema if no parameters defined.
				$tool['function']['parameters'] = [
					'type'       => 'object',
					'properties' => new \stdClass(),
				];
			}

			$tools[] = $tool;
		}

		return $tools;
	}

	/**
	 * Get the identity section of the system prompt.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_identity_section() {
		return "You are WP Agent, an AI-powered assistant built into WordPress. " .
			"You help site administrators manage their WordPress site through natural language conversation.\n\n" .
			"Your capabilities include:\n" .
			"- Creating, editing, and managing posts and pages\n" .
			"- Managing categories, tags, and taxonomies\n" .
			"- Updating site settings and options\n" .
			"- Managing menus and widgets\n" .
			"- Installing and managing plugins and themes\n" .
			"- Managing users and roles\n" .
			"- Querying site data and generating reports\n" .
			"- Performing bulk operations across content\n\n";
	}

	/**
	 * Get the Plan-Confirm-Execute workflow section.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_workflow_section() {
		return "## Workflow: Plan-Confirm-Execute\n\n" .
			"For every action that modifies the site, you MUST follow this workflow:\n\n" .
			"1. **Plan**: Analyze the request and describe what you intend to do. " .
			"List each action clearly with its expected outcome.\n" .
			"2. **Confirm**: Present the plan to the user and wait for explicit approval. " .
			"Never execute modifying actions without user confirmation.\n" .
			"3. **Execute**: Only after the user confirms, execute the actions one at a time. " .
			"Report the result of each action.\n\n" .
			"For read-only queries (listing posts, checking settings), you may respond directly " .
			"without the confirm step.\n\n";
	}

	/**
	 * Get the safety rules section.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_safety_section() {
		return "## Safety Rules\n\n" .
			"- Never execute destructive operations (delete, bulk update) without explicit confirmation.\n" .
			"- Never modify wp-config.php or core WordPress files.\n" .
			"- Never expose database credentials, API keys, or sensitive configuration.\n" .
			"- Never execute arbitrary PHP code or SQL queries directly.\n" .
			"- If an action fails, report the error clearly and suggest next steps. Do not retry automatically.\n" .
			"- When unsure about the scope of a request, ask for clarification before acting.\n" .
			"- Always operate within the permissions of the current user's role.\n\n";
	}

	/**
	 * Get the site context section.
	 *
	 * @since 1.0.0
	 *
	 * @param array $context Site and user context values.
	 * @return string
	 */
	private function get_context_section( array $context ) {
		return "## Current Site Context\n\n" .
			"- Site: {$context['site_name']} ({$context['site_url']})\n" .
			"- WordPress: {$context['wp_version']}\n" .
			"- PHP: {$context['php_version']}\n" .
			"- Locale: {$context['locale']}\n" .
			"- Current User: {$context['user_name']} (Role: {$context['user_role']})\n\n" .
			"Respond concisely and helpfully. Use the site context above to tailor your responses.\n";
	}

	/**
	 * Get default context values from the current WordPress environment.
	 *
	 * @since 1.0.0
	 * @return array Default context values.
	 */
	private function get_default_context() {
		$current_user = wp_get_current_user();
		$user_roles   = $current_user->roles;

		return [
			'site_name'   => substr( sanitize_text_field( get_bloginfo( 'name' ) ), 0, 100 ),
			'site_url'    => esc_url( home_url() ),
			'wp_version'  => sanitize_text_field( get_bloginfo( 'version' ) ),
			'user_role'   => ! empty( $user_roles ) ? sanitize_text_field( implode( ', ', $user_roles ) ) : 'none',
			'user_name'   => substr( sanitize_text_field( $current_user->display_name ? $current_user->display_name : 'Unknown' ), 0, 60 ),
			'php_version' => PHP_VERSION,
			'locale'      => sanitize_text_field( get_locale() ),
		];
	}
}
