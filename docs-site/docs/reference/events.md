---
title: Events
---

# Events Reference

Outbound webhook event names include:

- `price.changed`
- `price.dropped`
- `price.raised`
- `undercut.detected`
- `stock.out`
- `stock.back_in`
- `buybox.lost`
- `buybox.won`
- `map.violated`
- `competitor.new_found`
- `competitor.url_dead`
- `match.suggested`
- `match.confirmed`
- `match.rejected`
- `anomaly.detected`
- `promo.started`
- `promo.ended`
- `repricing.suggested`
- `narrative.generated`
- `digest.daily`

Payloads use `{ id, event, tenant_id, occurred_at, data, is_ai_generated }`.
