---
title: Pipeline and Workflow
---

# Pipeline and Workflow

```mermaid
stateDiagram-v2
  [*] --> ProductSynced
  ProductSynced --> TargetCreated
  TargetCreated --> DiscoveryQueued
  DiscoveryQueued --> CandidateFound
  CandidateFound --> AutoConfirmed: score >= 85
  CandidateFound --> ReviewPending: score 60..84
  CandidateFound --> Rejected: score < 60
  AutoConfirmed --> ScrapeQueued
  ReviewPending --> AutoConfirmed: approved
  ScrapeQueued --> ObservationStored
  ObservationStored --> AlertEvaluated
  AlertEvaluated --> WebhookDelivered
```

The scheduler selects due targets, dispatches scraping jobs, and updates `next_check_at` through adaptive backoff.

::: callout tip "Idempotency"
Jobs should be safe to retry. Persisted observations, match status, and fetch logs make repeated work detectable.
:::
