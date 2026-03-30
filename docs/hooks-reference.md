# Hooks & Filters Reference

This document lists all hooks and filters provided by Card Testing Blocker for developers who want to extend the plugin's functionality.

---

## Filters

### `ctb_threat_signals`

Register additional threat signal classes.

**Parameters:**
- `$signals` _(array)_ — Array of signal class instances

**Return:** _(array)_ Modified array of signal instances

**Example:**

```php
add_filter( 'ctb_threat_signals', 'my_custom_signals' );

function my_custom_signals( array $signals ): array {
    $signals[] = new My_Custom_Signal();
    return $signals;
}
```

Each signal class must implement two public methods:

```php
class My_Custom_Signal {
    /**
     * Calculate the threat score for this signal.
     *
     * @param array $context {
     *     Request context.
     *
     *     @type array  $cart_items      WooCommerce cart items.
     *     @type string $ip_address      Client IP address.
     *     @type string $user_agent      Client user agent string.
     *     @type string $server_protocol HTTP protocol version.
     * }
     * @return int Score (0 if not triggered, positive integer if triggered).
     */
    public function get_score( array $context ): int {
        // Your detection logic here.
        return 0;
    }

    /**
     * Get the human-readable name of this signal.
     *
     * @return string Signal name for logging.
     */
    public function get_name(): string {
        return 'My Custom Signal';
    }
}
```

---

### `ctb_threat_threshold`

Modify the threat score threshold dynamically.

**Parameters:**
- `$threshold` _(int)_ — Current threshold value
- `$context` _(array)_ — Request context (same as signal context)

**Return:** _(int)_ Modified threshold

**Example:**

```php
add_filter( 'ctb_threat_threshold', 'lower_threshold_for_known_ranges', 10, 2 );

function lower_threshold_for_known_ranges( int $threshold, array $context ): int {
    // Lower threshold for a known-bad IP range.
    if ( str_starts_with( $context['ip_address'], '180.190.' ) ) {
        return 25;
    }
    return $threshold;
}
```

---

### `ctb_honeypot_product_names`

Customise the names used for honeypot products.

**Parameters:**
- `$names` _(array)_ — Array of product name strings

**Return:** _(array)_ Modified array of product names

**Example:**

```php
add_filter( 'ctb_honeypot_product_names', 'my_custom_honeypot_names' );

function my_custom_honeypot_names( array $names ): array {
    return [
        'E-Gift Voucher',
        'Sample Pack',
        'Shipping Insurance',
        // ...
    ];
}
```

---

### `ctb_honeypot_price_range`

Modify the price range for honeypot products.

**Parameters:**
- `$range` _(array)_ — `[ 'min' => float, 'max' => float ]`
- `$cheapest_legitimate_price` _(float)_ — Price of the cheapest real product

**Return:** _(array)_ Modified price range

---

### `ctb_order_context`

Modify the context array before scoring.

**Parameters:**
- `$context` _(array)_ — Request context array

**Return:** _(array)_ Modified context

**Example:**

```php
add_filter( 'ctb_order_context', 'add_geoip_to_context' );

function add_geoip_to_context( array $context ): array {
    // Add GeoIP data if available.
    $context['country'] = function_exists( 'geoip_country_code_by_name' )
        ? geoip_country_code_by_name( $context['ip_address'] )
        : '';
    return $context;
}
```

---

## Actions

### `ctb_order_blocked`

Fires when an order is blocked due to exceeding the threat threshold.

**Parameters:**
- `$total_score` _(int)_ — Total threat score
- `$signals_triggered` _(array)_ — Array of `[ 'name' => string, 'score' => int ]`
- `$context` _(array)_ — Request context

**Example:**

```php
add_action( 'ctb_order_blocked', 'notify_admin_of_block', 10, 3 );

function notify_admin_of_block( int $total_score, array $signals, array $context ): void {
    error_log( sprintf(
        'CTB blocked order from %s (score: %d)',
        $context['ip_address'],
        $total_score
    ) );
}
```

---

### `ctb_order_score_calculated`

Fires after every score calculation, regardless of whether the order was blocked.

**Parameters:**
- `$total_score` _(int)_ — Total threat score
- `$signals_triggered` _(array)_ — Array of `[ 'name' => string, 'score' => int ]`
- `$context` _(array)_ — Request context
- `$blocked` _(bool)_ — Whether the order was blocked

**Example:**

```php
add_action( 'ctb_order_score_calculated', 'log_all_scores', 10, 4 );

function log_all_scores( int $score, array $signals, array $context, bool $blocked ): void {
    if ( $score > 0 ) {
        // Log non-zero scores to a custom table or external service.
    }
}
```

---

### `ctb_honeypot_products_created`

Fires after honeypot products are created or regenerated.

**Parameters:**
- `$product_ids` _(array)_ — Array of created product IDs
- `$price_range` _(array)_ — `[ 'min' => float, 'max' => float ]`

---

### `ctb_empty_search_detected`

Fires when an empty product search is detected.

**Parameters:**
- `$ip_address` _(string)_ — IP that performed the empty search
