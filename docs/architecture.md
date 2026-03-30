# Architecture Overview

This document describes the internal architecture of Card Testing Blocker for developers who want to understand or extend the plugin.

---

## Directory Structure

```
card-testing-blocker/
├── card-testing-blocker.php          # Plugin bootstrap
├── constants.php                     # All constants
├── includes/
│   ├── class-plugin.php              # Main class, hook registration
│   ├── class-honeypot-products.php   # Honeypot product lifecycle
│   ├── class-query-filter.php        # Query modification
│   ├── class-threat-scorer.php       # Scoring engine
│   ├── class-order-interceptor.php   # Checkout interception
│   ├── class-settings.php            # Admin settings
│   └── class-signals/
│       ├── class-honeypot-signal.php       # Honeypot detection
│       ├── class-empty-search-signal.php   # Empty search tracking
│       └── class-protocol-signal.php       # HTTP protocol check
├── admin-templates/                  # Admin page templates
├── assets/                           # CSS/JS assets
├── docs/                             # This documentation
├── languages/                        # Translation files
└── dev-notes/                        # Internal dev notes (not distributed)
```

---

## Component Overview

### Bootstrap (`card-testing-blocker.php`)

The main plugin file handles:
- Plugin header (metadata for WordPress)
- Autoloading or requiring class files
- Activation hook: triggers honeypot product creation
- Deactivation hook: triggers honeypot product removal
- Instantiates `Plugin` class and calls `run()`

### Plugin (`class-plugin.php`)

Central orchestrator. Instantiates all components and registers WordPress hooks. Uses lazy loading where possible.

### Honeypot Products (`class-honeypot-products.php`)

Manages the lifecycle of honeypot products:

- **Creation:** Queries the cheapest legitimate (non-honeypot) product, calculates a price range, creates N simple products with realistic names
- **Identification:** All honeypot products have `_ctb_honeypot` = `1` post meta
- **Pricing:** Distributed from a base price up to half the cheapest legitimate product price
- **Cleanup:** Removes all honeypot products on deactivation (uses `wp_delete_post` with force)

### Query Filter (`class-query-filter.php`)

Controls where honeypot products appear:

- Hooks `woocommerce_product_query` to exclude honeypots from shop/archive pages
- Hooks `pre_get_posts` to handle search queries:
  - Non-empty search: excludes honeypots
  - Empty search (`?s=` with `post_type=product`): includes honeypots, ensures they sort first
- Handles direct URL access to honeypot products (404)
- Allows honeypots through on REST API and Store API requests

### Threat Scorer (`class-threat-scorer.php`)

The scoring engine:

1. Collects registered signals (built-in + any added via `ctb_threat_signals` filter)
2. Builds a context array from the current request
3. Passes context to each signal's `get_score()` method
4. Sums all scores
5. Compares against threshold (configurable, filterable via `ctb_threat_threshold`)
6. Returns result with score breakdown

### Signals (`class-signals/`)

Each signal is a self-contained detection check:

- **Honeypot Signal:** Iterates cart items, checks for `_ctb_honeypot` meta. Score: 100.
- **Empty Search Signal:** On `pre_get_posts`, stores IP in transient when empty search detected. At checkout, checks if transient exists for current IP. Score: 30.
- **Protocol Signal:** Reads HTTP version from `$_SERVER` or custom header. If HTTP/1.1 on a site expecting HTTP/2, triggers. Score: 10.

### Order Interceptor (`class-order-interceptor.php`)

Hooks into WooCommerce checkout at the validation stage:

- `woocommerce_after_checkout_validation` — Classic checkout
- `woocommerce_store_api_checkout_order_processed` — Block/Store API checkout

When triggered:
1. Builds context array (fires `ctb_order_context` filter)
2. Passes to Threat Scorer
3. Fires `ctb_order_score_calculated` action
4. If over threshold: fires `ctb_order_blocked` action, logs attempt, blocks with error
5. If under threshold: checkout proceeds normally

### Settings (`class-settings.php`)

Admin settings page under WooCommerce menu:

- Uses WordPress Settings API
- Stores all settings in a single option (`ctb_settings`) as an associative array
- Provides UI for threshold, signal weights, honeypot count, TTLs
- Displays honeypot product status and blocked attempts log

---

## Data Storage

| Data | Storage | Lifetime |
|------|---------|----------|
| Plugin settings | `wp_options` (`ctb_settings`) | Permanent (until uninstall) |
| Honeypot products | `wp_posts` (product post type) | Plugin active period |
| Honeypot product marker | `wp_postmeta` (`_ctb_honeypot`) | With product |
| Empty search IPs | Transients (`ctb_empty_search_{hash}`) | Configurable TTL (default 1hr) |
| Blocked attempt log | TBD (option or custom table) | Configurable retention |

---

## Request Flow

```
Bot visits /?s=&post_type=product
    │
    ├── pre_get_posts fires
    │   └── Empty Search Signal stores IP in transient
    │       └── ctb_empty_search_detected action fires
    │
    ├── Query returns honeypot products (sorted cheapest first)
    │
Bot adds honeypot product to cart (?add-to-cart=XXX)
    │
Bot submits checkout (?wc-ajax=checkout)
    │
    ├── woocommerce_after_checkout_validation fires
    │   └── Order Interceptor builds context
    │       └── Threat Scorer evaluates signals:
    │           ├── Honeypot Signal: +100 (honeypot in cart)
    │           ├── Empty Search Signal: +30 (IP in transient)
    │           └── Protocol Signal: +10 (HTTP/1.1)
    │           = Total: 140
    │
    │       └── 140 > 50 (threshold) → BLOCKED
    │           ├── ctb_order_blocked action fires
    │           ├── Attempt logged
    │           └── wc_add_notice( error ) returned
    │
    └── Checkout fails, no payment processed
```
