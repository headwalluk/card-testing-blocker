<?php
/**
 * Honeypot signal.
 *
 * Detects honeypot products in the cart.
 *
 * @since 0.1.0
 *
 * @package Card_Testing_Blocker
 */

namespace Card_Testing_Blocker\Signals;

use Card_Testing_Blocker;

defined( 'ABSPATH' ) || die();

/**
 * Scores positively when one or more honeypot products are in the cart.
 *
 * @since 0.1.0
 */
class Honeypot_Signal {

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

		$score      = 0;
		$cart_items = isset( $context['cart_items'] ) ? $context['cart_items'] : [];

		foreach ( $cart_items as $item ) {

			$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;

			if ( $product_id > 0 && '1' === get_post_meta( $product_id, Card_Testing_Blocker\META_HONEYPOT, true ) ) {
				$score = $this->get_configured_score();
				break;
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

		return 'honeypot_product';
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
		$score    = isset( $settings['score_honeypot'] ) ? absint( $settings['score_honeypot'] ) : Card_Testing_Blocker\DEF_SCORE_HONEYPOT;

		return $score;
	}
}
