<?php
/**
 * Add Custom CSS Action.
 *
 * Reads, appends to, or replaces the site's Additional CSS stored in the
 * Customizer (wp_get_custom_css / wp_update_custom_css_post). Use this to
 * apply animations, hover effects, gradients, glassmorphism, transitions,
 * and other visual polish that block attributes alone cannot achieve.
 * CSS is site-wide and persists across theme changes.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Add_Custom_Css
 *
 * @since 1.0.0
 */
class Add_Custom_Css implements Action_Interface {

	/**
	 * Maximum allowed CSS size in bytes (100 KB).
	 *
	 * @var int
	 */
	const MAX_CSS_BYTES = 102400;

	/**
	 * Preview snippet length in characters.
	 *
	 * @var int
	 */
	const PREVIEW_LENGTH = 200;

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'add_custom_css';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Read, append, or replace the site\'s custom CSS (Additional CSS in the Customizer). '
			. 'Use this to add animations, hover effects, gradients, glassmorphism, transitions, '
			. 'and other visual polish that block attributes alone cannot achieve. '
			. 'CSS is site-wide and persists across theme changes.';
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
					'enum'        => [ 'get', 'append', 'replace' ],
					'description' => 'Operation to perform: get current CSS, append to it, or replace it entirely.',
				],
				'css'       => [
					'type'        => 'string',
					'description' => 'The CSS code to add or replace with. Required for append and replace operations. Maximum 100 KB.',
				],
			],
			'required'   => [ 'operation' ],
		];
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'edit_theme_options';
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
		$operation = sanitize_key( $params['operation'] ?? '' );

		if ( ! in_array( $operation, [ 'get', 'append', 'replace' ], true ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'Invalid operation. Must be one of: get, append, replace.', 'wp-agent' ),
			];
		}

		// Handle read-only operation.
		if ( 'get' === $operation ) {
			return $this->handle_get();
		}

		// append and replace both require the css parameter.
		if ( empty( $params['css'] ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'The "css" parameter is required for append and replace operations.', 'wp-agent' ),
			];
		}

		$raw_css = $params['css'];

		// Strip any accidental <style> tags.
		$raw_css = preg_replace( '/<\/?style[^>]*>/i', '', $raw_css );

		// Strip all HTML tags to prevent HTML/script injection.
		$raw_css = wp_strip_all_tags( $raw_css );
		$raw_css = trim( $raw_css );

		if ( empty( $raw_css ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'CSS was empty after sanitization.', 'wp-agent' ),
			];
		}

		// Enforce size limit.
		if ( strlen( $raw_css ) > self::MAX_CSS_BYTES ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: 1: submitted size in KB, 2: max size in KB */
					__( 'CSS exceeds the maximum allowed size of %2$d KB (submitted: %1$d KB).', 'wp-agent' ),
					(int) ceil( strlen( $raw_css ) / 1024 ),
					(int) ( self::MAX_CSS_BYTES / 1024 )
				),
			];
		}

		// Security validation.
		$validation = $this->validate_css( $raw_css );
		if ( is_wp_error( $validation ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => $validation->get_error_message(),
			];
		}

		if ( 'append' === $operation ) {
			return $this->handle_append( $raw_css );
		}

		return $this->handle_replace( $raw_css );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Handle the get operation.
	 *
	 * @since 1.0.0
	 * @return array Execution result.
	 */
	private function handle_get(): array {
		$current_css = wp_get_custom_css();
		$css_length  = strlen( $current_css );

		return [
			'success' => true,
			'data'    => [
				'css'        => $current_css,
				'css_length' => $css_length,
				'preview'    => $this->make_preview( $current_css ),
			],
			'message' => $css_length > 0
				? sprintf(
					/* translators: %d: number of CSS characters */
					__( 'Retrieved current custom CSS (%d characters).', 'wp-agent' ),
					$css_length
				)
				: __( 'No custom CSS is currently set.', 'wp-agent' ),
		];
	}

	/**
	 * Handle the append operation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $new_css Sanitized and validated CSS to append.
	 * @return array Execution result.
	 */
	private function handle_append( string $new_css ): array {
		$current_css = wp_get_custom_css();

		$combined_css = $current_css . "\n\n/* Added by JARVIS */\n" . $new_css;

		// Final combined-size check.
		if ( strlen( $combined_css ) > self::MAX_CSS_BYTES ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %d: max size in KB */
					__( 'Combined CSS would exceed the %d KB limit. Use the replace operation or remove existing CSS first.', 'wp-agent' ),
					(int) ( self::MAX_CSS_BYTES / 1024 )
				),
			];
		}

		$result = wp_update_custom_css_post( $combined_css );

		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => $result->get_error_message(),
			];
		}

		$total_length = strlen( $combined_css );

		return [
			'success' => true,
			'data'    => [
				'css_length' => $total_length,
				'preview'    => $this->make_preview( $new_css ),
			],
			'message' => sprintf(
				/* translators: 1: appended CSS length, 2: total CSS length */
				__( 'Appended %1$d characters of CSS. Total custom CSS is now %2$d characters.', 'wp-agent' ),
				strlen( $new_css ),
				$total_length
			),
		];
	}

	/**
	 * Handle the replace operation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $new_css Sanitized and validated CSS to use as replacement.
	 * @return array Execution result.
	 */
	private function handle_replace( string $new_css ): array {
		$result = wp_update_custom_css_post( $new_css );

		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => $result->get_error_message(),
			];
		}

		$css_length = strlen( $new_css );

		return [
			'success' => true,
			'data'    => [
				'css_length' => $css_length,
				'preview'    => $this->make_preview( $new_css ),
			],
			'message' => sprintf(
				/* translators: %d: CSS character count */
				__( 'Replaced custom CSS with %d characters of new CSS.', 'wp-agent' ),
				$css_length
			),
		];
	}

	/**
	 * Validate CSS for dangerous patterns that could be used as XSS vectors.
	 *
	 * Blocks CSS properties/values that browsers may evaluate as script or
	 * that load untrusted external resources.
	 *
	 * @since 1.0.0
	 *
	 * @param string $css Raw CSS string (HTML already stripped).
	 * @return true|\WP_Error True when safe, WP_Error on the first violation found.
	 */
	private function validate_css( string $css ) {
		$dangerous = [
			'expression(',
			'javascript:',
			'behavior:',
			'-moz-binding:',
			'vbscript:',
		];

		$css_lower = strtolower( $css );

		foreach ( $dangerous as $pattern ) {
			if ( false !== strpos( $css_lower, $pattern ) ) {
				return new \WP_Error(
					'unsafe_css',
					sprintf(
						/* translators: %s: the dangerous CSS pattern that was detected */
						__( 'CSS contains unsafe pattern: %s', 'wp-agent' ),
						$pattern
					)
				);
			}
		}

		// Block external @import URLs — relative imports are allowed.
		if ( preg_match( '/@import\s+url\s*\(\s*["\']?https?:\/\//i', $css ) ) {
			return new \WP_Error(
				'unsafe_css',
				__( 'External @import URLs are not allowed for security.', 'wp-agent' )
			);
		}

		return true;
	}

	/**
	 * Return a short preview snippet of the given CSS string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $css CSS string to preview.
	 * @return string Truncated preview, or empty string when CSS is empty.
	 */
	private function make_preview( string $css ): string {
		$css = trim( $css );

		if ( '' === $css ) {
			return '';
		}

		if ( strlen( $css ) <= self::PREVIEW_LENGTH ) {
			return $css;
		}

		return substr( $css, 0, self::PREVIEW_LENGTH ) . '...';
	}
}
