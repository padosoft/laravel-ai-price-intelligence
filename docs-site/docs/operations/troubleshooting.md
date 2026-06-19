---
title: Troubleshooting
---

# Troubleshooting

::: collapsible "No competitor URLs are discovered"
Check provider credentials, country and locale inputs, discovery cache, and monthly budget guard settings.
:::

::: collapsible "Matches are stuck in review"
Review confidence thresholds and candidate evidence. A high review backlog usually means weak catalog identifiers or too-broad discovery queries.
:::

::: collapsible "Webhooks fail verification"
Verify that the host uses the raw request body, the same secret, and the `X-PI-Signature` header value.
:::

::: collapsible "Scraping returns empty prices"
Inspect JSON-LD, OpenGraph, raw price text, marketplace adapter selection, and whether the page requires JavaScript rendering.
:::
