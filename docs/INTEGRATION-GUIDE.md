# Integration Guide

How an ecommerce host integrates `laravel-ai-price-intelligence`.

## Authentication

- **API key (machine-to-machine)**: send `X-Api-Key: <plaintext>`. Issue with
  `ApiKey::issue($tenantId, $name, $scopes)` (the plaintext is shown once).
- **Sanctum (UI)**: bearer token; the tenant is resolved from the authenticated user via
  `config('price-intelligence.api.tenant_resolver')` (defaults to `$user->tenant_id`).

All endpoints are under `config('price-intelligence.api.prefix')` (default `api/v1`).

## Catalog

| Method | Endpoint | Notes |
|---|---|---|
| POST | `/catalog/products:bulk` | upsert ≤5000 products, idempotent on `external_id` |
| POST | `/catalog/products:csv` | multipart CSV upload |
| GET | `/catalog/products` | filter by `brand`, `gtin`; cursor-paginated |
| GET | `/catalog/products/{id}` | |
| DELETE | `/catalog/products/{id}` | 204 |

## Targets

| Method | Endpoint | Notes |
|---|---|---|
| POST | `/targets` | `{product_external_id|product_id, country, locale?, frequency?, given_urls?, given_domains?}` |
| GET | `/targets` | filter by `status` |
| PATCH | `/targets/{id}` | `status` (active/paused/stopped), `frequency`, `priority` |
| POST | `/targets/{id}/discover:now` | queue an immediate discovery |

`given_urls` skips AI discovery and monitors those URLs directly (auto-confirmed).

## Matches

| Method | Endpoint | Notes |
|---|---|---|
| GET | `/matches?status=pending` | review queue (60–85% confidence) |
| POST | `/matches/{id}/approve` | promote to a confirmed competitor product |
| POST | `/matches/{id}/reject` | 204 |
| POST | `/competitor-products` | manually attach a URL to a target |

## Observations & analytics

- `GET /observations/prices?target_id=&from=&to=&aggregate=daily`
- `GET /forecasts?target_id=&horizon=14`
- `GET /anomalies?since=24h`
- `GET /alerts?unacknowledged=1` · `POST /alerts/{id}/ack`

## Webhooks (outbound, HMAC-signed)

Subscribe via `POST /webhook-subscriptions` (`{url, events[], secret}`). Each delivery carries
`X-PI-Signature: sha256=<hmac>`; verify with `WebhookSigner::verify($body, $secret, $signature)`.

Events: `price.changed`, `price.dropped`, `price.raised`, `undercut.detected`, `stock.out`,
`stock.back_in`, `buybox.lost`, `buybox.won`, `map.violated`, `competitor.new_found`,
`competitor.url_dead`, `match.suggested`, `match.confirmed`, `match.rejected`, `anomaly.detected`,
`promo.started`, `promo.ended`, `repricing.suggested`, `narrative.generated`, `digest.daily`.

Payload: `{ id, event, tenant_id, occurred_at, data, is_ai_generated }`.

## Eloquent events (in-process)

If you run the host in the same app, you can also listen to `Padosoft\PriceIntelligence\Events\*`
(e.g. `RepricingSuggested`) instead of webhooks.

## Scheduling

Run `php artisan piprice:run-due` every minute (the package registers this automatically when the
scheduler runs) and a Horizon worker for the `pi-*` queues. Prune audit logs with
`php artisan piprice:audit:prune`.

## Responsibilities

You are responsible for respecting target sites' Terms of Service. The package defaults to honoring
robots.txt and polite rate-limiting; opt-outs are explicit and audit-logged.
