# WP-CLI Reference

Card Testing Blocker provides a set of WP-CLI commands under the `wp ctb` namespace for managing honeypot products and verifying plugin behaviour.

---

## Commands

### `wp ctb status`

Show the current honeypot product status including count, price range, and the cheapest legitimate product price on the site.

```bash
wp ctb status
```

**Example output:**

```
  Honeypot products:          20
  Actual price range:         £1.00 – £2.50
  Configured price range:     £1.00 – £2.50
  Cheapest legitimate product: £5.00

Success: 20 honeypot products active.
```

---

### `wp ctb create`

Create honeypot products. The number of products created is controlled by the plugin settings (default: 20).

```bash
wp ctb create
```

If honeypot products already exist, the command warns and exits. Use `wp ctb regenerate` to delete and recreate.

---

### `wp ctb list`

List all honeypot products with their ID, name, price, catalog visibility, status, and slug.

```bash
wp ctb list
```

**Supported formats:**

```bash
wp ctb list --format=table   # Default
wp ctb list --format=json    # JSON output for scripting
wp ctb list --format=csv     # CSV output
wp ctb list --format=yaml    # YAML output
```

**Example output:**

```
+--------+---------------------+-------+------------+---------+---------------------+
| ID     | Name                | Price | Visibility | Status  | Slug                |
+--------+---------------------+-------+------------+---------+---------------------+
| 100001 | Digital Gift Card   | 1.00  | hidden     | publish | digital-gift-card   |
| 100002 | E-Gift Voucher      | 1.08  | hidden     | publish | e-gift-voucher      |
| 100003 | Shipping Protection | 1.16  | hidden     | publish | shipping-protection |
| ...    | ...                 | ...   | ...        | ...     | ...                 |
+--------+---------------------+-------+------------+---------+---------------------+
```

---

### `wp ctb delete`

Delete all honeypot products. Prompts for confirmation unless `--yes` is passed.

```bash
wp ctb delete
wp ctb delete --yes   # Skip confirmation
```

---

### `wp ctb regenerate`

Delete all existing honeypot products and create new ones with recalculated pricing. Useful after adding new products to the store that are cheaper than previous stock.

```bash
wp ctb regenerate
wp ctb regenerate --yes   # Skip confirmation
```

---

### `wp ctb test`

Run the visibility test suite. Makes real HTTP requests to verify honeypot products appear where they should and are hidden where they shouldn't be.

```bash
wp ctb test
wp ctb test --url=https://example.com   # Test against a specific URL
```

See [Testing Guide](testing-guide.md) for details on what the tests check and how to interpret results.

---

## Common Workflows

### Initial setup

```bash
wp ctb create     # Create honeypot products
wp ctb list       # Verify they were created
wp ctb status     # Check pricing looks correct
wp ctb test       # Run visibility tests
```

### After changing product prices

```bash
wp ctb regenerate --yes   # Recalculate honeypot pricing
wp ctb status             # Verify new price range
wp ctb test               # Confirm visibility still correct
```

### Troubleshooting

```bash
wp ctb list --format=json   # Inspect full product details
wp ctb delete --yes         # Clean slate
wp ctb create               # Recreate from scratch
wp ctb test                 # Verify
```
