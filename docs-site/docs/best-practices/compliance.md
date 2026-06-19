---
title: Compliance
---

# Compliance

Use the default robots-aware policy, explicit opt-outs, PII redaction, and AI decision logs.

::: callout warning "Review sentiment"
Review sentiment is off by default and should stay domain-opt-in. Persist only anonymized aggregates after PII redaction.
:::

Minimum production controls:

- Set a per-domain rate limit.
- Keep fetch audit logs.
- Configure webhook secrets.
- Enable PII redaction where review or content text is stored.
- Review AI outputs used in consequential workflows.
