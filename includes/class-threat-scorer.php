<?php
/**
 * Threat scoring engine.
 *
 * Evaluates checkout attempts against registered threat signals
 * and returns a total score with breakdown.
 *
 * @since 0.1.0
 *
 * @package Card_Testing_Blocker
 */

namespace Card_Testing_Blocker;

defined( 'ABSPATH' ) || die();

/**
 * Collects and evaluates threat signals against a configurable threshold.
 *
 * @since 0.1.0
 */
class Threat_Scorer {

	/**
	 * Evaluate all registered signals and return the result.
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
	 * @return array{total_score: int, threshold: int, blocked: bool, signals: array<array{name: string, score: int}>}
	 */
	public function evaluate( array $context ): array {

		$signals           = $this->get_signals();
		$triggered_signals = [];
		$total_score       = 0;

		foreach ( $signals as $signal ) {

			$score = $signal->get_score( $context );

			if ( $score > 0 ) {
				$triggered_signals[] = [
					'name'  => $signal->get_name(),
					'score' => $score,
				];

				$total_score += $score;
			}
		}

		$threshold = $this->get_threshold( $context );
		$blocked   = $total_score >= $threshold;

		$result = [
			'total_score' => $total_score,
			'threshold'   => $threshold,
			'blocked'     => $blocked,
			'signals'     => $triggered_signals,
		];

		return $result;
	}

	/**
	 * Build a context array from the current request.
	 *
	 * @since 0.1.0
	 *
	 * @return array Request context.
	 */
	public function build_context(): array {

		$cart_items = [];

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$cart_items = WC()->cart->get_cart();
		}

		$context = [
			'cart_items'      => $cart_items,
			'ip_address'      => $this->get_client_ip(),
			'user_agent'      => $this->get_user_agent(),
			'server_protocol' => $this->get_server_protocol(),
		];

		/**
		 * Filter the context array before scoring.
		 *
		 * @since 0.1.0
		 *
		 * @param array $context Request context.
		 */
		$context = apply_filters( 'ctb_order_context', $context );

		return $context;
	}

	// -------------------------------------------------------------------------
	// Internal helpers.
	// -------------------------------------------------------------------------

	/**
	 * Get all registered threat signals.
	 *
	 * @since 0.1.0
	 *
	 * @return array Array of signal instances (each must implement get_score() and get_name()).
	 */
	private function get_signals(): array {

		$signals = [
			new Signals\Honeypot_Signal(),
			new Signals\Empty_Search_Signal(),
			new Signals\Protocol_Signal(),
		];

		/**
		 * Filter the registered threat signals.
		 *
		 * @since 0.1.0
		 *
		 * @param array $signals Array of signal class instances.
		 */
		$signals = apply_filters( 'ctb_threat_signals', $signals );

		return $signals;
	}

	/**
	 * Get the threat score threshold.
	 *
	 * @since 0.1.0
	 *
	 * @param array $context Request context.
	 *
	 * @return int Threshold value.
	 */
	private function get_threshold( array $context ): int {

		$settings  = get_option( OPT_SETTINGS, [] );
		$threshold = isset( $settings['threat_threshold'] ) ? absint( $settings['threat_threshold'] ) : DEF_THREAT_THRESHOLD;

		/**
		 * Filter the threat score threshold.
		 *
		 * @since 0.1.0
		 *
		 * @param int   $threshold Current threshold value.
		 * @param array $context   Request context.
		 */
		$threshold = apply_filters( 'ctb_threat_threshold', $threshold, $context );

		return $threshold;
	}

	/**
	 * Get the client IP address.
	 *
	 * @since 0.1.0
	 *
	 * @return string Client IP, or empty string if unavailable.
	 */
	private function get_client_ip(): string {

		$ip = '';

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Get the client user agent string.
	 *
	 * @since 0.1.0
	 *
	 * @return string User agent, or empty string if unavailable.
	 */
	private function get_user_agent(): string {

		$ua = '';

		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$ua = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		}

		return $ua;
	}

	/**
	 * Get the client HTTP protocol version.
	 *
	 * Reads from the custom header set by the web server (e.g.,
	 * X-CTB-Http-Version). Returns empty string if not available.
	 *
	 * @since 0.1.0
	 *
	 * @return string Protocol version (e.g., 'HTTP/1.1', 'HTTP/2.0'), or empty string.
	 */
	private function get_server_protocol(): string {

		$protocol = '';

		if ( ! empty( $_SERVER[ HTTP_VERSION_HEADER ] ) ) {
			$protocol = sanitize_text_field( wp_unslash( $_SERVER[ HTTP_VERSION_HEADER ] ) );
		}

		return $protocol;
	}
}
