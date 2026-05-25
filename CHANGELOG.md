# Changelog

All notable changes to `padosoft/laravel-ai-price-intelligence` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/) and the project adheres to
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.6.0] - 2026-05-25

Admin-driven backfill (gap-backfill policy): anomaly **acknowledgement** endpoints, so the admin
Anomalies screen has no dead buttons. 243 PHPUnit green (PHP 8.3/8.4), Pint + PHPStan level 5 clean.

### Added
- `POST /anomalies/{id}/ack` — mark a single anomaly reviewed (idempotent), returning the row.
- `POST /anomalies:ack` — bulk-acknowledge by `ids[]`; only unacknowledged rows are touched, returns
  `{ acknowledged: <count> }`.
- `Anomaly::acknowledge()` model helper (mirrors `Alert::acknowledge()`).

## [1.5.0] - 2026-05-25

B-phase **B3**: remaining REST API gaps + enterprise-scale primitives. 239 PHPUnit green
(PHP 8.3/8.4), Pint + PHPStan level 5 clean.

### Added
- `GET /observations/prices` **host filter** (`?host=`), plus new **history endpoints**
  `GET /observations/stock` and `GET /observations/promos` (cursor-paginated, filter by
  competitor_product_id / host / from / to).
- `GET /ai-decisions` — paginated EU AI Act decision log (filter by feature / subject / date) for the
  admin Compliance screen.
- **Facet endpoints** computed in SQL/lazy: `GET /facets/hosts` (confirmed-competitor count per host)
  and `GET /facets/categories` (product count per category).
- **Streamed bulk CSV export**: `GET /catalog/products:export` and `GET /observations/prices:export`
  (Eloquent `cursor()` + `streamDownload` — OOM-safe for 100k+ rows). Excel is an opt-in via
  `phpoffice/phpspreadsheet`.
- **Tenant settings**: exposed in `GET /tenants/me` (`data.tenant.settings`) and writable via
  `PATCH /tenants/me/settings` (partial merge, validated).
- **Daily price aggregates**: `pi_price_daily_aggregates` table + `PriceDailyAggregate` model + the
  `piprice:aggregates:daily` command (SQL `GROUP BY` reduction, idempotent `updateOrCreate`,
  scheduled nightly at 02:30 when `storage.aggregates.enabled`).
- Composite indexes for stock/promo history (`competitor_product_id, captured_at`) and AI-decision
  subject lookups (`tenant_id, subject_type, subject_id`).

### Changed
- `league/csv` promoted from `require-dev` to `require` (CSV export is a runtime feature).

## [1.4.0] - 2026-05-25

B-phase **B2**: real, config-selectable marketplace **API adapters** with graceful scrape fallback.
All fixture-tested with `Http::fake`; no live calls in CI. 216 PHPUnit green (PHP 8.3/8.4),
Pint + PHPStan level 5 clean.

### Added
- **`AbstractApiAdapter`** — API-or-scrape `fetch()`: runs the configured marketplace driver, falling
  back to the existing HTML scrape path when the driver is `scrape`, credentials are missing, or the
  API call fails/returns empty (never throws).
- **Amazon** drivers `sp_api` (LWA bearer token → Product Pricing, no AWS SigV4), `keepa` (price +
  EAN/brand), `auto` (SP-API → Keepa → scrape).
- **eBay** Browse API driver (client-credentials OAuth → `getItemByLegacyId`).
- **Google Shopping** SERP driver (SerpApi-compatible `google_product` lookup).
- **Farfetch** — new first-class luxury adapter (`AdapterCode::Farfetch`), drivers `scrape` (default,
  JSON-LD), `retailed`, `apify`; host auto-mapped by `CompetitorSourceResolver`.
- `ApiProductResult` DTO + per-provider API clients under `Services/Scraping/Marketplaces/Api/`.
- Opt-in live marketplace smoke suite (`tests/Live`, `PI_LIVE_MARKETPLACE=1`), excluded from CI.

### Config
- Extended `marketplaces.*` with per-marketplace `driver` + credential sub-arrays
  (`amazon.sp_api`/`amazon.keepa`, `ebay`, `google_shopping.serp`, `farfetch.retailed`/`farfetch.apify`).
  No new hard dependencies — all APIs are plain REST via the `Http` facade.

## [1.3.0] - 2026-05-25

B-phase **B1**: real, provider-agnostic LLM layer built on the official `laravel/ai` SDK +
`padosoft/laravel-ai-regolo` (EU/Italian-safe Regolo). 198 PHPUnit green (PHP 8.3/8.4),
Pint + PHPStan level 5 clean; no live calls in CI.

### Added
- **`LlmProviderInterface`** with two drivers: `fake` (default, offline-deterministic) and
  `laravel-ai` (delegates to the `laravel/ai` SDK through an `AgentRunner` seam). Provider/model
  are config-driven and per-call overridable; `completeJson()` strips markdown fences and decodes
  strict JSON; `vision()` attaches image URLs.
- Real LLM-backed **`NarrativeWriter`**, **`ContentGapAnalyzer`**, **`PromoDetector`**, vision
  **`VisualMatcher`**, and a borderline-gated **`LlmJudgeMatcher`** matching step (runs only when
  the best cascade score is uncertain).
- **`laravel/ai` embedding driver** (`LaravelAiEmbeddingProvider`) selectable via
  `matching.embeddings.driver`; the deterministic `FakeEmbeddingProvider` stays the default.
- Every AI feature records an `ai_decision_logs` row (model, output, confidence) for EU AI Act
  auditability.
- Opt-in live smoke suite (`tests/Live`, gated on `PI_LIVE_LLM=1`), excluded from the default run.

### Config
- New `ai.llm.{driver,provider,model,vision_model,timeout}` block (shared LLM backing for all AI
  features) and `matching.embeddings.{driver,provider,model,dimensions}`.
- Removed superseded dead keys (`ai.narrative.driver`, `ai.promo_detection.driver`,
  `matching.visual`, `matching.llm.model`) — LLM backing is now the single `ai.llm.driver`.

## [1.2.0] - 2026-05-25

Admin A4 backfill (consumed by `padosoft/laravel-ai-price-intelligence-admin`). 169 PHPUnit green
(PHP 8.3/8.4), Pint + PHPStan level 5 clean.

### Added
- **Competitors list**: `GET /competitor-products` — paginated listings (confirmed by default,
  filterable by `status` / `host` / `monitoring_target_id` / `product_id`), each row eager-loading
  the matched product (`target.product`), the source host, and the latest price observation so the
  admin can compute the price delta without N extra requests.
- **Match candidate metadata**: cached the discovery snapshot (`candidate_title`,
  `candidate_image_url`, `candidate_price_cents`, `candidate_host`) on `MatchProposal` so the
  matches-review screen renders candidates without a second fetch; `GET /matches` eager-loads
  `target.product`.

### Changed
- `CompetitorProduct::latestPrice()` resolves via `ofMany(['captured_at'=>'max','id'=>'max'])` and
  the competitor-product detail (`show()`) tie-breaks `latest_*` rows on `id` — deterministic
  "latest" row under timestamp ties (bulk scrapes), consistent between list and detail.

## [1.1.0] - 2026-05-24

Admin-facing REST API expansion (consumed by `padosoft/laravel-ai-price-intelligence-admin`).
Full PHPUnit suite green (PHP 8.3/8.4), Pint + PHPStan level 5 clean.

### Added
- **Identity**: `GET /tenants/me` — resolved tenant + toggleable feature flags + caller abilities.
- **Dashboard**: `GET /stats` — tenant-scoped KPIs (products, active targets, confirmed competitors,
  pending matches, alerts 24h/unacked, anomalies 24h).
- **Observations**: `GET /observations/prices` (price history, from/to filters), `GET /competitor-products/{id}`
  (detail + latest price/stock/promo/content snapshots).
- **Intelligence**: `GET /forecasts`, `/anomalies`, `/reviews`, `/narratives`, `/assortment-gaps`,
  `/content-gaps`. New models + migration for narratives / assortment_gaps / content_gaps.
- **Pricing**: `GET/POST/PATCH/DELETE /rules`, `POST /rules/{id}/simulate` (dry-run, no persistence),
  `GET /rule-decisions`.
- **System**: `GET/POST/DELETE /api-keys` (plaintext returned once; DELETE = revoke),
  `GET /audit/fetch-logs`, `POST /targets/{id}/scrape:now`, `GET /alerts/stream` (Server-Sent Events).

## [1.0.0] - 2026-05-24

First public release. Enterprise Product & Price Intelligence / Competitor Monitoring for Laravel.
133 tests, CI green (PHP 8.3/8.4), Pint + PHPStan level 5 clean.

### Added
- **Foundations**: multi-tenant (single-DB + database-per-tenant ready), Sanctum + API-key auth with
  scopes, `ResolveTenant` middleware, configurable table names, idempotent migrations.
- **Catalog onboarding**: bulk JSON + CSV import, idempotent upsert, monitoring targets (product × country),
  `piprice:catalog:import` command.
- **Discovery & matching**: geo-aware competitor discovery via `padosoft/laravel-ai-search-providers`;
  cascade matcher (GTIN → MPN+brand → normalized name → embedding) with confidence band
  (≥85 auto-confirm / 60–84 review / <60 reject) and an admin review queue.
- **Scraping**: JSON-LD + OpenGraph extractor, generic HTTP driver, marketplace adapters
  (Amazon, eBay, Google Shopping, Idealo, Trovaprezzi) + factory.
- **Pricing & storage**: multi-currency FX normalization to a base currency; time-series observations
  (price/content/stock/promo) + fetch audit log.
- **Scheduling**: adaptive backoff + per-lane Horizon queues; `piprice:run-due` (auto-scheduled).
- **Alerts & webhooks**: price drop/raise, undercut, stock-out; HMAC-signed webhooks (when a secret is set).
- **AI layer**: statistical price forecasting (OLS + confidence interval) and anomaly detection
  (detrended-residual outliers + price-error), pluggable + toggleable; `ai_decision_logs` + `is_ai_generated`.
- **Review sentiment** (off by default): GDPR-safe, per-domain opt-in, mandatory PII redaction,
  anonymous aggregates only.
- **Repricer** (off by default, advisory-only): no-code strategies (match_cheapest, undercut_pct,
  beat_top_n, match_with_floor, dynamic_demand, custom) with margin floor / max-change / charm guards;
  emits `RepricingSuggested` and never applies prices.
- **Compliance**: robots.txt parser + per-domain policy, atomic per-domain rate limiter, PII redaction
  of scraped content, EU AI Act bridge (`AiActBridgeInterface` + null-object), `piprice:audit:prune`.
- **Docs**: README (with web-panel showcase), INTEGRATION-GUIDE, EXTENDING, COMPETITIVE-MATRIX, PROJECT spec.

### Notes
- Companion web admin panel: [`padosoft/laravel-ai-price-intelligence-admin`](https://github.com/padosoft/laravel-ai-price-intelligence-admin).
- Apache-2.0. PHP 8.3+, Laravel 11/12/13.

[Unreleased]: https://github.com/padosoft/laravel-ai-price-intelligence/compare/v1.6.0...HEAD
[1.6.0]: https://github.com/padosoft/laravel-ai-price-intelligence/compare/v1.5.0...v1.6.0
[1.5.0]: https://github.com/padosoft/laravel-ai-price-intelligence/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/padosoft/laravel-ai-price-intelligence/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/padosoft/laravel-ai-price-intelligence/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/padosoft/laravel-ai-price-intelligence/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/padosoft/laravel-ai-price-intelligence/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/padosoft/laravel-ai-price-intelligence/releases/tag/v1.0.0
