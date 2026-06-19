---
title: ADR
---

# Architecture Decision Records

::: collapsible "ADR-001: Advisory-only repricing"
Status: accepted.

The package can calculate repricing suggestions, but never applies prices to the merchant catalog. This avoids mixing intelligence with margin policy and keeps the host application authoritative.
:::

::: collapsible "ADR-002: Fake LLM provider by default"
Status: accepted.

The default LLM provider is deterministic and offline. This keeps installation, CI, and package tests free from external calls while preserving extension points for real providers.
:::

::: collapsible "ADR-003: Human review for borderline matches"
Status: accepted.

Scores from 60 to 84 create `MatchProposal` records. False positives are more expensive than missing a candidate, so uncertain matches require review.
:::

::: collapsible "ADR-004: Robots-aware scraping"
Status: accepted.

The default robots policy is respect. Opt-out must be explicit and audit-logged because scraping policy is a compliance and reputation concern.
:::
