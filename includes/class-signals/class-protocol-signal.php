<?php
/**
 * Protocol signal.
 *
 * Detects HTTP/1.1 requests on a site configured for HTTP/2.
 *
 * @since 0.1.0
 *
 * @package Card_Testing_Blocker
 */

namespace Card_Testing_Blocker\Signals;

use Card_Testing_Blocker;

defined( 'ABSPATH' ) || die();

/**
 * Scores positively when the client's HTTP protocol version does not
 * match the expected version (e.g., HTTP/1.1 on an HTTP/2 site).
 *
 * Requires the web server to forward the client protocol via a custom
 * header (e.g., nginx: `proxy_set_header X-CTB-Http-Version $server_protocol;`).
 * If the header is not present, this signal returns 0.
 *
 * @since 0.1.0
 */
class Protocol_Signal {

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

		$score    = 0;
		$protocol = isset( $context['server_protocol'] ) ? $context['server_protocol'] : '';

		if ( ! empty( $protocol ) ) {
			$expected = $this->get_expected_protocol();

			if ( $protocol !== $expected ) {
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

		return 'http_protocol';
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
		$score    = isset( $settings['score_http_protocol'] ) ? absint( $settings['score_http_protocol'] ) : Card_Testing_Blocker\DEF_SCORE_HTTP_PROTOCOL;

		return $score;
	}

	/**
	 * Get the expected HTTP protocol version.
	 *
	 * @since 0.1.0
	 *
	 * @return string Expected protocol (e.g., 'HTTP/2.0').
	 */
	private function get_expected_protocol(): string {

		$settings = get_option( Card_Testing_Blocker\OPT_SETTINGS, [] );
		$protocol = isset( $settings['expected_protocol'] ) ? $settings['expected_protocol'] : Card_Testing_Blocker\DEF_EXPECTED_PROTOCOL;

		return $protocol;
	}
}
