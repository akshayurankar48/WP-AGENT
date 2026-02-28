<?php
/**
 * REST Permissions Helper.
 *
 * Shared permission check for role-based access control across
 * chat, stream, history, and action controllers.
 *
 * @package WPAgent\REST
 * @since   1.0.0
 */

namespace WPAgent\REST;

defined( 'ABSPATH' ) || exit;

/**
 * Class REST_Permissions
 *
 * @since 1.0.0
 */
class REST_Permissions {

	/**
	 * Check if the current user has an allowed role for WP Agent access.
	 *
	 * Checks the user's role against the wp_agent_allowed_roles option.
	 * Defaults to administrator-only if no option is set.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the user has an allowed role.
	 */
	public static function current_user_has_allowed_role() {
		$user = wp_get_current_user();

		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		$allowed_roles = get_option( Settings_Controller::ROLES_OPTION, [ 'administrator' ] );

		if ( ! is_array( $allowed_roles ) || empty( $allowed_roles ) ) {
			$allowed_roles = [ 'administrator' ];
		}

		return ! empty( array_intersect( $user->roles, $allowed_roles ) );
	}
}
