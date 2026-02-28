<?php
/**
 * Model Router.
 *
 * Three-tier model selection with auto-routing and fallback chain.
 * Selects the appropriate AI model based on message complexity.
 *
 * @package WPAgent\AI
 * @since   1.0.0
 */

namespace WPAgent\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class Model_Router
 *
 * @since 1.0.0
 */
class Model_Router {

	/**
	 * Instance
	 *
	 * @access private
	 * @var Model_Router|null Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Model tier: fast.
	 *
	 * @var string
	 */
	const TIER_FAST = 'fast';

	/**
	 * Model tier: balanced.
	 *
	 * @var string
	 */
	const TIER_BALANCED = 'balanced';

	/**
	 * Model tier: powerful.
	 *
	 * @var string
	 */
	const TIER_POWERFUL = 'powerful';

	/**
	 * Available models keyed by tier.
	 *
	 * @var array<string, array{id: string, name: string, tier: string, max_tokens: int, supports_tools: bool}>
	 */
	private static $models = [
		self::TIER_FAST      => [
			'id'             => 'google/gemini-2.0-flash-001',
			'name'           => 'Gemini 2.0 Flash',
			'tier'           => self::TIER_FAST,
			'max_tokens'     => 8192,
			'supports_tools' => true,
		],
		self::TIER_BALANCED  => [
			'id'             => 'openai/gpt-4o-mini',
			'name'           => 'GPT-4o Mini',
			'tier'           => self::TIER_BALANCED,
			'max_tokens'     => 16384,
			'supports_tools' => true,
		],
		self::TIER_POWERFUL  => [
			'id'             => 'anthropic/claude-sonnet-4',
			'name'           => 'Claude Sonnet 4',
			'tier'           => self::TIER_POWERFUL,
			'max_tokens'     => 8192,
			'supports_tools' => true,
		],
	];

	/**
	 * Fallback chain: powerful -> balanced -> fast -> none.
	 *
	 * @var array<string, string>
	 */
	private static $fallback_chain = [
		'anthropic/claude-sonnet-4'    => 'openai/gpt-4o-mini',
		'openai/gpt-4o-mini'           => 'google/gemini-2.0-flash-001',
		'google/gemini-2.0-flash-001'  => '',
	];

	/**
	 * Keywords that indicate complex actions needing a powerful model.
	 *
	 * @var string[]
	 */
	private static $action_keywords = [
		'delete',
		'remove',
		'update',
		'create',
		'publish',
		'install',
		'deactivate',
		'activate',
		'migrate',
		'bulk',
		'all posts',
		'every page',
		'database',
	];

	/**
	 * Initiator
	 *
	 * @since 1.0.0
	 * @return Model_Router Initialized object of class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Select the best model for a given message and conversation history.
	 *
	 * Uses complexity heuristics: message length, action keywords,
	 * and conversation depth.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message The user's message.
	 * @param array  $history Conversation history (array of message arrays).
	 * @return string OpenRouter model ID.
	 */
	public function select_model( $message, array $history = [] ) {
		$score = $this->compute_complexity_score( $message, $history );

		if ( $score >= 7 ) {
			$tier = self::TIER_POWERFUL;
		} elseif ( $score >= 3 ) {
			$tier = self::TIER_BALANCED;
		} else {
			$tier = self::TIER_FAST;
		}

		return self::$models[ $tier ]['id'];
	}

	/**
	 * Get all available models.
	 *
	 * @since 1.0.0
	 * @return array<string, array{id: string, name: string, tier: string, max_tokens: int, supports_tools: bool}>
	 */
	public function get_available_models() {
		return self::$models;
	}

	/**
	 * Get the user's default model preference.
	 *
	 * Falls back to the balanced tier if no preference is set.
	 *
	 * @since 1.0.0
	 * @return string OpenRouter model ID.
	 */
	public function get_default_model() {
		$default = get_option( 'wp_agent_default_model', '' );

		if ( ! empty( $default ) && $this->is_valid_model( $default ) ) {
			return $default;
		}

		return self::$models[ self::TIER_BALANCED ]['id'];
	}

	/**
	 * Get the fallback model for a given model ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id OpenRouter model ID.
	 * @return string Fallback model ID, or empty string if none.
	 */
	public function get_fallback( $model_id ) {
		return isset( self::$fallback_chain[ $model_id ] ) ? self::$fallback_chain[ $model_id ] : '';
	}

	/**
	 * Check if a model ID is in the available models list.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id OpenRouter model ID.
	 * @return bool
	 */
	public function is_valid_model( $model_id ) {
		foreach ( self::$models as $model ) {
			if ( $model['id'] === $model_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Compute a complexity score for model selection.
	 *
	 * Scoring:
	 * - Message length > 500 chars: +2
	 * - Message length > 200 chars: +1
	 * - Contains action keywords: +3
	 * - Contains multiple action keywords: +2 (additional)
	 * - Conversation depth > 10: +2
	 * - Conversation depth > 5: +1
	 *
	 * @since 1.0.0
	 *
	 * @param string $message The user's message.
	 * @param array  $history Conversation history.
	 * @return int Complexity score (0-10+).
	 */
	private function compute_complexity_score( $message, array $history ) {
		$score          = 0;
		$message_lower  = strtolower( $message );
		$message_length = function_exists( 'mb_strlen' ) ? mb_strlen( $message, 'UTF-8' ) : strlen( $message );

		// Message length scoring.
		if ( $message_length > 500 ) {
			$score += 2;
		} elseif ( $message_length > 200 ) {
			$score += 1;
		}

		// Action keyword scoring.
		$keyword_hits = 0;
		foreach ( self::$action_keywords as $keyword ) {
			if ( false !== strpos( $message_lower, $keyword ) ) {
				++$keyword_hits;
			}
		}

		if ( $keyword_hits > 0 ) {
			$score += 3;
		}
		if ( $keyword_hits > 1 ) {
			$score += 2;
		}

		// Conversation depth scoring.
		$depth = count( $history );
		if ( $depth > 10 ) {
			$score += 2;
		} elseif ( $depth > 5 ) {
			$score += 1;
		}

		return $score;
	}
}
