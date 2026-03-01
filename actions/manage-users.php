<?php
/**
 * Manage Users Action.
 *
 * Edit, delete, and manage existing WordPress users. Complements
 * create_user (new users) and list_users (query users). Supports
 * role changes, profile updates, password resets, and user deletion.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Manage_Users
 *
 * @since 1.0.0
 */
class Manage_Users implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'manage_users';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Manage existing WordPress users — update profile fields, change roles, '
			. 'reset passwords, or delete users. Use list_users first to find users, '
			. 'then use this to modify them. Use create_user to add new users.';
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
				'operation' => [
					'type'        => 'string',
					'enum'        => [ 'update', 'change_role', 'reset_password', 'delete' ],
					'description' => 'Operation to perform: "update" edits profile fields, '
						. '"change_role" changes the user role, "reset_password" generates a new password '
						. 'and sends a reset email, "delete" removes the user.',
				],
				'user_id'   => [
					'type'        => 'integer',
					'description' => 'The ID of the user to manage.',
				],
				'fields'    => [
					'type'        => 'object',
					'description' => 'Profile fields to update (for "update" operation). '
						. 'Supports: first_name, last_name, display_name, user_email, user_url, description.',
				],
				'role'      => [
					'type'        => 'string',
					'enum'        => [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ],
					'description' => 'New role (for "change_role" operation).',
				],
				'reassign'  => [
					'type'        => 'integer',
					'description' => 'User ID to reassign posts to when deleting a user. '
						. 'Required for "delete" operation to prevent orphaned content.',
				],
			],
			'required'   => [ 'operation', 'user_id' ],
		];
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'edit_users';
	}

	/**
	 * Whether this action is reversible.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_reversible(): bool {
		return false;
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
		$operation = sanitize_key( $params['operation'] ?? '' );
		$user_id   = absint( $params['user_id'] ?? 0 );

		if ( ! $user_id ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'User ID is required.', 'wp-agent' ),
			];
		}

		// Verify user exists.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf( __( 'User #%d not found.', 'wp-agent' ), $user_id ),
			];
		}

		// Prevent modifying yourself through the AI agent.
		if ( $user_id === get_current_user_id() ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'You cannot modify your own account through the AI agent.', 'wp-agent' ),
			];
		}

		// Prevent non-admins from modifying admins.
		if ( in_array( 'administrator', $user->roles, true ) && ! current_user_can( 'manage_options' ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'You do not have permission to modify administrator accounts.', 'wp-agent' ),
			];
		}

		switch ( $operation ) {
			case 'update':
				return $this->update_user( $user, $params );
			case 'change_role':
				return $this->change_role( $user, $params );
			case 'reset_password':
				return $this->reset_password( $user );
			case 'delete':
				return $this->delete_user( $user, $params );
			default:
				return [
					'success' => false,
					'data'    => null,
					'message' => sprintf( __( 'Unknown operation: %s', 'wp-agent' ), $operation ),
				];
		}
	}

	/**
	 * Update user profile fields.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $user   The user object.
	 * @param array    $params Action parameters.
	 * @return array Result.
	 */
	private function update_user( $user, array $params ): array {
		$fields = $params['fields'] ?? [];

		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'No fields provided to update.', 'wp-agent' ),
			];
		}

		$allowed_fields = [
			'first_name',
			'last_name',
			'display_name',
			'user_email',
			'user_url',
			'description',
		];

		$userdata = [ 'ID' => $user->ID ];
		$updated  = [];

		foreach ( $fields as $key => $value ) {
			$key = sanitize_key( $key );

			if ( ! in_array( $key, $allowed_fields, true ) ) {
				continue;
			}

			if ( 'user_email' === $key ) {
				$value = sanitize_email( $value );
				if ( ! is_email( $value ) ) {
					return [
						'success' => false,
						'data'    => null,
						'message' => __( 'Invalid email address.', 'wp-agent' ),
					];
				}
				// Check email is not taken by another user.
				$existing = email_exists( $value );
				if ( $existing && $existing !== $user->ID ) {
					return [
						'success' => false,
						'data'    => null,
						'message' => __( 'This email is already registered to another user.', 'wp-agent' ),
					];
				}
			} elseif ( 'user_url' === $key ) {
				$value = esc_url_raw( $value );
			} else {
				$value = sanitize_text_field( $value );
			}

			$userdata[ $key ] = $value;
			$updated[]        = $key;
		}

		if ( count( $updated ) === 0 ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'No valid fields to update.', 'wp-agent' ),
			];
		}

		$result = wp_update_user( $userdata );

		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					__( 'Failed to update user: %s', 'wp-agent' ),
					$result->get_error_message()
				),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'user_id'        => $user->ID,
				'updated_fields' => $updated,
			],
			'message' => sprintf(
				__( 'Updated %d field(s) for user "%s".', 'wp-agent' ),
				count( $updated ),
				sanitize_text_field( $user->display_name )
			),
		];
	}

	/**
	 * Change a user's role.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $user   The user object.
	 * @param array    $params Action parameters.
	 * @return array Result.
	 */
	private function change_role( $user, array $params ): array {
		$new_role = sanitize_key( $params['role'] ?? '' );

		if ( empty( $new_role ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Role is required for change_role operation.', 'wp-agent' ),
			];
		}

		// Validate role exists.
		$valid_roles = wp_roles()->get_names();
		if ( ! isset( $valid_roles[ $new_role ] ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf( __( 'Invalid role: %s', 'wp-agent' ), $new_role ),
			];
		}

		// Restrict: only admins can promote to administrator.
		if ( 'administrator' === $new_role && ! current_user_can( 'manage_options' ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'You do not have permission to assign the administrator role.', 'wp-agent' ),
			];
		}

		$old_role = ! empty( $user->roles ) ? reset( $user->roles ) : 'none';
		$user->set_role( $new_role );

		return [
			'success' => true,
			'data'    => [
				'user_id'  => $user->ID,
				'old_role' => $old_role,
				'new_role' => $new_role,
			],
			'message' => sprintf(
				__( 'Changed role for "%1$s" from %2$s to %3$s.', 'wp-agent' ),
				sanitize_text_field( $user->display_name ),
				$valid_roles[ $old_role ] ?? $old_role,
				$valid_roles[ $new_role ]
			),
		];
	}

	/**
	 * Reset a user's password and send notification.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $user The user object.
	 * @return array Result.
	 */
	private function reset_password( $user ): array {
		$new_password = wp_generate_password( 24, true, true );

		wp_set_password( $new_password, $user->ID );

		// Send the password reset notification email.
		wp_password_change_notification( $user );

		return [
			'success' => true,
			'data'    => [
				'user_id' => $user->ID,
			],
			'message' => sprintf(
				__( 'Password reset for "%s". A notification has been sent.', 'wp-agent' ),
				sanitize_text_field( $user->display_name )
			),
		];
	}

	/**
	 * Delete a user and reassign their content.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $user   The user object.
	 * @param array    $params Action parameters.
	 * @return array Result.
	 */
	private function delete_user( $user, array $params ): array {
		if ( ! current_user_can( 'delete_users' ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'You do not have permission to delete users.', 'wp-agent' ),
			];
		}

		$reassign = isset( $params['reassign'] ) ? absint( $params['reassign'] ) : 0;

		// Require reassignment to prevent orphaned content.
		if ( ! $reassign ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'You must specify a reassign user ID to transfer content to before deleting a user.', 'wp-agent' ),
			];
		}

		// Validate reassign user exists.
		$reassign_user = get_userdata( $reassign );
		if ( ! $reassign_user ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf( __( 'Reassign user #%d not found.', 'wp-agent' ), $reassign ),
			];
		}

		// Cannot reassign to the user being deleted.
		if ( $reassign === $user->ID ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Cannot reassign content to the user being deleted.', 'wp-agent' ),
			];
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$display_name = sanitize_text_field( $user->display_name );
		$deleted      = wp_delete_user( $user->ID, $reassign );

		if ( ! $deleted ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Failed to delete user.', 'wp-agent' ),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'deleted_user_id' => $user->ID,
				'reassigned_to'   => $reassign,
			],
			'message' => sprintf(
				__( 'Deleted user "%1$s". Content reassigned to "%2$s".', 'wp-agent' ),
				$display_name,
				sanitize_text_field( $reassign_user->display_name )
			),
		];
	}
}
