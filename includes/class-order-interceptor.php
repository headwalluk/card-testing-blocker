<?php
/**
 * Order interceptor.
 *
 * Hooks into WooCommerce checkout to evaluate and block
 * suspicious orders based on threat scoring.
 *
 * @since 0.1.0
 *
 * @package Card_Testing_Blocker
 */

namespace Card_Testing_Blocker;

defined( 'ABSPATH' ) || die();

/**
 * Intercepts checkout at validation stage and blocks orders
 * that exceed the threat score threshold.
 *
 * @since 0.1.0
 */
class Order_Interceptor {

	/**
	 * Threat scorer instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Threat_Scorer
	 */
	private Threat_Scorer $scorer;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Threat_Scorer $scorer Threat scorer instance.
	 */
	public function __construct( Threat_Scorer $scorer ) {

		$this->scorer = $scorer;
	}

	/**
	 * Validate classic checkout.
	 *
	 * Called via `woocommerce_after_checkout_validation`.
	 *
	 * @since 0.1.0
	 *
	 * @param array     $data   Checkout POST data.
	 * @param \WP_Error $errors Validation errors.
	 *
	 * @return void
	 */
	public function validate_checkout( array $data, \WP_Error $errors ): void {

		$context = $this->scorer->build_context();
		$result  = $this->scorer->evaluate( $context );

		$this->fire_score_action( $result, $context );

		if ( $result['blocked'] ) {
			$this->log_blocked_attempt( $result, $context );
			$errors->add( 'ctb_blocked', $this->get_block_message() );
		}
	}

	/**
	 * Validate Store API / block checkout.
	 *
	 * Called via `woocommerce_store_api_checkout_order_processed`.
	 *
	 * @since 0.1.0
	 *
	 * @param \WC_Order $order The order being processed.
	 *
	 * @return void
	 */
	public function validate_store_api_checkout( \WC_Order $order ): void {

		$context = $this->scorer->build_context();
		$result  = $this->scorer->evaluate( $context );

		$this->fire_score_action( $result, $context );

		if ( $result['blocked'] ) {
			$this->log_blocked_attempt( $result, $context );

			$order->update_status( 'failed', __( 'Blocked by Card Testing Blocker.', 'card-testing-blocker' ) );

			wp_die(
				esc_html( $this->get_block_message() ),
				esc_html__( 'Order Blocked', 'card-testing-blocker' ),
				[ 'response' => 403 ]
			);
		}
	}

	// -------------------------------------------------------------------------
	// Internal helpers.
	// -------------------------------------------------------------------------

	/**
	 * Fire the score-calculated action.
	 *
	 * @since 0.1.0
	 *
	 * @param array $result  Scoring result.
	 * @param array $context Request context.
	 *
	 * @return void
	 */
	private function fire_score_action( array $result, array $context ): void {

		/**
		 * Fires after every score calculation.
		 *
		 * @since 0.1.0
		 *
		 * @param int   $total_score Total threat score.
		 * @param array $signals     Triggered signals: array of [ 'name' => string, 'score' => int ].
		 * @param array $context     Request context.
		 * @param bool  $blocked     Whether the order was blocked.
		 */
		do_action(
			'ctb_order_score_calculated',
			$result['total_score'],
			$result['signals'],
			$context,
			$result['blocked']
		);
	}

	/**
	 * Log a blocked attempt and fire the blocked action.
	 *
	 * @since 0.1.0
	 *
	 * @param array $result  Scoring result.
	 * @param array $context Request context.
	 *
	 * @return void
	 */
	private function log_blocked_attempt( array $result, array $context ): void {

		$signal_names = array_map(
			function ( $signal ) {
				return sprintf( '%s(%d)', $signal['name'], $signal['score'] );
			},
			$result['signals']
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional security logging.
		error_log(
			sprintf(
				'[Card Testing Blocker] Blocked order — IP: %s | Score: %d/%d | Signals: %s | UA: %s',
				$context['ip_address'],
				$result['total_score'],
				$result['threshold'],
				implode( ', ', $signal_names ),
				$context['user_agent']
			)
		);

		/**
		 * Fires when an order is blocked.
		 *
		 * @since 0.1.0
		 *
		 * @param int   $total_score Total threat score.
		 * @param array $signals     Triggered signals.
		 * @param array $context     Request context.
		 */
		do_action(
			'ctb_order_blocked',
			$result['total_score'],
			$result['signals'],
			$context
		);
	}

	/**
	 * Get the generic error message shown when an order is blocked.
	 *
	 * @since 0.1.0
	 *
	 * @return string Block message.
	 */
	private function get_block_message(): string {

		return __( 'This order could not be processed. Please contact us if you believe this is an error.', 'card-testing-blocker' );
	}
}
