---
title: AI Layer
---

# AI Layer

AI features are optional, logged, and replaceable.

| Feature | Default | Contract |
|---|---|---|
| Embeddings | Fake deterministic provider | `EmbeddingProviderInterface` |
| Forecast | Statistical PHP driver | `ForecastProviderInterface` |
| Anomaly | Statistical detector | `AnomalyDetectorInterface` |
| LLM | Fake deterministic provider | `LlmProviderInterface` |
| Visual match | Null or configured provider | `VisualMatcherInterface` |
| Review sentiment | Off | `ReviewSentimentInterface` |

::: callout info "EU AI Act"
AI outputs are flagged with `is_ai_generated` where relevant and recorded in the AI decision log.
:::
