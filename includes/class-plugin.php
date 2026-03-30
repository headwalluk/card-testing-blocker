<?php
/**
 * Main plugin class.
 *
 * @since 0.1.0
 *
 * @package Card_Testing_Blocker
 */

namespace Card_Testing_Blocker;

defined( 'ABSPATH' ) || die();

/**
 * Plugin orchestrator.
 *
 * Registers all WordPress hooks and lazy-loads component classes
 * as needed. Accessed via the global ctb() helper.
 *
 * @since 0.1.0
 */
class Plugin {

	/**
	 * Honeypot products manager.
	 *
	 * @since 0.1.0
	 *
	 * @var ?Honeypot_Products
	 */
	private ?Honeypot_Products $honeypot_products = null;

	/**
	 * Query filter.
	 *
	 * @since 0.1.0
	 *
	 * @var ?Query_Filter
	 */
	private ?Query_Filter $query_filter = null;

	/**
	 * Threat scorer.
	 *
	 * @since 0.1.0
	 *
	 * @var ?Threat_Scorer
	 */
	private ?Threat_Scorer $threat_scorer = null;

	/**
	 * Order interceptor.
	 *
	 * @since 0.1.0
	 *
	 * @var ?Order_Interceptor
	 */
	private ?Order_Interceptor $order_interceptor = null;

	/**
	 * Settings manager.
	 *
	 * @since 0.1.0
	 *
	 * @var ?Settings
	 */
	private ?Settings $settings = null;

	/**
	 * Register all hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function run(): void {

		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Frontend query filtering.
		add_action( 'pre_get_posts', array( $this, 'on_pre_get_posts' ) );
		add_action( 'woocommerce_product_query', array( $this, 'on_product_query' ) );

		// Order interception.
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'on_checkout_validation' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_store_api_checkout' ) );

		// Admin.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'on_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'on_admin_init' ) );
		}
	}

	// -------------------------------------------------------------------------
	// Lifecycle.
	// -------------------------------------------------------------------------

	/**
	 * Plugin activation.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function activate(): void {

		$honeypot = new Honeypot_Products();
		$honeypot->create_products();
	}

	/**
	 * Plugin deactivation.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function deactivate(): void {

		$honeypot = new Honeypot_Products();
		$honeypot->remove_products();
	}

	// -------------------------------------------------------------------------
	// Hook callbacks.
	// -------------------------------------------------------------------------

	/**
	 * Load plugin translations.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function load_textdomain(): void {

		load_plugin_textdomain(
			'card-testing-blocker',
			false,
			dirname( CARD_TESTING_BLOCKER_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Filter queries to control honeypot product visibility.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_Query $query The current query.
	 *
	 * @return void
	 */
	public function on_pre_get_posts( \WP_Query $query ): void {

		$this->get_query_filter()->filter_pre_get_posts( $query );
	}

	/**
	 * Filter WooCommerce product queries.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_Query $query The product query.
	 *
	 * @return void
	 */
	public function on_product_query( \WP_Query $query ): void {

		$this->get_query_filter()->filter_product_query( $query );
	}

	/**
	 * Intercept classic checkout validation.
	 *
	 * @since 0.1.0
	 *
	 * @param array     $data   Checkout POST data.
	 * @param \WP_Error $errors Validation errors.
	 *
	 * @return void
	 */
	public function on_checkout_validation( array $data, \WP_Error $errors ): void {

		$this->get_order_interceptor()->validate_checkout( $data, $errors );
	}

	/**
	 * Intercept Store API / block checkout.
	 *
	 * @since 0.1.0
	 *
	 * @param \WC_Order $order The order being processed.
	 *
	 * @return void
	 */
	public function on_store_api_checkout( \WC_Order $order ): void {

		$this->get_order_interceptor()->validate_store_api_checkout( $order );
	}

	/**
	 * Register admin menu items.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function on_admin_menu(): void {

		$this->get_settings()->register_menu();
	}

	/**
	 * Register admin settings.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function on_admin_init(): void {

		$this->get_settings()->register_settings();
	}

	// -------------------------------------------------------------------------
	// Lazy-loaded component accessors.
	// -------------------------------------------------------------------------

	/**
	 * Get the honeypot products manager.
	 *
	 * @since 0.1.0
	 *
	 * @return Honeypot_Products
	 */
	public function get_honeypot_products(): Honeypot_Products {

		if ( is_null( $this->honeypot_products ) ) {
			$this->honeypot_products = new Honeypot_Products();
		}

		return $this->honeypot_products;
	}

	/**
	 * Get the query filter.
	 *
	 * @since 0.1.0
	 *
	 * @return Query_Filter
	 */
	public function get_query_filter(): Query_Filter {

		if ( is_null( $this->query_filter ) ) {
			$this->query_filter = new Query_Filter();
		}

		return $this->query_filter;
	}

	/**
	 * Get the threat scorer.
	 *
	 * @since 0.1.0
	 *
	 * @return Threat_Scorer
	 */
	public function get_threat_scorer(): Threat_Scorer {

		if ( is_null( $this->threat_scorer ) ) {
			$this->threat_scorer = new Threat_Scorer();
		}

		return $this->threat_scorer;
	}

	/**
	 * Get the order interceptor.
	 *
	 * @since 0.1.0
	 *
	 * @return Order_Interceptor
	 */
	public function get_order_interceptor(): Order_Interceptor {

		if ( is_null( $this->order_interceptor ) ) {
			$this->order_interceptor = new Order_Interceptor( $this->get_threat_scorer() );
		}

		return $this->order_interceptor;
	}

	/**
	 * Get the settings manager.
	 *
	 * @since 0.1.0
	 *
	 * @return Settings
	 */
	public function get_settings(): Settings {

		if ( is_null( $this->settings ) ) {
			$this->settings = new Settings();
		}

		return $this->settings;
	}
}
