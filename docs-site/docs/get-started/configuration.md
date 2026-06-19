---
title: Configuration
---

# Configuration

Publish `config/price-intelligence.php` when the host needs to override defaults.

```bash
php artisan vendor:publish --tag=price-intelligence-config
```

## Important Flags

| Area | Key | Default |
|---|---|---|
| Tenancy | `tenancy.mode` | `single` |
| Matching | `matching.confidence_band` | `[60, 85]` |
| Scraping | `scraping.default_driver` | `auto` |
| AI LLM | `llm.driver` | `fake` |
| Repricer | `repricer.enabled` | `false` |
| Review sentiment | `review_insight.enabled` | `false` |
| Robots | `compliance.robots.default` | `respect` |

::: callout tip "Config-cache safe"
Use class strings or container bindings for custom callbacks. Avoid closures in published config when the host runs `php artisan config:cache`.
:::
