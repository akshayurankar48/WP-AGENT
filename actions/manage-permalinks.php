<?php
/**
 * Manage Permalinks Action.
 *
 * Updates the WordPress permalink structure and flushes rewrite rules.
 * Validates that the structure contains at least one rewrite tag.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Manage_Permalinks
 *
 * @since 1.0.0
 */
class Manage_Permalinks implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'manage_permalinks';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Update the WordPress permalink structure and flush rewrite rules. '
			. 'Common presets: Plain = "" (empty), Day and name = "/%year%/%monthnum%/%day%/%postname%/", '
			. 'Month and name = "/%year%/%monthnum%/%postname%/", Post name = "/%postname%/", '
			. 'Numeric = "/archives/%post_id%". The structure must contain at least one rewrite tag.';
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
				'structure' => [
					'type'        => 'string',
					'description' => 'The permalink structure string (e.g. "/%postname%/", "/%year%/%monthnum%/%postname%/"). Use empty string for plain permalinks.',
				],
			],
			'required'   => [ 'structure' ],
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
		$structure = sanitize_text_field( $params['structure'] );

		// Allow empty string for "plain" permalinks, but validate non-empty strings.
		if ( '' !== $structure ) {
			$valid_tags = [
				'%year%',
				'%monthnum%',
				'%day%',
				'%hour%',
				'%minute%',
				'%second%',
				'%post_id%',
				'%postname%',
				'%category%',
				'%author%',
			];

			$has_tag = false;
			foreach ( $valid_tags as $tag ) {
				if ( false !== strpos( $structure, $tag ) ) {
					$has_tag = true;
					break;
				}
			}

			if ( ! $has_tag ) {
				return [
					'success' => false,
					'data'    => null,
					'message' => __( 'Permalink structure must contain at least one rewrite tag (e.g. %postname%, %post_id%).', 'wp-agent' ),
				];
			}
		}

		$old_structure = get_option( 'permalink_structure' );

		update_option( 'permalink_structure', $structure );
		flush_rewrite_rules();

		return [
			'success' => true,
			'data'    => [
				'old_structure' => $old_structure,
				'new_structure' => $structure,
			],
			'message' => sprintf(
				/* translators: 1: old permalink structure, 2: new permalink structure */
				__( 'Permalink structure updated from "%1$s" to "%2$s". Rewrite rules flushed.', 'wp-agent' ),
				$old_structure,
				$structure
			),
		];
	}
}
