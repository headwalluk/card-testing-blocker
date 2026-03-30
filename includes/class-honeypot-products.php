<?php
/**
 * Honeypot product lifecycle management.
 *
 * Creates, prices, and removes decoy products used to trap
 * card-testing bots.
 *
 * @since 0.1.0
 *
 * @package Card_Testing_Blocker
 */

namespace Card_Testing_Blocker;

defined( 'ABSPATH' ) || die();

/**
 * Manages honeypot product creation, pricing, and removal.
 *
 * @since 0.1.0
 */
class Honeypot_Products {

	/**
	 * Pool of realistic product names for honeypot products.
	 *
	 * Intentionally generic — the kind of cheap items any store might stock.
	 * Filterable via `ctb_honeypot_product_names`.
	 *
	 * @since 0.1.0
	 *
	 * @var array<string>
	 */
	private const PRODUCT_NAME_POOL = [
		'Digital Gift Card',
		'E-Gift Voucher',
		'Shipping Protection',
		'Sample Pack',
		'Mystery Sample',
		'Branded Sticker Pack',
		'Printed Postcard Set',
		'Mini Colour Chart',
		'Branded Pen',
		'Keyring',
		'Branded Tote Bag',
		'Pin Badge',
		'Branded Bookmark',
		'Phone Grip',
		'Cable Tidy',
		'Lens Cloth',
		'Magnet Set',
		'Wristband',
		'Branded Coaster',
		'Sample Swatch Book',
	];

	/**
	 * Create honeypot products on activation.
	 *
	 * Queries the cheapest legitimate product to determine pricing,
	 * then creates the configured number of decoy products.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function create_products(): void {

		$existing = $this->get_honeypot_product_ids();

		if ( count( $existing ) > 0 ) {
			return;
		}

		$count            = $this->get_product_count();
		$cheapest_price   = $this->get_cheapest_legitimate_price();
		$price_range      = $this->calculate_price_range( $cheapest_price );
		$names            = $this->get_product_names( $count );
		$created_ids      = [];

		for ( $i = 0; $i < $count; $i++ ) {

			$price      = $this->interpolate_price( $i, $count, $price_range['min'], $price_range['max'] );
			$product_id = $this->create_single_product( $names[ $i ], $price );

			if ( $product_id > 0 ) {
				$created_ids[] = $product_id;
			}
		}

		/**
		 * Fires after honeypot products are created.
		 *
		 * @since 0.1.0
		 *
		 * @param array $created_ids Array of created product IDs.
		 * @param array $price_range Price range used: [ 'min' => float, 'max' => float ].
		 */
		do_action( 'ctb_honeypot_products_created', $created_ids, $price_range );
	}

	/**
	 * Remove all honeypot products on deactivation.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function remove_products(): void {

		$ids = $this->get_honeypot_product_ids();

		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	/**
	 * Regenerate honeypot products.
	 *
	 * Removes existing products and creates fresh ones with
	 * recalculated pricing.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function regenerate_products(): void {

		$this->remove_products();
		$this->create_products();
	}

	/**
	 * Get all honeypot product IDs.
	 *
	 * @since 0.1.0
	 *
	 * @return array<int> Array of product post IDs.
	 */
	public function get_honeypot_product_ids(): array {

		$query = new \WP_Query(
			[
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to identify honeypot products.
					[
						'key'   => META_HONEYPOT,
						'value' => '1',
					],
				],
				'no_found_rows'  => true,
			]
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Check whether a product ID is a honeypot product.
	 *
	 * @since 0.1.0
	 *
	 * @param int $product_id Product post ID.
	 *
	 * @return bool
	 */
	public function is_honeypot_product( int $product_id ): bool {

		return '1' === get_post_meta( $product_id, META_HONEYPOT, true );
	}

	// -------------------------------------------------------------------------
	// Internal helpers.
	// -------------------------------------------------------------------------

	/**
	 * Create a single honeypot product.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name  Product name.
	 * @param float  $price Product price.
	 *
	 * @return int Created product ID, or 0 on failure.
	 */
	private function create_single_product( string $name, float $price ): int {

		$result = 0;

		$product = new \WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( (string) $price );
		$product->set_price( $price );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_reviews_allowed( false );
		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );
		$product->set_description( $name );
		$product->set_short_description( $name );

		$product_id = $product->save();

		if ( $product_id > 0 ) {
			update_post_meta( $product_id, META_HONEYPOT, '1' );
			$result = $product_id;
		}

		return $result;
	}

	/**
	 * Get the price of the cheapest legitimate (non-honeypot) product.
	 *
	 * Falls back to a sensible default if no products exist.
	 *
	 * @since 0.1.0
	 *
	 * @return float Cheapest product price.
	 */
	public function get_cheapest_legitimate_price(): float {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time activation query, no cache needed.
		$price = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN( CAST( pm_price.meta_value AS DECIMAL(10,2) ) )
				FROM {$wpdb->postmeta} pm_price
				INNER JOIN {$wpdb->posts} p ON p.ID = pm_price.post_id
				LEFT JOIN {$wpdb->postmeta} pm_honeypot ON pm_honeypot.post_id = p.ID AND pm_honeypot.meta_key = %s
				WHERE pm_price.meta_key = '_price'
				AND CAST( pm_price.meta_value AS DECIMAL(10,2) ) > 0
				AND p.post_type = 'product'
				AND p.post_status = 'publish'
				AND ( pm_honeypot.meta_value IS NULL OR pm_honeypot.meta_value != '1' )",
				META_HONEYPOT
			)
		);

		$result = null !== $price ? (float) $price : 10.00;

		return $result;
	}

	/**
	 * Calculate the min and max price for honeypot products.
	 *
	 * Range runs from the base price up to half the cheapest legitimate price.
	 * Filterable via `ctb_honeypot_price_range`.
	 *
	 * @since 0.1.0
	 *
	 * @param float $cheapest_price Cheapest legitimate product price.
	 *
	 * @return array{min: float, max: float} Price range.
	 */
	public function calculate_price_range( float $cheapest_price ): array {

		$max = round( $cheapest_price / 2, 2 );
		$min = DEF_HONEYPOT_MIN_PRICE;

		// Ensure min does not exceed max.
		if ( $min >= $max ) {
			$min = round( $max / 2, 2 );
		}

		// Ensure we still have a valid minimum.
		if ( $min <= 0 ) {
			$min = 0.01;
		}

		$range = [
			'min' => $min,
			'max' => $max,
		];

		/**
		 * Filter the honeypot product price range.
		 *
		 * @since 0.1.0
		 *
		 * @param array $range                 Price range: [ 'min' => float, 'max' => float ].
		 * @param float $cheapest_price Cheapest legitimate product price.
		 */
		$range = apply_filters( 'ctb_honeypot_price_range', $range, $cheapest_price );

		return $range;
	}

	/**
	 * Interpolate a price for a given product index within the range.
	 *
	 * Distributes prices evenly between min and max.
	 *
	 * @since 0.1.0
	 *
	 * @param int   $index Product index (0-based).
	 * @param int   $total Total number of products.
	 * @param float $min   Minimum price.
	 * @param float $max   Maximum price.
	 *
	 * @return float Calculated price rounded to 2 decimal places.
	 */
	private function interpolate_price( int $index, int $total, float $min, float $max ): float {

		$result = $min;

		if ( $total > 1 ) {
			$fraction = $index / ( $total - 1 );
			$result   = round( $min + ( $fraction * ( $max - $min ) ), 2 );
		}

		return $result;
	}

	/**
	 * Get product names for the honeypot products.
	 *
	 * Returns exactly $count names, cycling through the pool if needed.
	 * Filterable via `ctb_honeypot_product_names`.
	 *
	 * @since 0.1.0
	 *
	 * @param int $count Number of names needed.
	 *
	 * @return array<string> Product names.
	 */
	private function get_product_names( int $count ): array {

		/**
		 * Filter the pool of honeypot product names.
		 *
		 * @since 0.1.0
		 *
		 * @param array $names Array of product name strings.
		 */
		$pool = apply_filters( 'ctb_honeypot_product_names', self::PRODUCT_NAME_POOL );

		$names      = [];
		$pool_count = count( $pool );

		for ( $i = 0; $i < $count; $i++ ) {
			$names[] = $pool[ $i % $pool_count ];
		}

		return $names;
	}

	/**
	 * Get the configured number of honeypot products to create.
	 *
	 * @since 0.1.0
	 *
	 * @return int Product count.
	 */
	private function get_product_count(): int {

		$settings = get_option( OPT_SETTINGS, [] );
		$count    = isset( $settings['honeypot_count'] ) ? absint( $settings['honeypot_count'] ) : DEF_HONEYPOT_COUNT;

		return $count;
	}
}
