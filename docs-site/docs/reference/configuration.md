---
title: Configuration Reference
---

# Configuration Reference

Important configuration namespaces:

| Namespace | Purpose |
|---|---|
| `tenancy` | Single database or database-per-tenant mode. |
| `api` | Prefix, middleware, tenant resolver. |
| `discovery` | Search providers, geo behavior, caching, budget guard. |
| `scraping` | Default driver and rendering fallback. |
| `marketplaces` | Marketplace driver choices and credentials. |
| `matching` | Confidence band, embeddings, recompute thresholds. |
| `ai` | Feature toggles for forecasts, anomalies, narratives, visual match, promos, content gaps. |
| `review_insight` | GDPR-safe review sentiment opt-in. |
| `repricer` | Advisory repricer toggle and safety controls. |
| `webhooks` | Retry and digest settings. |
| `compliance` | Robots, audit, PII, and AI Act behavior. |

::: callout warning "Default posture"
The safest defaults are conservative: fake LLM, review sentiment off, repricer off, robots respected.
:::
