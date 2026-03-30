# Honeypot Strategy & Bot Attack Analysis

**Created:** 30 March 2026
**Status:** Planning

---

## Observed Bot Attack Pattern

Source: Access log analysis from 30 March 2026 (IP: 180.190.199.79)

### Attack Sequence (per attempt)

1. `GET /?s=&post_type=product` — Empty search, returns all products
2. `POST /?add-to-cart={product_id}` — Adds cheapest product to cart (or GET variant)
3. `GET /checkout/` — Loads checkout page
4. `POST /?wc-ajax=ppc-data-client-id` — PayPal Commerce init
5. `POST /?wc-ajax=update_order_review` — Updates order review (x2)
6. `POST /?wc-ajax=ppc-create-order` — Creates PayPal order
7. `POST /?wc-ajax=ppc-approve-order` — Approves PayPal order
8. `POST /?wc-ajax=checkout` — Submits checkout

### Bot Characteristics

- **Product discovery:** Empty search (`?s=&post_type=product`), NOT the REST API
- **User agents:** Rotated per attempt, all ancient/fake:
  - `Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.2; Trident/4.0)`
  - `Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/531.2 (KHTML, like Gecko) Chrome/35.0.827.0 Safari/531.2`
  - `Mozilla/5.0 (X11; Linux x86_64; rv:1.9.5.20) Gecko/3215-03-04 13:22:41 Firefox/13.0`
  - `Mozilla/5.0 (compatible; MSIE 7.0; Windows NT 5.01; Trident/4.1)`
- **Protocol:** All requests use HTTP/1.1 (server configured for HTTP/2.0)
- **Timing:** ~13-15 minutes between attempts
- **Payment gateway:** PayPal Commerce Platform (PPC)
- **Product selection:** Varies between attempts (IDs 239302, 231731) — not always the cheapest, but always cheap
- **Referrer:** First attempt had no referrer on checkout; subsequent attempts set referrer to the search page

### Key Insight: Not Always the Cheapest

The bot picked product 239302 on the first attempt and 231731 on subsequent attempts. This suggests the bot may randomly select from the cheapest N products, not strictly the single cheapest. This is why we need **multiple** honeypot products filling the entire first page of results, not just one.

---

## Honeypot Strategy

### Product Creation

- Create enough honeypot products to fill an entire page of search results (default: 20, configurable)
- Price range: from a base price (e.g., 1.00) up to half the cheapest legitimate product
- Realistic product names to avoid detection (e.g., "Digital Gift Card", "Shipping Protection", "Sample Pack", etc.)
- Simple products (no variations needed)
- Catalog visibility set to `hidden` (removes from shop pages)
- Marked with `_ctb_honeypot` = `1` post meta for identification

### Frontend Visibility Rules

| Context | Honeypots Visible? | Method |
|---|---|---|
| Shop / archive pages | No | `hidden` catalog visibility |
| Category pages | No | `hidden` catalog visibility |
| Site search (non-empty `?s=`) | No | `pre_get_posts` filter |
| Empty search (`?s=&post_type=product`) | **Yes** | `pre_get_posts` — override visibility for this pattern |
| Product widgets | No | `hidden` catalog visibility |
| Related products | No | `hidden` catalog visibility |
| Direct URL access | No | 404 or redirect |
| WooCommerce REST API | **Yes** | Allow through for REST API attack vector coverage |
| WooCommerce Store API | **Yes** | Allow through for Store API attack vector coverage |

### Threat Scoring

| Signal | Score | Notes |
|---|---|---|
| Honeypot product in cart | +100 | Instant block — no legitimate customer can add these |
| IP performed empty search | +30 | Tracked via per-IP transient, 1-hour TTL |
| HTTP/1.1 on HTTP/2 site | +10 | Weak signal, needs server config for detection |
| **Threshold** | **50** | Configurable; above this score = blocked |

Honeypot alone = blocked.
Empty search + HTTP/1.1 = 40 (not blocked alone — safe for edge cases).
Any two signals + one more future signal = blocked.

---

## Open Questions

1. **Honeypot product images:** Should we generate/assign placeholder images? Absence of images could be a bot detection tell, but bots likely don't check images.
2. **Price recalculation frequency:** When a store admin changes product prices, we need to recalculate honeypot prices. Hook into `woocommerce_update_product`? Or just recalculate on a schedule?
3. **Multisite support:** Needed? Defer to later if not.
4. **Logging storage:** Use custom DB table or just `error_log()`? Custom table allows admin UI but adds complexity.
5. **nginx config for HTTP version:** Document required nginx config change, or detect automatically?
