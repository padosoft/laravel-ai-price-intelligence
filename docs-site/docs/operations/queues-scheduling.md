---
title: Queues and Scheduling
---

# Queues and Scheduling

The service provider schedules `piprice:run-due` every minute. A host only needs to run Laravel's scheduler and workers.

| Lane | Purpose |
|---|---|
| `pi-discovery` | Search and candidate URL discovery. |
| `pi-scrape` | Fetching, extracting, and normalizing observations. |
| `pi-ai` | Forecasting, anomaly detection, narrative, and optional LLM work. |
| `pi-notifications` | Alerts and webhook delivery. |

Adaptive backoff stretches quiet targets and accelerates when recent observations change materially.
