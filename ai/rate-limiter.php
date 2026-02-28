<?php
/**
 * Rate Limiter.
 *
 * Per-user request throttling via WordPress transients.
 * Tracks minute and daily counters to prevent abuse.
 *
 * @package WPAgent\AI
 * @since   1.0.0
 */

namespace WPAgent\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class Rate_Limiter
 *
 * @since 1.0.0
 */
class Rate_Limiter {

	/**
	 * Instance
	 *
	 * @access private
	 * @var Rate_Limiter|null Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Default per-minute limit.
	 *
	 * @var int
	 */
	const DEFAULT_MINUTE_LIMIT = 30;

	/**
	 * Default per-day limit.
	 *
	 * @var int
	 */
	const DEFAULT_DAILY_LIMIT = 500;

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return Rate_Limiter Initialized object of class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Check rate limits and record the request in a single call.
	 *
	 * Combines check and record to minimize the TOCTOU window.
	 * Note: WordPress transients do not support atomic increment,
	 * so a small over-limit race is possible under high concurrency.
	 * This is an accepted tradeoff for the transient-based approach.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id WordPress user ID. Must be > 0 (authenticated).
	 * @return true|\WP_Error True if allowed (and recorded), WP_Error if rate-limited.
	 */
	public function check_and_record( $user_id ) {
		$user_id = (int) $user_id;

		if ( $user_id <= 0 ) {
			return new \WP_Error(
				'invalid_user',
				__( 'Rate limiting requires an authenticated user.', 'wp-agent' ),
				[ 'status' => 403 ]
			);
		}

		$usage = $this->get_usage( $user_id );

		if ( $usage['minute'] >= $usage['minute_limit'] ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				__( 'Rate limit exceeded. Please wait a moment before sending another message.', 'wp-agent' ),
				[ 'status' => 429 ]
			);
		}

		if ( $usage['day'] >= $usage['day_limit'] ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				__( 'Daily request limit reached. Please try again tomorrow.', 'wp-agent' ),
				[ 'status' => 429 ]
			);
		}

		// Record immediately after passing checks to minimize race window.
		$this->record( $user_id );

		return true;
	}

	/**
	 * Record a request for a user.
	 *
	 * Increments both minute and daily counters.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return void
	 */
	public function record( $user_id ) {
		$minute_key = $this->get_minute_key( $user_id );
		$day_key    = $this->get_day_key( $user_id );

		// Increment minute counter (60s TTL).
		$minute_count = (int) get_transient( $minute_key );
		set_transient( $minute_key, $minute_count + 1, 60 );

		// Increment daily counter (TTL = seconds until midnight).
		$day_count = (int) get_transient( $day_key );
		set_transient( $day_key, $day_count + 1, $this->seconds_until_midnight() );
	}

	/**
	 * Get current usage stats for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array{minute: int, minute_limit: int, day: int, day_limit: int}
	 */
	public function get_usage( $user_id ) {
		return [
			'minute'       => (int) get_transient( $this->get_minute_key( $user_id ) ),
			'minute_limit' => (int) get_option( 'wp_agent_rate_limit', self::DEFAULT_MINUTE_LIMIT ),
			'day'          => (int) get_transient( $this->get_day_key( $user_id ) ),
			'day_limit'    => (int) get_option( 'wp_agent_daily_limit', self::DEFAULT_DAILY_LIMIT ),
		];
	}

	/**
	 * Get the transient key for per-minute tracking.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string
	 */
	private function get_minute_key( $user_id ) {
		return 'wp_agent_rate_min_' . (int) $user_id;
	}

	/**
	 * Get the transient key for per-day tracking.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string
	 */
	private function get_day_key( $user_id ) {
		return 'wp_agent_rate_day_' . (int) $user_id;
	}

	/**
	 * Calculate seconds remaining until midnight (WordPress timezone).
	 *
	 * @since 1.0.0
	 * @return int Seconds until midnight, minimum 1.
	 */
	private function seconds_until_midnight() {
		$now      = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Need numeric timestamp for math.
		$midnight = strtotime( 'tomorrow midnight', $now );

		return max( 1, $midnight - $now );
	}
}
