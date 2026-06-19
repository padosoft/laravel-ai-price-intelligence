---
title: Marketplace Adapters
---

# Marketplace Adapters

Adapters normalize marketplace-specific APIs or pages into `ProductSnapshot` data.

| Marketplace | Drivers | Notes |
|---|---|---|
| Amazon | `auto`, `sp_api`, `keepa`, `scrape` | API credentials enable official paths; scraping is fallback. |
| eBay | `auto`, `api`, `scrape` | Browse API through client-credentials OAuth. |
| Google Shopping | `auto`, `serp`, `scrape` | SerpApi-compatible product lookup. |
| Farfetch | `scrape`, `retailed`, `apify` | Luxury-focused options. |
| Idealo, Trovaprezzi | `scrape` | JSON-LD and registered selectors. |

::: callout warning "Terms and robots"
You remain responsible for respecting target site terms. The package defaults to robots-aware, rate-limited access.
:::
