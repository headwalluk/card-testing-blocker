<?php
/**
 * Query filter.
 *
 * Controls honeypot product visibility: hidden from legitimate
 * frontend browsing, visible to bot discovery patterns.
 *
 * @since 0.1.0
 *
 * @package Card_Testing_Blocker
 */

namespace Card_Testing_Blocker;

defined( 'ABSPATH' ) || die();

/**
 * Filters WordPress and WooCommerce queries to hide or reveal
 * honeypot products based on context.
 *
 * @since 0.1.0
 */
class Query_Filter {

	/**
	 * Cached array of honeypot product IDs.
	 *
	 * Populated once per request to avoid repeated queries.
	 *
	 * @since 0.1.0
	 *
	 * @var ?array<int>
	 */
	private ?array $honeypot_ids = null;

	/**
	 * Filter pre_get_posts to handle search queries and direct access.
	 *
	 * - Empty search (`?s=&post_type=product`): allow honeypots through,
	 *   and record the visitor IP for the empty-search signal.
	 * - Non-empty search: exclude honeypots.
	 * - Single product pages: 404 honeypot products.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_Query $query The current query.
	 *
	 * @return void
	 */
	public function filter_pre_get_posts( \WP_Query $query ): void {

		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Allow REST API requests through unfiltered.
		if ( $this->is_rest_request() ) {
			return;
		}

		$ids = $this->get_honeypot_ids();

		if ( empty( $ids ) ) {
			return;
		}

		if ( $this->is_empty_product_search( $query ) ) {
			$this->record_empty_search_ip();
			return;
		}

		if ( $query->is_search() && 'product' === $query->get( 'post_type' ) ) {
			$this->exclude_honeypots_from_query( $query, $ids );
			return;
		}

		if ( $query->is_singular( 'product' ) ) {
			$this->maybe_block_direct_access( $query, $ids );
		}
	}

	/**
	 * Filter WooCommerce product queries (shop, archive, category pages).
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_Query $query The product query.
	 *
	 * @return void
	 */
	public function filter_product_query( \WP_Query $query ): void {

		if ( $this->is_rest_request() ) {
			return;
		}

		$ids = $this->get_honeypot_ids();

		if ( ! empty( $ids ) ) {
			$this->exclude_honeypots_from_query( $query, $ids );
		}
	}

	/**
	 * Filter related product IDs to exclude honeypots.
	 *
	 * @since 0.1.0
	 *
	 * @param array $related_ids Related product IDs.
	 *
	 * @return array Filtered related product IDs.
	 */
	public function filter_related_products( array $related_ids ): array {

		$honeypot_ids = $this->get_honeypot_ids();

		$result = $related_ids;

		if ( ! empty( $honeypot_ids ) ) {
			$result = array_values( array_diff( $related_ids, $honeypot_ids ) );
		}

		return $result;
	}

	/**
	 * Filter widget product queries to exclude honeypots.
	 *
	 * @since 0.1.0
	 *
	 * @param array $query_args Widget query arguments.
	 *
	 * @return array Modified query arguments.
	 */
	public function filter_widget_products( array $query_args ): array {

		$ids = $this->get_honeypot_ids();

		if ( ! empty( $ids ) ) {
			$existing                      = isset( $query_args['post__not_in'] ) ? $query_args['post__not_in'] : [];
			$query_args['post__not_in']    = array_merge( $existing, $ids );
		}

		return $query_args;
	}

	/**
	 * Block direct URL access to honeypot products.
	 *
	 * Sets 404 status if the current single product is a honeypot.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function block_direct_access(): void {

		if ( ! is_singular( 'product' ) ) {
			return;
		}

		$product_id = get_queried_object_id();
		$ids        = $this->get_honeypot_ids();

		if ( in_array( $product_id, $ids, true ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	// -------------------------------------------------------------------------
	// Internal helpers.
	// -------------------------------------------------------------------------

	/**
	 * Get honeypot product IDs, cached for the current request.
	 *
	 * @since 0.1.0
	 *
	 * @return array<int> Honeypot product IDs.
	 */
	private function get_honeypot_ids(): array {

		if ( is_null( $this->honeypot_ids ) ) {
			$honeypot_manager   = new Honeypot_Products();
			$this->honeypot_ids = $honeypot_manager->get_honeypot_product_ids();
		}

		return $this->honeypot_ids;
	}

	/**
	 * Check if the current query is an empty product search.
	 *
	 * Matches the bot pattern: `?s=&post_type=product`.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_Query $query The current query.
	 *
	 * @return bool
	 */
	private function is_empty_product_search( \WP_Query $query ): bool {

		$result = false;

		if ( $query->is_search() && 'product' === $query->get( 'post_type' ) ) {
			$search_term = $query->get( 's' );
			$result      = is_string( $search_term ) && '' === trim( $search_term );
		}

		return $result;
	}

	/**
	 * Record the current visitor's IP for the empty-search signal.
	 *
	 * Stores a transient with a configurable TTL so the scoring
	 * engine can detect the pattern at checkout.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function record_empty_search_ip(): void {

		$ip = $this->get_client_ip();

		if ( empty( $ip ) ) {
			return;
		}

		$key = TRANSIENT_PREFIX_EMPTY_SEARCH . md5( $ip );
		$ttl = $this->get_empty_search_ttl();

		set_transient( $key, current_time( 'Y-m-d H:i:s T' ), $ttl );

		/**
		 * Fires when an empty product search is detected.
		 *
		 * @since 0.1.0
		 *
		 * @param string $ip IP address that performed the empty search.
		 */
		do_action( 'ctb_empty_search_detected', $ip );
	}

	/**
	 * Exclude honeypot product IDs from a query via post__not_in.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_Query $query The query to modify.
	 * @param array<int> $ids  Honeypot product IDs to exclude.
	 *
	 * @return void
	 */
	private function exclude_honeypots_from_query( \WP_Query $query, array $ids ): void {

		$existing = $query->get( 'post__not_in' );

		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		$query->set( 'post__not_in', array_merge( $existing, $ids ) );
	}

	/**
	 * Block direct access to a honeypot product in the main query.
	 *
	 * Checks whether the queried post name or ID matches a honeypot
	 * and forces a 404 if so.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_Query  $query The current query.
	 * @param array<int> $ids   Honeypot product IDs.
	 *
	 * @return void
	 */
	private function maybe_block_direct_access( \WP_Query $query, array $ids ): void {

		$post_id = $query->get( 'p' );

		if ( $post_id && in_array( (int) $post_id, $ids, true ) ) {
			$query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * Check whether the current request is a REST API request.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	private function is_rest_request(): bool {

		$result = defined( 'REST_REQUEST' ) && REST_REQUEST;

		return $result;
	}

	/**
	 * Get the client IP address.
	 *
	 * @since 0.1.0
	 *
	 * @return string Client IP address, or empty string if unavailable.
	 */
	private function get_client_ip(): string {

		$ip = '';

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Get the configured TTL for empty-search transients.
	 *
	 * @since 0.1.0
	 *
	 * @return int TTL in seconds.
	 */
	private function get_empty_search_ttl(): int {

		$settings = get_option( OPT_SETTINGS, [] );
		$ttl      = isset( $settings['empty_search_ttl'] ) ? absint( $settings['empty_search_ttl'] ) : DEF_EMPTY_SEARCH_TTL;

		return $ttl;
	}
}
