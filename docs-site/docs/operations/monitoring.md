---
title: Monitoring
---

# Monitoring

Monitor queues, fetch failures, webhook failures, match proposal backlog, and observation freshness.

Suggested alerts:

- Discovery jobs failing repeatedly.
- Scrape latency or error rate above normal.
- Webhook retry exhaustion.
- No fresh observations for active high-priority targets.
- Review queue growing faster than reviewers can clear it.

::: callout info "Audit trail"
`pi_fetch_logs` and AI decision logs are operational data, not just compliance data.
:::
