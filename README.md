# Card Testing Blocker

![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue?logo=wordpress)
![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-purple?logo=woocommerce)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php)
![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)
![Version](https://img.shields.io/badge/Version-0.1.0-orange)

A WooCommerce plugin that detects and blocks card-testing botnet attacks using honeypot products and intelligent threat scoring.

---

## The Problem

Card-testing bots abuse WooCommerce stores to validate stolen credit card numbers. They search for the cheapest product, add it to cart, and attempt checkout with hundreds of stolen cards. This results in:

- Fraudulent orders and chargebacks
- Payment gateway fees for failed transactions
- Account holds from payment processors
- Increased server load from bot traffic

## How It Works

Card Testing Blocker uses a **honeypot + threat scoring** approach:

1. **Honeypot Products** — The plugin creates decoy products priced below your cheapest real product. These products are invisible to legitimate customers but appear when bots search for cheap products to test cards against.

2. **Threat Scoring** — Each checkout attempt is evaluated against multiple signals. Scores are summed and compared against a configurable threshold:

   | Signal | Score | Description |
   |--------|-------|-------------|
   | Honeypot product in cart | +100 | The customer added a decoy product |
   | Empty search pattern | +30 | The IP recently performed an empty product search |
   | HTTP/1.1 protocol | +10 | Request uses HTTP/1.1 on an HTTP/2 site |

3. **Blocking** — When a checkout attempt exceeds the threat threshold, the order is blocked before any payment is processed.

## Requirements

- WordPress 6.4 or later
- WooCommerce 8.0 or later
- PHP 8.0 or later

## Installation

1. Download the latest release
2. Upload to `/wp-content/plugins/card-testing-blocker/`
3. Activate via the WordPress Plugins screen
4. Navigate to **WooCommerce > Card Testing Blocker** to configure

## Configuration

See the [Administrator Guide](docs/admin-guide.md) for full configuration instructions.

## For Developers

- [Hooks & Filters Reference](docs/hooks-reference.md) — Extend the scoring system with custom signals
- [Architecture Overview](docs/architecture.md) — How the plugin is structured

## License

This plugin is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
