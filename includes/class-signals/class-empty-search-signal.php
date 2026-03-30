<?php
/**
 * Empty search signal.
 *
 * Detects whether the current visitor's IP recently performed
 * an empty product search — a strong bot indicator.
 *
 * @since 0.1.0
 *
 * @package Card_Testing_Blocker
 */

namespace Card_Testing_Blocker\Signals;

use Card_Testing_Blocker;

defined( 'ABSPATH' ) || die();

/**
 * Scores positively when the visitor's IP has a recent empty-search
 * transient recorded by Query_Filter.
 *
 * @since 0.1.0
 */
class Empty_Search_Signal {

	/**
	 * Calculate the threat score for this signal.
	 *
	 * @since 0.1.0
	 *
	 * @param array $context {
	 *     Request context.
	 *
	 *     @type array  $cart_items      WooCommerce cart items.
	 *     @type string $ip_address      Client IP address.
	 *     @type string $user_agent      Client user agent string.
	 *     @type string $server_protocol HTTP protocol version.
	 * }
	 *
	 * @return int Score (0 if not triggered, positive integer if triggered).
	 */
	public function get_score( array $context ): int {

		$score = 0;
		$ip    = isset( $context['ip_address'] ) ? $context['ip_address'] : '';

		if ( ! empty( $ip ) ) {
			$key   = Card_Testing_Blocker\TRANSIENT_PREFIX_EMPTY_SEARCH . md5( $ip );
			$value = get_transient( $key );

			if ( false !== $value ) {
				$score = $this->get_configured_score();
			}
		}

		return $score;
	}

	/**
	 * Get the human-readable name of this signal.
	 *
	 * @since 0.1.0
	 *
	 * @return string Signal name for logging.
	 */
	public function get_name(): string {

		return 'empty_search';
	}

	/**
	 * Get the configured score for this signal.
	 *
	 * @since 0.1.0
	 *
	 * @return int Score value.
	 */
	private function get_configured_score(): int {

		$settings = get_option( Card_Testing_Blocker\OPT_SETTINGS, [] );
		$score    = isset( $settings['score_empty_search'] ) ? absint( $settings['score_empty_search'] ) : Card_Testing_Blocker\DEF_SCORE_EMPTY_SEARCH;

		return $score;
	}
}
