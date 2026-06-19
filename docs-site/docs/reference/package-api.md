---
title: Package API
---

# Package API

Public extension points are contracts under `Padosoft\PriceIntelligence\Contracts`.

| Contract | Use |
|---|---|
| `ProductScraperInterface` | Fetch and extract a product page. |
| `MarketplaceAdapterInterface` | Fetch marketplace-specific product snapshots. |
| `MatchStepInterface` | Add a matching step to the cascade. |
| `EmbeddingProviderInterface` | Provide vectors for semantic matching. |
| `FxProviderInterface` | Normalize currencies. |
| `ForecastProviderInterface` | Forecast future prices. |
| `AnomalyDetectorInterface` | Detect unusual price behavior. |
| `ReviewSentimentInterface` | Produce GDPR-safe sentiment aggregates. |
| `RepricerEngineInterface` | Produce advisory repricing suggestions. |
| `AiActBridgeInterface` | Connect to AI governance tooling. |

Use Laravel container bindings to replace defaults.
