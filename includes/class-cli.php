<?php
/**
 * WP-CLI commands.
 *
 * Provides CLI tools for managing honeypot products
 * and running visibility tests.
 *
 * @since 0.1.0
 *
 * @package Card_Testing_Blocker
 */

namespace Card_Testing_Blocker;

defined( 'ABSPATH' ) || die();

/**
 * Card Testing Blocker CLI commands.
 *
 * ## EXAMPLES
 *
 *     # Show honeypot status
 *     $ wp ctb status
 *
 *     # Create honeypot products
 *     $ wp ctb create
 *
 *     # List honeypot products
 *     $ wp ctb list
 *
 *     # Run visibility tests
 *     $ wp ctb test
 *
 * @since 0.1.0
 */
class CLI extends \WP_CLI_Command {

	/**
	 * Show honeypot product status.
	 *
	 * Displays the number of honeypot products, their price range,
	 * and the cheapest legitimate product price.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp ctb status
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function status( array $args, array $assoc_args ): void {

		$honeypot = new Honeypot_Products();
		$ids      = $honeypot->get_honeypot_product_ids();
		$count    = count( $ids );

		if ( 0 === $count ) {
			\WP_CLI::warning( 'No honeypot products exist.' );
			return;
		}

		$cheapest_legit = $honeypot->get_cheapest_legitimate_price();
		$price_range    = $honeypot->calculate_price_range( $cheapest_legit );

		$prices = [];
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$prices[] = (float) $product->get_price();
			}
		}

		$actual_min = count( $prices ) > 0 ? min( $prices ) : 0;
		$actual_max = count( $prices ) > 0 ? max( $prices ) : 0;

		\WP_CLI::log( '' );
		\WP_CLI::log( sprintf( '  Honeypot products:          %d', $count ) );
		\WP_CLI::log( sprintf( '  Actual price range:         %s – %s', wc_price( $actual_min ), wc_price( $actual_max ) ) );
		\WP_CLI::log( sprintf( '  Configured price range:     %s – %s', wc_price( $price_range['min'] ), wc_price( $price_range['max'] ) ) );
		\WP_CLI::log( sprintf( '  Cheapest legitimate product: %s', wc_price( $cheapest_legit ) ) );
		\WP_CLI::log( '' );
		\WP_CLI::success( sprintf( '%d honeypot products active.', $count ) );
	}

	/**
	 * Create honeypot products.
	 *
	 * Creates the configured number of honeypot products. Does nothing
	 * if honeypot products already exist — use `regenerate` instead.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp ctb create
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function create( array $args, array $assoc_args ): void {

		$honeypot = new Honeypot_Products();
		$existing = $honeypot->get_honeypot_product_ids();

		if ( count( $existing ) > 0 ) {
			\WP_CLI::warning( sprintf( '%d honeypot products already exist. Use `wp ctb regenerate` to recreate.', count( $existing ) ) );
			return;
		}

		$honeypot->create_products();

		$created = $honeypot->get_honeypot_product_ids();
		\WP_CLI::success( sprintf( 'Created %d honeypot products.', count( $created ) ) );
	}

	/**
	 * List honeypot products.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp ctb list
	 *     $ wp ctb list --format=json
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 *
	 * @subcommand list
	 */
	public function list_products( array $args, array $assoc_args ): void {

		$honeypot = new Honeypot_Products();
		$ids      = $honeypot->get_honeypot_product_ids();

		if ( 0 === count( $ids ) ) {
			\WP_CLI::warning( 'No honeypot products exist.' );
			return;
		}

		$items = [];

		foreach ( $ids as $id ) {

			$product = wc_get_product( $id );

			if ( $product ) {
				$items[] = [
					'ID'         => $id,
					'Name'       => $product->get_name(),
					'Price'      => $product->get_price(),
					'Visibility' => $product->get_catalog_visibility(),
					'Status'     => $product->get_status(),
					'Slug'       => $product->get_slug(),
				];
			}
		}

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		\WP_CLI\Utils\format_items( $format, $items, [ 'ID', 'Name', 'Price', 'Visibility', 'Status', 'Slug' ] );
	}

	/**
	 * Delete all honeypot products.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp ctb delete
	 *     $ wp ctb delete --yes
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function delete( array $args, array $assoc_args ): void {

		$honeypot = new Honeypot_Products();
		$ids      = $honeypot->get_honeypot_product_ids();
		$count    = count( $ids );

		if ( 0 === $count ) {
			\WP_CLI::warning( 'No honeypot products to delete.' );
			return;
		}

		\WP_CLI::confirm( sprintf( 'Delete %d honeypot products?', $count ), $assoc_args );

		$honeypot->remove_products();
		\WP_CLI::success( sprintf( 'Deleted %d honeypot products.', $count ) );
	}

	/**
	 * Regenerate honeypot products.
	 *
	 * Deletes existing honeypot products and creates new ones
	 * with recalculated pricing.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp ctb regenerate
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function regenerate( array $args, array $assoc_args ): void {

		$honeypot  = new Honeypot_Products();
		$old_count = count( $honeypot->get_honeypot_product_ids() );

		if ( $old_count > 0 ) {
			\WP_CLI::confirm( sprintf( 'Delete %d existing honeypot products and recreate?', $old_count ), $assoc_args );
		}

		$honeypot->regenerate_products();

		$new_count = count( $honeypot->get_honeypot_product_ids() );
		\WP_CLI::success( sprintf( 'Regenerated: %d removed, %d created.', $old_count, $new_count ) );
	}

	/**
	 * Run visibility tests.
	 *
	 * Makes HTTP requests to verify honeypot products appear in
	 * the right places and are hidden from the wrong ones.
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : Site URL to test against. Defaults to home_url().
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp ctb test
	 *     $ wp ctb test --url=https://example.com
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function test( array $args, array $assoc_args ): void {

		$honeypot = new Honeypot_Products();
		$ids      = $honeypot->get_honeypot_product_ids();

		if ( 0 === count( $ids ) ) {
			\WP_CLI::error( 'No honeypot products exist. Run `wp ctb create` first.' );
			return;
		}

		$site_url = isset( $assoc_args['url'] ) ? $assoc_args['url'] : home_url();

		\WP_CLI::log( '' );
		\WP_CLI::log( sprintf( 'Running visibility tests against: %s', $site_url ) );
		\WP_CLI::log( sprintf( 'Honeypot products: %d (IDs: %s)', count( $ids ), implode( ', ', $ids ) ) );
		\WP_CLI::log( '' );

		$test_runner = new Visibility_Tests( $site_url, $ids );
		$suite       = $test_runner->run_all();

		foreach ( $suite['results'] as $result ) {

			$icon = $result['passed'] ? '✓' : '✗';

			if ( $result['passed'] ) {
				\WP_CLI::log( \WP_CLI::colorize( sprintf( '  %%g%s%%n  %s — %s', $icon, $result['name'], $result['message'] ) ) );
			} else {
				\WP_CLI::log( \WP_CLI::colorize( sprintf( '  %%r%s%%n  %s — %s', $icon, $result['name'], $result['message'] ) ) );
			}
		}

		\WP_CLI::log( '' );

		$passed_count = 0;
		$total_count  = count( $suite['results'] );

		foreach ( $suite['results'] as $result ) {
			if ( $result['passed'] ) {
				++$passed_count;
			}
		}

		if ( $suite['passed'] ) {
			\WP_CLI::success( sprintf( 'All %d tests passed.', $total_count ) );
		} else {
			\WP_CLI::error( sprintf( '%d of %d tests failed.', $total_count - $passed_count, $total_count ) );
		}
	}
}
