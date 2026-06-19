---
title: Modello dati e contratto
---

# Modello dati e contratto

Il contratto pubblico ruota intorno a prodotti, target, competitor product, osservazioni, alert e webhook.

| Entity | Contract |
|---|---|
| Product | Host SKU, keyed by `external_id` per tenant. |
| MonitoringTarget | Product and country pair with frequency and options. |
| CompetitorProduct | Confirmed or reviewed URL attached to a target. |
| PriceObservation | Captured price in source currency and normalized cents. |
| Alert | Business signal derived from observations. |
| WebhookSubscription | Delivery endpoint, event filters, optional secret. |

::: callout info "Tenant scope"
Every business table is tenant-scoped. Jobs carry `tenant_id` and restore `TenantContext` before work.
:::
