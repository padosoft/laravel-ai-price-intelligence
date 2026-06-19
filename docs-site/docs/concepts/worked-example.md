---
title: Worked Example
---

# Worked Example

Acme sells `SKU-123`, a phone priced at EUR 199.00. The host syncs the product and creates an Italian daily target.

::: steps
=== Step 1: Catalog
`external_id=SKU-123`, GTIN, brand, model, categories, and current price are stored.

=== Step 2: Discovery
Geo-aware search queries find Amazon, eBay, Trovaprezzi, and a generic competitor URL.

=== Step 3: Matching
GTIN confirms one result at 100. A normalized-name candidate scores 73 and enters review.

=== Step 4: Scraping
The confirmed URL produces price, stock, seller, shipping, JSON-LD, OpenGraph, and fetch audit data.

=== Step 5: Signals
A competitor price drop to EUR 189.90 emits `price.dropped` and `undercut.detected`.

=== Step 6: Host action
The host receives the signed webhook and asks its pricing engine to reevaluate margin policy.
:::

::: callout warning "Limit"
The package cannot know the host's real acquisition cost, stock strategy, or MAP obligations unless the host supplies that policy to downstream pricing logic.
:::
