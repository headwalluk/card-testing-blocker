# Testing Guide

Card Testing Blocker includes a visibility test suite that verifies honeypot products are correctly shown to bots and hidden from legitimate customers. The tests make real HTTP requests against a live WordPress site.

---

## Running the Tests

### Prerequisites

- The plugin must be active
- Honeypot products must exist (`wp ctb create` if not)
- WP-CLI must be available
- The site must be accessible via HTTP (the tests make requests to the frontend and REST API)

### Basic usage

```bash
wp ctb test
```

### Test against a specific URL

By default, tests run against `home_url()`. To test against a different URL (e.g., a staging domain):

```bash
wp ctb test --url=https://staging.example.com
```

---

## What the Tests Check

The suite runs five tests, each making one or more HTTP GET requests:

### 1. Empty search contains honeypots

**What:** Requests `/?s=&post_type=product` (the bot discovery pattern).

**Expects:** At least one honeypot product ID appears in the HTML response.

**Why:** This is how bots find cheap products. Our honeypot products must be visible here to act as bait.

**Detection method:** Looks for `post-{id}` CSS classes in the response HTML, which WooCommerce adds to each product in listing templates.

---

### 2. Non-empty search excludes honeypots

**What:** Requests `/?s=test&post_type=product`.

**Expects:** No honeypot product IDs in the HTML response.

**Why:** Real customers performing searches should never see honeypot products.

---

### 3. Shop page excludes honeypots

**What:** Requests the WooCommerce shop page URL.

**Expects:** No honeypot product IDs in the HTML response.

**Why:** Honeypot products should be invisible on the main shop page and category archives.

---

### 4. REST API contains honeypots

**What:** Requests `/wp-json/wc/store/v1/products?per_page=100&orderby=price&order=asc` (WooCommerce Store API).

**Expects:** At least one honeypot product ID in the JSON response.

**Why:** Some bot variants use the REST API instead of the frontend for product discovery. The Store API is public and unauthenticated, making it the most likely target.

---

### 5. Direct honeypot URLs return 404

**What:** Requests `/?p={id}` for each honeypot product.

**Expects:** HTTP 404 response for every honeypot product.

**Why:** If a customer somehow obtains a honeypot product URL (e.g., from a cached page or search engine), they should see a 404 — not a product page they can add to cart.

---

## Interpreting Results

### All tests pass

```
  ✓  empty_search_contains_honeypots — Found 20 honeypot product(s) in empty search results.
  ✓  nonempty_search_excludes_honeypots — No honeypot products found in non-empty search results. Good.
  ✓  shop_page_excludes_honeypots — No honeypot products found on shop page. Good.
  ✓  rest_api_contains_honeypots — Found 20 honeypot product(s) in Store API results.
  ✓  direct_honeypot_url_returns_404 — All 20 honeypot product URLs returned 404.

Success: All 5 tests passed.
```

The plugin is working correctly.

### Test failures

**`empty_search_contains_honeypots` fails:**
- Honeypot products may not have `publish` status — check with `wp ctb list`
- The theme may not output standard `post-{id}` CSS classes — inspect the page source manually
- A caching plugin may be serving a stale page — clear the cache and retest

**`nonempty_search_excludes_honeypots` fails:**
- The query filter may not be running — check that the plugin is active
- Another plugin may be overriding `pre_get_posts` — check for conflicts

**`shop_page_excludes_honeypots` fails:**
- The WooCommerce shop page may not be set — check WooCommerce > Settings > Products > Shop page
- A theme or plugin may be overriding the product query

**`rest_api_contains_honeypots` fails:**
- The Store API may not be available — check that WooCommerce is active
- A security plugin may be blocking the Store API endpoint
- The response may be paginated — the test requests `per_page=100`

**`direct_honeypot_url_returns_404` fails:**
- The `template_redirect` hook may not be firing — check for conflicts
- A caching plugin may be serving the product page from cache — clear the cache
- The response reports the failing IDs and their actual HTTP status codes

---

## Troubleshooting

### HTTP request failures

If tests report "HTTP request failed", the site may not be accessible from the server itself (loopback request issue). Common causes:

- **SSL certificate issues:** The tests disable SSL verification, but some server configurations may still block self-referencing requests
- **Firewall rules:** The server may block requests from its own IP
- **DNS resolution:** The domain may not resolve from the server — try `wp ctb test --url=http://localhost`

### Cached responses

Page caching can cause tests to pass or fail incorrectly because the cached page was generated before/after the plugin was active. Always clear caches before running tests:

```bash
wp cache flush
wp ctb test
```

### Custom themes

The HTML-based tests rely on WooCommerce's standard `post-{id}` CSS classes in product listings. If your theme uses a completely custom product loop that omits these classes, the empty search and shop page tests may give false results. In this case, inspect the page source manually to verify.
