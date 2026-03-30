# Card Testing Blocker - Development Guide

**Plugin Slug:** `card-testing-blocker`
**Text Domain:** `card-testing-blocker`
**Namespace:** `Card_Testing_Blocker` (used in `includes/` classes and `constants.php`, NOT in the main plugin file)
**Function Prefix:** `ctb_`
**Global Constants Prefix:** `CARD_TESTING_BLOCKER_` (main plugin file, no namespace)
**Namespaced Constants Prefix:** `CTB_` (via `Card_Testing_Blocker\` namespace in `constants.php`)
**Options Prefix:** `ctb_`
**Transients Prefix:** `ctb_`
**Minimum PHP:** 8.0
**Minimum WordPress:** 6.4
**Requires WooCommerce:** 8.0+

---

## What This Plugin Does

Detects and blocks card-testing botnet attacks on WooCommerce stores using a **honeypot product + threat scoring** system.

**Core mechanism:**
1. Programmatically creates cheap "honeypot" products that appear in bot product-discovery queries but are hidden from legitimate frontend browsing
2. Assigns a threat score to each checkout attempt based on multiple signals (honeypot product in cart, empty search pattern, HTTP protocol version, etc.)
3. Blocks checkout when the score exceeds a configurable threshold

---

## Architecture

```
card-testing-blocker/
├── card-testing-blocker.php      # Main plugin file (bootstrap)
├── constants.php                 # All constants
├── includes/
│   ├── class-plugin.php          # Main plugin class, hook registration
│   ├── class-honeypot-products.php   # Create/manage/price honeypot products
│   ├── class-query-filter.php    # Hide honeypots from frontend, show in bot queries
│   ├── class-threat-scorer.php   # Scoring engine, registers & runs signal checks
│   ├── class-order-interceptor.php   # Hooks checkout, runs scorer, blocks
│   ├── class-settings.php        # Admin settings page
│   └── class-signals/
│       ├── class-honeypot-signal.php       # Cart contains honeypot product
│       ├── class-empty-search-signal.php   # IP performed empty search recently
│       └── class-protocol-signal.php       # HTTP/1.1 on HTTP/2 site
├── admin-templates/              # Admin page templates
├── assets/
│   ├── admin/                    # Admin CSS/JS
│   └── public/                   # Public CSS/JS (if needed)
├── docs/                         # Documentation for store admins & developers
├── dev-notes/                    # Internal development notes (excluded from dist)
├── languages/                    # Translation files
├── CHANGELOG.md
├── README.md                     # GitHub readme
└── readme.txt                    # WordPress.org readme
```

---

## Coding Standards

Follow `.github/copilot-instructions.md` for all general WordPress plugin conventions. Key points specific to this plugin:

### Prefixing

- **Constants:** `CTB_` prefix (e.g., `CTB_HONEYPOT_SCORE`)
- **Options:** `ctb_` prefix (e.g., `ctb_threat_threshold`)
- **Transients:** `ctb_` prefix (e.g., `ctb_empty_search_{ip_hash}`)
- **Hooks (custom):** `ctb_` prefix (e.g., `ctb_before_score_calculation`)
- **CSS classes:** `ctb-` prefix
- **Nonce actions:** `ctb_` prefix

### Signal Classes

Each threat signal implements a consistent interface:

```php
namespace Card_Testing_Blocker\Signals;

class Example_Signal {
    public function get_score( array $context ): int {
        // Return 0 if signal not triggered, positive int if triggered.
    }

    public function get_name(): string {
        // Human-readable signal name for logging.
    }
}
```

The `$context` array contains: `cart_items`, `ip_address`, `user_agent`, `server_protocol`, and any other relevant request data.

### Honeypot Products

- Identified by post meta `_ctb_honeypot` = `1`
- Never use `wp_delete_post()` on honeypot products outside of deactivation
- Prices are dynamically calculated relative to the cheapest legitimate product
- Product names should be realistic (not obviously fake)

---

## Key Technical Decisions

1. **Honeypot visibility:** Uses WooCommerce `catalog` visibility = `hidden` as base, then selectively re-includes in queries matching bot patterns (empty search `?s=&post_type=product`). NOT using custom post status.

2. **Scoring, not binary detection:** A threshold-based scoring system allows multiple weak signals to combine into a strong detection, and is extensible for future signals.

3. **Transients for IP tracking:** Per-IP transients (`ctb_empty_search_{ip_hash}`) with configurable TTL. Auto-expire, no cleanup needed.

4. **HTTP protocol detection:** Requires web server to forward client protocol via header (e.g., nginx `X-CTB-Http-Version`). Falls back gracefully if header not present.

5. **HPOS compatible:** All order data access uses WC_Order CRUD methods, never `get_post_meta()`.

---

## Development Commands

```bash
# Check coding standards
phpcs

# Auto-fix coding standards
phpcbf

# Check specific directory
phpcs includes/
```

---

## Testing Considerations

- Honeypot products must not appear in WooCommerce shop, category, or widget queries
- Honeypot products MUST appear in `?s=&post_type=product` queries
- Orders containing honeypot products must be blocked at checkout
- Scoring threshold must be configurable
- Deactivation must clean up all honeypot products
- Activation must create honeypot products with correct pricing
