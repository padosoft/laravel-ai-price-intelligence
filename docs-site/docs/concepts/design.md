---
title: Design
---

# Design

Il design separa input, acquisizione, normalizzazione, AI e delivery.

```mermaid
flowchart TB
  subgraph Host
    A[Catalog API]
    B[Webhook receiver]
  end
  subgraph Package
    C[Tenant context]
    D[Discovery]
    E[Matching pipeline]
    F[Scraping adapters]
    G[Normalizer]
    H[AI services]
    I[Alerts]
    J[Webhook dispatcher]
  end
  A --> C --> D --> E --> F --> G --> H --> I --> J --> B
```

::: grids
::: grid
::: card "Interface first"
Drivers can be swapped by binding contracts.
:::
::: card "Queue first"
Discovery, scraping, AI, and notifications are idempotent jobs.
:::
::: card "Audit first"
Fetch logs, AI decision logs, and signed webhook attempts leave evidence.
:::
:::
:::
