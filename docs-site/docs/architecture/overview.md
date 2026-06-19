---
title: Architecture Overview
---

# Architecture Overview

The package is a Laravel-native library with controllers, models, jobs, services, and contracts.

```mermaid
flowchart LR
  API[API controllers] --> Services
  Console[Console commands] --> Services
  Scheduler[Laravel scheduler] --> Jobs
  Jobs --> Services
  Services --> Models
  Services --> Contracts
  Contracts --> Drivers
```

Primary source folders:

- `src/Contracts` for extension points.
- `src/Services` for discovery, scraping, matching, pricing, AI, compliance, alerts, and webhooks.
- `src/Jobs` for queued workflows.
- `src/Http/Controllers/Api/V1` for public API endpoints.
- `database/migrations` for tenant-scoped persistence.
