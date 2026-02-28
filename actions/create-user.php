<?php
/**
 * Create User Action.
 *
 * Creates a new WordPress user with an auto-generated password.
 * Validates username and email uniqueness, and restricts role assignment
 * based on the current user's capabilities.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Create_User
 *
 * @since 1.0.0
 */
class Create_User implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'create_user';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Create a new WordPress user. A strong password is auto-generated '
			. '(never accepts user-supplied passwords). Defaults to the "subscriber" role.';
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
				'user_login'   => [
					'type'        => 'string',
					'description' => 'The username for the new user.',
				],
				'user_email'   => [
					'type'        => 'string',
					'description' => 'The email address for the new user.',
				],
				'role'         => [
					'type'        => 'string',
					'description' => 'The role to assign (e.g. "subscriber", "editor", "author"). Defaults to "subscriber".',
				],
				'first_name'   => [
					'type'        => 'string',
					'description' => 'The user\'s first name.',
				],
				'last_name'    => [
					'type'        => 'string',
					'description' => 'The user\'s last name.',
				],
				'display_name' => [
					'type'        => 'string',
					'description' => 'The display name for the user.',
				],
			],
			'required'   => [ 'user_login', 'user_email' ],
		];
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'create_users';
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
		$user_login = sanitize_user( $params['user_login'], true );
		$user_email = sanitize_email( $params['user_email'] );
		$role       = isset( $params['role'] ) ? sanitize_text_field( $params['role'] ) : 'subscriber';

		// Validate username is not empty after sanitization.
		if ( empty( $user_login ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Invalid username. Username must contain valid characters.', 'wp-agent' ),
			];
		}

		// Validate email format.
		if ( ! is_email( $user_email ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Invalid email address.', 'wp-agent' ),
			];
		}

		// Check username uniqueness.
		if ( username_exists( $user_login ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: username */
					__( 'Username "%s" already exists.', 'wp-agent' ),
					$user_login
				),
			];
		}

		// Check email uniqueness.
		if ( email_exists( $user_email ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: email address */
					__( 'Email "%s" is already registered.', 'wp-agent' ),
					$user_email
				),
			];
		}

		// Validate role exists.
		$valid_roles = wp_roles()->get_names();
		if ( ! isset( $valid_roles[ $role ] ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: role name */
					__( 'Invalid role "%s".', 'wp-agent' ),
					$role
				),
			];
		}

		// Restrict role assignment: non-admins cannot assign administrator role.
		if ( 'administrator' === $role && ! current_user_can( 'manage_options' ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'You do not have permission to assign the administrator role.', 'wp-agent' ),
			];
		}

		// Build the user data array.
		$userdata = [
			'user_login' => $user_login,
			'user_email' => $user_email,
			'user_pass'  => wp_generate_password( 24, true, true ),
			'role'       => $role,
		];

		if ( ! empty( $params['first_name'] ) ) {
			$userdata['first_name'] = sanitize_text_field( $params['first_name'] );
		}

		if ( ! empty( $params['last_name'] ) ) {
			$userdata['last_name'] = sanitize_text_field( $params['last_name'] );
		}

		if ( ! empty( $params['display_name'] ) ) {
			$userdata['display_name'] = sanitize_text_field( $params['display_name'] );
		}

		// Create the user.
		$user_id = wp_insert_user( $userdata );

		if ( is_wp_error( $user_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Failed to create user: %s', 'wp-agent' ),
					$user_id->get_error_message()
				),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'user_id'    => $user_id,
				'user_login' => $user_login,
				'role'       => $role,
				'edit_url'   => admin_url( 'user-edit.php?user_id=' . $user_id ),
			],
			'message' => sprintf(
				/* translators: 1: username, 2: role */
				__( 'Created user "%1$s" with the "%2$s" role. A password was auto-generated.', 'wp-agent' ),
				$user_login,
				$valid_roles[ $role ]
			),
		];
	}
}
