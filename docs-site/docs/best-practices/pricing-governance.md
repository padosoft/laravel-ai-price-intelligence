---
title: Pricing Governance
---

# Pricing Governance

Treat package output as evidence, not as final pricing policy.

Good governance patterns:

- Keep margin floors outside the package.
- Record why a price was changed.
- Separate undercut detection from final price application.
- Use max-change-per-day limits.
- Keep human review for strategic SKUs.

::: callout info "Advisory repricer"
The optional repricer emits `repricing.suggested`; it does not write the final catalog price.
:::
