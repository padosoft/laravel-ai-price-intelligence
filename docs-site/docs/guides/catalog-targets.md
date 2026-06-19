---
title: Catalog and Targets
---

# Catalog and Targets

Products are the host catalog records. Targets define where and how to monitor them.

```json
{
  "products": [
    {
      "external_id": "SKU-123",
      "gtin": "8001234567890",
      "brand": "Acme",
      "model": "X1",
      "name": "Acme X1 64GB",
      "categories": ["Electronics", "Phones"],
      "our_price_cents": 19900,
      "currency": "EUR",
      "base_country": "IT"
    }
  ]
}
```

::: callout info "Idempotency"
Bulk catalog sync is idempotent on `external_id` per tenant, so host jobs can retry safely.
:::

Targets accept a country, locale, frequency, priority, and optional direct URLs. Direct URLs skip AI discovery and become monitored competitor products immediately.
