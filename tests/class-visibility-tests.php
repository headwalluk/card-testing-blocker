<?php
/**
 * Visibility test runner.
 *
 * Makes real HTTP requests to verify honeypot products appear
 * in the right places and are hidden from the wrong ones.
 *
 * @since 0.1.0
 *
 * @package Card_Testing_Blocker
 */

namespace Card_Testing_Blocker;

defined( 'ABSPATH' ) || die();

/**
 * Runs HTTP-based integration tests to verify honeypot product visibility.
 *
 * @since 0.1.0
 */
class Visibility_Tests {

	/**
	 * Base URL for the site under test.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	private string $site_url;

	/**
	 * Honeypot product IDs to check for.
	 *
	 * @since 0.1.0
	 *
	 * @var array<int>
	 */
	private array $honeypot_ids;

	/**
	 * Honeypot product slugs to check for.
	 *
	 * @since 0.1.0
	 *
	 * @var array<string>
	 */
	private array $honeypot_slugs;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param string     $site_url      Base URL (e.g., https://example.com).
	 * @param array<int> $honeypot_ids  Honeypot product IDs.
	 */
	public function __construct( string $site_url, array $honeypot_ids ) {

		$this->site_url      = untrailingslashit( $site_url );
		$this->honeypot_ids  = $honeypot_ids;
		$this->honeypot_slugs = $this->resolve_slugs( $honeypot_ids );
	}

	/**
	 * Run all visibility tests.
	 *
	 * @since 0.1.0
	 *
	 * @return array{passed: bool, results: array<array{name: string, passed: bool, message: string}>}
	 */
	public function run_all(): array {

		$results = [
			$this->test_empty_search_contains_honeypots(),
			$this->test_nonempty_search_excludes_honeypots(),
			$this->test_shop_page_excludes_honeypots(),
			$this->test_rest_api_contains_honeypots(),
			$this->test_direct_honeypot_url_returns_404(),
		];

		$all_passed = true;

		foreach ( $results as $result ) {
			if ( ! $result['passed'] ) {
				$all_passed = false;
				break;
			}
		}

		$output = [
			'passed'  => $all_passed,
			'results' => $results,
		];

		return $output;
	}

	/**
	 * Test: Empty search should contain honeypot products.
	 *
	 * @since 0.1.0
	 *
	 * @return array{name: string, passed: bool, message: string}
	 */
	public function test_empty_search_contains_honeypots(): array {

		$name     = 'empty_search_contains_honeypots';
		$url      = $this->site_url . '/?s=&post_type=product';
		$response = $this->fetch( $url );

		$passed  = false;
		$message = '';

		if ( is_wp_error( $response ) ) {
			$message = 'HTTP request failed: ' . $response->get_error_message();
		} else {
			$body  = wp_remote_retrieve_body( $response );
			$found = $this->find_product_ids_in_html( $body );
			$hits  = array_intersect( $this->honeypot_ids, $found );

			if ( count( $hits ) > 0 ) {
				$passed  = true;
				$message = sprintf( 'Found %d honeypot product(s) in empty search results.', count( $hits ) );
			} else {
				$message = 'No honeypot products found in empty search results.';
			}
		}

		return [
			'name'    => $name,
			'passed'  => $passed,
			'message' => $message,
		];
	}

	/**
	 * Test: Non-empty search should exclude honeypot products.
	 *
	 * @since 0.1.0
	 *
	 * @return array{name: string, passed: bool, message: string}
	 */
	public function test_nonempty_search_excludes_honeypots(): array {

		$name     = 'nonempty_search_excludes_honeypots';
		$url      = $this->site_url . '/?s=test&post_type=product';
		$response = $this->fetch( $url );

		$passed  = false;
		$message = '';

		if ( is_wp_error( $response ) ) {
			$message = 'HTTP request failed: ' . $response->get_error_message();
		} else {
			$body  = wp_remote_retrieve_body( $response );
			$found = $this->find_product_ids_in_html( $body );
			$hits  = array_intersect( $this->honeypot_ids, $found );

			if ( 0 === count( $hits ) ) {
				$passed  = true;
				$message = 'No honeypot products found in non-empty search results. Good.';
			} else {
				$message = sprintf( 'Found %d honeypot product(s) in non-empty search — they should be hidden.', count( $hits ) );
			}
		}

		return [
			'name'    => $name,
			'passed'  => $passed,
			'message' => $message,
		];
	}

	/**
	 * Test: Shop page should exclude honeypot products.
	 *
	 * @since 0.1.0
	 *
	 * @return array{name: string, passed: bool, message: string}
	 */
	public function test_shop_page_excludes_honeypots(): array {

		$name     = 'shop_page_excludes_honeypots';
		$shop_url = $this->get_shop_url();
		$response = $this->fetch( $shop_url );

		$passed  = false;
		$message = '';

		if ( is_wp_error( $response ) ) {
			$message = 'HTTP request failed: ' . $response->get_error_message();
		} else {
			$body  = wp_remote_retrieve_body( $response );
			$found = $this->find_product_ids_in_html( $body );
			$hits  = array_intersect( $this->honeypot_ids, $found );

			if ( 0 === count( $hits ) ) {
				$passed  = true;
				$message = 'No honeypot products found on shop page. Good.';
			} else {
				$message = sprintf( 'Found %d honeypot product(s) on shop page — they should be hidden.', count( $hits ) );
			}
		}

		return [
			'name'    => $name,
			'passed'  => $passed,
			'message' => $message,
		];
	}

	/**
	 * Test: REST API should contain honeypot products.
	 *
	 * @since 0.1.0
	 *
	 * @return array{name: string, passed: bool, message: string}
	 */
	public function test_rest_api_contains_honeypots(): array {

		$name     = 'rest_api_contains_honeypots';
		$url      = $this->site_url . '/wp-json/wc/store/v1/products?per_page=100&orderby=price&order=asc';
		$response = $this->fetch( $url );

		$passed  = false;
		$message = '';

		if ( is_wp_error( $response ) ) {
			$message = 'HTTP request failed: ' . $response->get_error_message();
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( 200 !== $code || ! is_array( $data ) ) {
				$message = sprintf( 'Unexpected REST API response (HTTP %d).', $code );
			} else {
				$api_ids = array_map(
					function ( $product ) {
						return isset( $product['id'] ) ? (int) $product['id'] : 0;
					},
					$data
				);
				$hits    = array_intersect( $this->honeypot_ids, $api_ids );

				if ( count( $hits ) > 0 ) {
					$passed  = true;
					$message = sprintf( 'Found %d honeypot product(s) in Store API results.', count( $hits ) );
				} else {
					$message = 'No honeypot products found in Store API results.';
				}
			}
		}

		return [
			'name'    => $name,
			'passed'  => $passed,
			'message' => $message,
		];
	}

	/**
	 * Test: Direct honeypot product URLs should return 404.
	 *
	 * @since 0.1.0
	 *
	 * @return array{name: string, passed: bool, message: string}
	 */
	public function test_direct_honeypot_url_returns_404(): array {

		$name        = 'direct_honeypot_url_returns_404';
		$all_are_404 = true;
		$details     = [];

		foreach ( $this->honeypot_ids as $id ) {

			$url      = $this->site_url . '/?p=' . $id;
			$response = $this->fetch( $url );

			if ( is_wp_error( $response ) ) {
				$all_are_404 = false;
				$details[]   = sprintf( 'ID %d: request failed (%s)', $id, $response->get_error_message() );
			} else {
				$code = wp_remote_retrieve_response_code( $response );

				if ( 404 !== $code ) {
					$all_are_404 = false;
					$details[]   = sprintf( 'ID %d: expected 404, got %d', $id, $code );
				}
			}
		}

		$passed  = $all_are_404;
		$message = $passed
			? sprintf( 'All %d honeypot product URLs returned 404.', count( $this->honeypot_ids ) )
			: 'Some honeypot URLs did not 404: ' . implode( '; ', $details );

		return [
			'name'    => $name,
			'passed'  => $passed,
			'message' => $message,
		];
	}

	// -------------------------------------------------------------------------
	// Internal helpers.
	// -------------------------------------------------------------------------

	/**
	 * Make an HTTP GET request.
	 *
	 * @since 0.1.0
	 *
	 * @param string $url URL to fetch.
	 *
	 * @return array|\WP_Error Response array or WP_Error.
	 */
	private function fetch( string $url ) {

		$response = wp_remote_get(
			$url,
			[
				'timeout'     => 30,
				'sslverify'   => false,
				'redirection' => 0,
				'user-agent'  => 'Card-Testing-Blocker-Tests/1.0',
			]
		);

		return $response;
	}

	/**
	 * Find WooCommerce product IDs in HTML response body.
	 *
	 * Looks for CSS classes in the format `post-{id}` which
	 * WooCommerce adds to product listing items.
	 *
	 * @since 0.1.0
	 *
	 * @param string $html HTML response body.
	 *
	 * @return array<int> Found product IDs.
	 */
	private function find_product_ids_in_html( string $html ): array {

		$ids = [];

		if ( preg_match_all( '/\bpost-(\d+)\b/', $html, $matches ) ) {
			$ids = array_unique( array_map( 'intval', $matches[1] ) );
		}

		return $ids;
	}

	/**
	 * Resolve product slugs from IDs for URL matching.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int> $ids Product IDs.
	 *
	 * @return array<string> Product slugs.
	 */
	private function resolve_slugs( array $ids ): array {

		$slugs = [];

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( $post ) {
				$slugs[] = $post->post_name;
			}
		}

		return $slugs;
	}

	/**
	 * Get the shop page URL.
	 *
	 * @since 0.1.0
	 *
	 * @return string Shop page URL.
	 */
	private function get_shop_url(): string {

		$url = '';

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'shop' );
		}

		if ( empty( $url ) ) {
			$url = $this->site_url . '/shop/';
		}

		return $url;
	}
}
