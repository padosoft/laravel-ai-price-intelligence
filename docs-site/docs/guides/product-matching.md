---
title: Product Matching
---

# Product Matching

Matching is a cascade. Cheap deterministic checks run first; LLM steps are reserved for borderline cases.

```mermaid
flowchart TD
  A[Candidate URL] --> B[Exact GTIN]
  B -->|miss| C[MPN plus brand]
  C -->|miss| D[Normalized name]
  D -->|borderline| E[Embedding semantic match]
  E -->|fashion or luxury| F[Visual matcher]
  F -->|60 to 84| G[LLM judge]
  G --> H{Score}
  H -->|85 plus| I[Auto confirmed]
  H -->|60 to 84| J[Human review]
  H -->|below 60| K[Rejected]
```

The acceptance bands are:

| Score | Result |
|---:|---|
| `85..100` | Confirmed |
| `60..84` | Match proposal |
| `0..59` | Rejected |

::: callout tip "Cost control"
The package caches embeddings and avoids re-running expensive AI steps unless candidate evidence changes materially.
:::
