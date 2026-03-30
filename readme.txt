=== Card Testing Blocker ===
Contributors: headwalltech
Tags: woocommerce, security, card-testing, fraud, honeypot
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
WC requires at least: 8.0
WC tested up to: 9.6
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Detects and blocks card-testing botnet attacks on WooCommerce stores using honeypot products and intelligent threat scoring.

== Description ==

Card Testing Blocker protects your WooCommerce store from card-testing botnet attacks — automated bots that search for cheap products and attempt checkout with stolen credit card numbers.

**How it works:**

1. The plugin creates invisible "honeypot" products priced below your cheapest real product
2. These products are hidden from your real customers but appear when bots search for cheap items
3. When a bot adds a honeypot product to cart and attempts checkout, the order is blocked
4. Multiple threat signals are combined into a score — if the score exceeds your threshold, the checkout is blocked before any payment is processed

**Threat signals include:**

* Honeypot product detected in cart (high confidence)
* IP address recently performed an empty product search (medium confidence)
* Request uses HTTP/1.1 on an HTTP/2 configured server (low confidence)

The scoring system is extensible — developers can register custom signals via filters.

**Features:**

* Zero impact on legitimate customers
* Blocks attacks before payment processing (no gateway fees)
* Configurable threat threshold and signal weights
* Works with classic and block checkout
* WooCommerce HPOS compatible
* Extensible via hooks and filters

== Installation ==

1. Upload `card-testing-blocker` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to WooCommerce > Card Testing Blocker to configure settings
4. The plugin automatically creates honeypot products on activation

== Frequently Asked Questions ==

= Will honeypot products appear in my shop? =

No. Honeypot products are hidden from your shop pages, category pages, search results, widgets, and direct URL access. They only appear in specific query patterns used by card-testing bots.

= What happens when a bot is blocked? =

The checkout fails with a generic error message. No payment is processed, so you incur no gateway fees. The attempt is logged for your review.

= Does this work with PayPal / Stripe / other gateways? =

Yes. The plugin blocks at the WooCommerce checkout validation stage, before any payment gateway is called.

= Will this slow down my checkout? =

No. The scoring checks are lightweight — a transient lookup and a meta check on cart items. There is no external API call or heavy processing.

= Can I add custom threat signals? =

Yes. Use the `ctb_threat_signals` filter to register additional signal classes. See the developer documentation for details.

= What happens if I deactivate the plugin? =

All honeypot products are removed automatically on deactivation. No orphaned data is left behind.

== Changelog ==

= 0.1.0 =
* Plugin bootstrap, constants, and main plugin class
* Project documentation and development planning

== Upgrade Notice ==

= 0.1.0 =
Initial development release.
