# Integration Guide

How an ecommerce host integrates `laravel-ai-price-intelligence`.

## Authentication

- **API key (machine-to-machine)**: send `X-Api-Key: <plaintext>`. Issue with
  `ApiKey::issue($tenantId, $name, $scopes)` (the plaintext is shown once).
- **Sanctum (UI)**: bearer token; the tenant is resolved from the authenticated user via
  `config('price-intelligence.api.tenant_resolver')` (defaults to `$user->tenant_id`; set it to an
  invokable **class-string** rather than a closure so `php artisan config:cache` keeps working). Sanctum is
  not enabled by default — the package's `api.middleware` is just `['api']`; add Sanctum (and any
  auth middleware) in your host app if you want session/bearer auth alongside the API-key path.

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
| GET | `/matches?status=pending` | review queue (60–84% confidence; ≥85 auto-confirms) |
| POST | `/matches/{id}/approve` | promote to a confirmed competitor product |
| POST | `/matches/{id}/reject` | 204 |
| POST | `/competitor-products` | manually attach a URL to a target |

## Alerts

- `GET /alerts?unacknowledged=1&type=` — cursor-paginated
- `POST /alerts/{id}/ack`

> **Analytics read endpoints** (`/observations/prices`, `/forecasts`, `/anomalies`, …) are on the
> roadmap. Today, forecasts/anomalies are produced and stored (`pi_forecasts`, `pi_anomalies`) and
> surfaced via webhooks/events and the admin panel; query the models directly if you need them now.

## Webhooks (outbound)

Subscribe via `POST /webhook-subscriptions` (`{url, events[], secret}`). **When a `secret` is set**
(recommended), each delivery carries `X-PI-Signature: sha256=<hmac>`; verify with
`WebhookSigner::verify($body, $secret, $signature)`. Without a secret the payload is delivered
unsigned, so always configure one in production.

Events: `price.changed`, `price.dropped`, `price.raised`, `undercut.detected`, `stock.out`,
`stock.back_in`, `buybox.lost`, `buybox.won`, `map.violated`, `competitor.new_found`,
`competitor.url_dead`, `match.suggested`, `match.confirmed`, `match.rejected`, `anomaly.detected`,
`promo.started`, `promo.ended`, `repricing.suggested`, `narrative.generated`, `digest.daily`.

Payload: `{ id, event, tenant_id, occurred_at, data, is_ai_generated }`.

## Eloquent events (in-process)

If you run the host in the same app, you can also listen to `Padosoft\PriceIntelligence\Events\*`
(e.g. `RepricingSuggested`) instead of webhooks.

## Scheduling

The package **already schedules** `piprice:run-due` every minute via its service provider, so you
only need a running scheduler (`php artisan schedule:work` / cron) and a queue worker for the `pi-*`
queues. You can also invoke `piprice:run-due` manually. Prune audit logs with `piprice:audit:prune`.

## Responsibilities

You are responsible for respecting target sites' Terms of Service. The package defaults to honoring
robots.txt and polite rate-limiting; opt-outs are explicit and audit-logged.
