# Administrator Guide

## Overview

Card Testing Blocker protects your WooCommerce store from card-testing botnet attacks. Once activated, the plugin works automatically with sensible defaults. This guide covers configuration options for fine-tuning.

---

## Getting Started

1. **Activate the plugin** — Honeypot products are created automatically on activation
2. **Navigate to WooCommerce > Card Testing Blocker** to review settings
3. **No further action required** — the plugin is active and protecting your store

---

## How It Works

### Honeypot Products

The plugin creates a set of decoy products priced below your cheapest real product. These products:

- Are **invisible** to your real customers (hidden from shop pages, search, categories, widgets)
- **Appear** when bots perform their characteristic product-discovery queries
- Act as a **trap** — any order containing a honeypot product is blocked

### Threat Scoring

Each checkout attempt is evaluated against multiple signals. Each signal contributes a score, and if the total exceeds the configured threshold, the checkout is blocked.

| Signal | Default Score | What It Detects |
|--------|--------------|-----------------|
| Honeypot product in cart | 100 | Bot added a decoy product |
| Empty search pattern | 30 | IP recently queried `?s=&post_type=product` |
| HTTP/1.1 protocol | 10 | Request uses outdated protocol |

Default threshold: **50**

---

## Settings

### General

| Setting | Default | Description |
|---------|---------|-------------|
| Enable plugin | On | Master switch for all protection |
| Threat threshold | 50 | Score at which checkout is blocked |

### Honeypot Products

| Setting | Default | Description |
|---------|---------|-------------|
| Product count | 20 | Number of honeypot products to create |
| Regenerate products | — | Button to recalculate prices and recreate products |

Honeypot product prices are automatically calculated based on your cheapest real product. If you add a new product that is cheaper than your current cheapest, click **Regenerate products** to update honeypot pricing.

### Signal Weights

Each signal can be individually enabled/disabled and its score adjusted:

| Setting | Default | Description |
|---------|---------|-------------|
| Honeypot signal | Enabled, score 100 | Score when honeypot product is in cart |
| Empty search signal | Enabled, score 30 | Score when IP performed empty product search |
| Empty search TTL | 1 hour | How long to remember an IP that performed an empty search |
| Protocol signal | Enabled, score 10 | Score when HTTP/1.1 is used on HTTP/2 site |

### HTTP Protocol Detection

For the protocol signal to work, your web server needs to forward the client's HTTP version to PHP. This typically requires a small configuration change:

**nginx** — Add to your server block:

```nginx
proxy_set_header X-CTB-Http-Version $server_protocol;
```

**Apache** — Add to your virtualhost or `.htaccess`:

```apache
SetEnvIf SERVER_PROTOCOL "HTTP/1.1" CTB_HTTP_VERSION=HTTP/1.1
SetEnvIf SERVER_PROTOCOL "HTTP/2.0" CTB_HTTP_VERSION=HTTP/2.0
RequestHeader set X-CTB-Http-Version "%{CTB_HTTP_VERSION}e"
```

If this header is not available, the protocol signal is skipped (score 0) rather than producing false positives.

---

## Blocked Attempts Log

The settings page includes a log of recent blocked checkout attempts, showing:

- Date and time
- IP address
- User agent
- Threat score breakdown (which signals triggered and their scores)

Use this log to verify the plugin is working and to identify attack patterns.

---

## Troubleshooting

### Honeypot products appearing in my shop

This should not happen. If it does, check that another plugin or theme is not overriding WooCommerce product visibility. Navigate to the settings page and click **Regenerate products** to recreate them with correct visibility.

### Legitimate orders being blocked

If the threat threshold is too low and you have signals other than the honeypot contributing scores, a legitimate customer could theoretically be blocked. Increase the threshold or disable weak signals. The honeypot signal alone (score 100) is sufficient to catch most bots.

### No blocked attempts showing in the log

The plugin only logs blocks, not all checkout attempts. If you're under active attack and see no blocks, verify the plugin is enabled and check that honeypot products exist (shown on the settings page).

---

## Deactivation

When deactivated, the plugin:

- Removes all honeypot products
- Clears all transients
- Removes scheduled events

Plugin settings are preserved in case you reactivate later. To remove all data, uninstall (delete) the plugin.
