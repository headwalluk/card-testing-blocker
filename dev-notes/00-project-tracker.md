# Project Tracker

**Version:** 0.1.0
**Last Updated:** 30 March 2026
**Current Phase:** Milestone 2 (Query Filtering)
**Overall Progress:** 15%

---

## Overview

Card Testing Blocker is a WooCommerce plugin that detects and blocks card-testing botnet attacks using a honeypot product + threat scoring system. Bots discover products via empty search queries (`?s=&post_type=product`), add the cheapest product to cart, and attempt checkout with stolen card details. This plugin creates decoy products that are invisible to real customers but irresistible to bots, then blocks any order containing those products.

---

## Active TODO Items

- [ ] Complete M2: Query filtering
- [ ] Complete M3: Threat scoring engine

---

## Milestones

### M1: Plugin Bootstrap & Honeypot Products

Core plugin scaffold and the honeypot product creation system.

- [x] Create main plugin file (`card-testing-blocker.php`) with plugin header, activation/deactivation hooks
- [x] Create `constants.php` with all plugin constants (prefixes, default values, score weights)
- [x] Create `includes/class-plugin.php` — main plugin class with hook registration
- [x] Create `includes/class-honeypot-products.php` — honeypot product CRUD
  - [x] Activation: query cheapest legitimate product price
  - [x] Activation: create honeypot products with realistic names/descriptions
  - [x] Dynamic pricing: range from a base price up to half the cheapest legitimate product
  - [x] Mark honeypot products with `_ctb_honeypot` meta
  - [x] Set catalog visibility to `hidden`
  - [x] Ensure enough products to fill a full page of results (configurable count)
  - [x] Deactivation: remove all honeypot products cleanly
- [x] Declare WooCommerce HPOS compatibility
- [x] Create `phpcs.xml` with plugin-specific configuration

### M2: Query Filtering

Hide honeypot products from legitimate customers while ensuring bots find them.

- [ ] Create `includes/class-query-filter.php`
- [ ] Hide honeypots from WooCommerce shop/archive pages (`woocommerce_product_query`)
- [ ] Hide honeypots from search results when search term is non-empty
- [ ] Show honeypots in empty search queries (`?s=&post_type=product`)
- [ ] Hide honeypots from WooCommerce product widgets
- [ ] Hide honeypots from related products
- [ ] Handle direct URL access to honeypot products (404 or redirect)
- [ ] Ensure honeypots appear in WooCommerce REST API product listings
- [ ] Verify honeypot products sort first by price (cheapest first)

### M3: Threat Scoring Engine

Extensible scoring system for evaluating checkout attempts.

- [ ] Create `includes/class-threat-scorer.php` — scoring engine
  - [ ] Signal registration mechanism (filter-based for extensibility)
  - [ ] Score calculation: iterate registered signals, sum scores
  - [ ] Threshold comparison with configurable threshold
  - [ ] Provide `ctb_threat_signals` filter for third-party signal registration
  - [ ] Provide `ctb_threat_threshold` filter for threshold adjustment
- [ ] Create `includes/class-signals/class-honeypot-signal.php`
  - [ ] Check cart items for `_ctb_honeypot` meta
  - [ ] Return configured score (default: 100)
- [ ] Create `includes/class-signals/class-empty-search-signal.php`
  - [ ] Hook into `pre_get_posts` to detect empty search + product post type
  - [ ] Store IP in transient with configurable TTL (default: 1 hour)
  - [ ] At checkout, check if current IP has a transient hit
  - [ ] Return configured score (default: 30)
- [ ] Create `includes/class-signals/class-protocol-signal.php`
  - [ ] Read HTTP protocol version from server variable or custom header
  - [ ] Compare against expected protocol (configurable, default: HTTP/2.0)
  - [ ] Return configured score (default: 10)
  - [ ] Degrade gracefully if protocol header not available

### M4: Order Interception

Block checkout when threat score exceeds threshold.

- [ ] Create `includes/class-order-interceptor.php`
- [ ] Hook into `woocommerce_after_checkout_validation` for classic checkout
- [ ] Hook into `woocommerce_store_api_checkout_order_processed` for block checkout
- [ ] Build context array from current request (cart items, IP, UA, protocol)
- [ ] Run threat scorer, get total score and triggered signals
- [ ] If over threshold: block with `wc_add_notice()` error / `wp_die()` as appropriate
- [ ] Log blocked attempts (IP, UA, score breakdown, timestamp)
- [ ] Provide `ctb_order_blocked` action for extensibility
- [ ] Provide `ctb_order_score_calculated` action for monitoring/logging

### M5: Admin Settings & UI

Settings page for store administrators.

- [ ] Create `includes/class-settings.php`
- [ ] Register settings page under WooCommerce menu
- [ ] Settings: enable/disable plugin
- [ ] Settings: threat score threshold (default: 50)
- [ ] Settings: honeypot product count (default: 20)
- [ ] Settings: individual signal enable/disable and score weights
- [ ] Settings: empty search transient TTL
- [ ] Settings: HTTP protocol expected version
- [ ] Display honeypot product status (created count, price range)
- [ ] Button to regenerate honeypot products (recalculate prices)
- [ ] Blocked attempts log viewer (recent blocks with score breakdowns)

### M6: REST API & Additional Signals

Extend protection to cover REST API attack vectors.

- [ ] Ensure honeypot products appear in WC REST API (`/wp-json/wc/v3/products`)
- [ ] Ensure honeypot products appear in WC Store API (`/wp-json/wc/store/v1/products`)
- [ ] Hook into REST API order creation endpoints
- [ ] Consider additional signals:
  - [ ] Rotating user agent detection (track UA changes per IP/session)
  - [ ] Checkout speed (cart-to-checkout time below threshold)
  - [ ] Known bot user agent patterns (ancient browsers like IE7, IE9)
  - [ ] Geographic velocity (optional, if GeoIP available)

### M6b: Sitemap & SEO Integrations

Exclude honeypot products from sitemaps. Use `integrations/` directory for third-party SEO plugin support.

- [ ] Create `integrations/` directory structure
- [ ] Exclude honeypot products from WordPress core sitemap (`wp_sitemaps_posts_query_args`)
- [ ] Yoast SEO integration (`wpseo_exclude_from_sitemap_by_post_ids` or `wpseo_sitemap_entry`)
- [ ] RankMath integration
- [ ] Auto-detect which SEO plugin is active and load the appropriate integration

### M7: Polish & Release

Final hardening, documentation, and release preparation.

- [ ] Full PHPCS compliance pass
- [ ] Complete all docs/ documentation
- [ ] Complete `readme.txt` with screenshots section
- [ ] Test activation/deactivation/uninstall cycle
- [ ] Test with WooCommerce block checkout
- [ ] Test with WooCommerce classic checkout
- [ ] Test with popular payment gateways (PayPal, Stripe)
- [ ] Test with page caching plugins
- [ ] Performance review: ensure no impact on legitimate checkout speed
- [ ] Review all TODO/FIXME comments in code
- [ ] Tag version 1.0.0

---

## Technical Debt

_(None yet — fresh project)_

---

## Notes for Development

- Bot pattern discovered 30 March 2026: bots use `?s=&post_type=product` (empty frontend search), NOT the REST API
- Bots rotate user agents per attempt (all ancient/fake: IE9, Chrome 35, Firefox 13, IE7)
- Bots use HTTP/1.1 on HTTP/2.0 configured servers
- Payment flow observed: PayPal Commerce Platform (`ppc-create-order` / `ppc-approve-order`)
- ~13-15 minutes between attempts from same IP
- Bots add-to-cart via simple URL (`?add-to-cart={id}`)
- Product IDs vary between attempts (not always the single cheapest)
