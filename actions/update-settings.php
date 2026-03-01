<?php
/**
 * Update Settings Action.
 *
 * Updates a WordPress site option from a strict whitelist of safe options.
 * Stores the old value before updating for checkpoint undo support.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Update_Settings
 *
 * @since 1.0.0
 */
class Update_Settings implements Action_Interface {

	/**
	 * Whitelist of options the AI is allowed to modify.
	 *
	 * @var string[]
	 */
	const ALLOWED_OPTIONS = [
		'blogname',
		'blogdescription',
		'admin_email',
		'date_format',
		'time_format',
		'timezone_string',
		'gmt_offset',
		'start_of_week',
		'WPLANG',
		'posts_per_page',
		'posts_per_rss',
		'blog_public',
		'default_category',
		'default_post_format',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
		'default_comment_status',
		'default_ping_status',
		'comment_moderation',
		'comment_registration',
		'close_comments_for_old_posts',
		'thread_comments',
		'thread_comments_depth',
		'comments_per_page',
	];

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'update_settings';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Update a WordPress site setting. Only whitelisted options are allowed: '
			. implode( ', ', self::ALLOWED_OPTIONS )
			. '. Rejects any option not in this list.';
	}

	/**
	 * Get the JSON Schema for parameters.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_parameters(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'option_name'  => [
					'type'        => 'string',
					'description' => 'The option name to update. Must be one of the whitelisted options.',
					'enum'        => self::ALLOWED_OPTIONS,
				],
				'option_value' => [
					'type'        => 'string',
					'description' => 'The new value for the option. Pass numbers and booleans as strings (e.g. "1", "true").',
				],
			],
			'required'   => [ 'option_name', 'option_value' ],
		];
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'manage_options';
	}

	/**
	 * Whether this action is reversible.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_reversible(): bool {
		return true;
	}

	/**
	 * Execute the action.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Validated parameters.
	 * @return array Execution result.
	 */
	public function execute( array $params ): array {
		$option_name = sanitize_text_field( $params['option_name'] );

		if ( ! in_array( $option_name, self::ALLOWED_OPTIONS, true ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: option name */
					__( 'Option "%s" is not in the allowed whitelist.', 'wp-agent' ),
					$option_name
				),
			];
		}

		$old_value = get_option( $option_name );
		$new_value = $params['option_value'];

		// Sanitize based on expected types.
		if ( is_numeric( $new_value ) && in_array( $option_name, [ 'posts_per_page', 'posts_per_rss', 'start_of_week', 'gmt_offset', 'page_on_front', 'page_for_posts', 'default_category', 'thread_comments_depth', 'comments_per_page', 'blog_public' ], true ) ) {
			$new_value = intval( $new_value );
		} else {
			$new_value = sanitize_text_field( (string) $new_value );
		}

		$updated = update_option( $option_name, $new_value );

		if ( ! $updated && get_option( $option_name ) !== $new_value ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: option name */
					__( 'Failed to update option "%s".', 'wp-agent' ),
					$option_name
				),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'option_name' => $option_name,
				'old_value'   => $old_value,
				'new_value'   => $new_value,
			],
			'message' => sprintf(
				/* translators: %s: option name */
				__( 'Updated option "%s" successfully.', 'wp-agent' ),
				$option_name
			),
		];
	}
}
