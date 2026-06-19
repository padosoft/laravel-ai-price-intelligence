---
title: Overview
---

# laravel-ai-price-intelligence

`laravel-ai-price-intelligence` is an Apache-2.0 Laravel package by Padosoft for enterprise product and price intelligence, competitor monitoring, AI-assisted matching, alerts, and advisory repricing signals.

::: callout info "Official docs"
Production documentation is published at `https://doc.laravel-ai-price-intelligence.padosoft.com`.
:::

::: grids
::: grid
::: card "Install"
Start with Composer, publish optional configuration, and run migrations.
:::
::: card "Monitor"
Sync catalog SKUs, create country-specific targets, discover competitor URLs, and schedule scraping.
:::
::: card "Act"
Consume signed webhooks, events, forecasts, anomalies, and optional repricing suggestions in your ecommerce.
:::
:::
:::

```mermaid
flowchart LR
  A[Ecommerce catalog] --> B[Targets]
  B --> C[Discovery]
  C --> D[Matching]
  D --> E[Scraping]
  E --> F[Normalized observations]
  F --> G[AI signals]
  G --> H[Alerts and webhooks]
  H --> I[Host pricing logic]
```

The package is boundary-respecting: it produces intelligence, while the host application keeps final pricing, margin, order, checkout, and inventory decisions.

## Core Capabilities

- Catalog onboarding through API, CSV, webhook sync, and console import.
- Geo-aware competitor discovery through `padosoft/laravel-ai-search-providers`.
- Product matching cascade with GTIN, MPN, normalized names, embeddings, optional vision, and optional LLM judge.
- Marketplace adapters for Amazon, eBay, Google Shopping, Farfetch, Idealo, Trovaprezzi, and generic pages.
- Time-series storage for price, stock, promo, and content observations.
- AI features for forecasts, anomalies, narratives, promos, content gaps, visual matching, and review sentiment.
- HMAC-signed webhooks and Laravel events for downstream ecommerce workflows.

## Metadata

| Field | Value |
|---|---|
| Package | `padosoft/laravel-ai-price-intelligence` |
| Author | Lorenzo Padovani / Padosoft |
| License | Apache-2.0 |
| PHP | 8.3+ |
| Laravel | 11, 12, 13 |
