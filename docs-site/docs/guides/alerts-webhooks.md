---
title: Alerts and Webhooks
---

# Alerts and Webhooks

Alerts are persisted, then delivered through configured channels and outbound webhooks.

```mermaid
sequenceDiagram
  participant Scrape
  participant Eval as PriceChangeEvaluator
  participant Alerts
  participant Webhooks
  participant Host
  Scrape->>Eval: latest observation
  Eval->>Alerts: price.changed or undercut.detected
  Alerts->>Webhooks: enqueue delivery
  Webhooks->>Host: signed JSON payload
```

When a webhook subscription has a secret, deliveries include `X-PI-Signature: sha256=<hmac>`.

::: callout warning "Unsigned deliveries"
Unsigned webhooks are possible only when no secret is configured. Use a secret in production.
:::
