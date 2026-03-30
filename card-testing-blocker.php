<?php
/**
 * Plugin Name:       Card Testing Blocker
 * Plugin URI:        https://github.com/headwalluk/card-testing-blocker
 * Description:       Detects and blocks card-testing botnet attacks on WooCommerce stores using honeypot products and intelligent threat scoring.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Headwall Tech
 * Author URI:        https://headwall.tech
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       card-testing-blocker
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * WC requires at least: 8.0
 * WC tested up to:      9.6
 *
 * @package Card_Testing_Blocker
 */

defined( 'ABSPATH' ) || die();

// Plugin identity.
define( 'CARD_TESTING_BLOCKER_VERSION', '0.1.0' );
define( 'CARD_TESTING_BLOCKER_TEXT_DOMAIN', 'card-testing-blocker' );

// Plugin paths and URL.
define( 'CARD_TESTING_BLOCKER_PLUGIN_FILE', __FILE__ );
define( 'CARD_TESTING_BLOCKER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CARD_TESTING_BLOCKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CARD_TESTING_BLOCKER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Constants.
require_once CARD_TESTING_BLOCKER_PLUGIN_DIR . 'constants.php';

// Declare WooCommerce HPOS compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

// Core classes.
require_once CARD_TESTING_BLOCKER_PLUGIN_DIR . 'includes/class-honeypot-products.php';
require_once CARD_TESTING_BLOCKER_PLUGIN_DIR . 'includes/class-plugin.php';

// Activation and deactivation.
register_activation_hook( __FILE__, array( 'Card_Testing_Blocker\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Card_Testing_Blocker\\Plugin', 'deactivate' ) );

/**
 * Return the single plugin instance.
 *
 * @since 0.1.0
 *
 * @return void
 */
function card_testing_blocker_plugin_run(): void {
	global $card_testing_blocker_plugin;
	$card_testing_blocker_plugin = new Card_Testing_Blocker\Plugin();
	$card_testing_blocker_plugin->run();
}
card_testing_blocker_plugin_run();
