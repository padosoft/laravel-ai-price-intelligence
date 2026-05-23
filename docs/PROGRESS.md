# PROGRESS.md — build status

> Live tracker so any session (or subagent) can resume instantly after an interruption.
> Update after every meaningful step. Source of truth for "where am I".

## Current status — 2026-05-23

**Roadmap**: see `docs/PROJECT.md` §18 (Phases 0–13). Building the **core** package fully before
the admin panel (user builds the admin template in parallel).

**Test command**: `vendor\bin\phpunit` via PowerShell. Current: **69 tests green**.

**Open PR**: #1 `feat/core-foundation` (phases 0–7). Copilot review requested + 1 P1 fixed
(AdaptiveBackoff import). Awaiting re-review / merge.

**STRICT per-phase workflow (mandatory from now on)** — see AGENTS.md / .claude/rules:
one PR per phase; local loop (phpunit + local `copilot` CLI review → fix) until clean → push →
loop until CI green AND GitHub Copilot review has zero actionable comments. Only then the phase is done.

### Phase status
- [x] **Phase 0 — Foundations**: composer.json, ServiceProvider, config, base migrations
  (tenants/products/monitoring_targets/competitor_sources/competitor_products/api_keys),
  enums, identifiers (GTIN/MPN/Slug), TenantContext + BelongsToTenant, ResolveTenant middleware,
  ApiKey, TestCase, health route. composer install + phpunit green.
- [x] **Phase 1 — Search-providers extension**: ProductScraperInterface, MarketplaceAdapterInterface,
  ProductSnapshot DTO, GeoSearchQueryFactory (country/locale via metadata). Decision: extend via
  metadata, no upstream code change (keeps upstream 300+ tests stable).
- [x] **Phase 2 — Catalog & onboarding**: ProductData DTO, CatalogImporter (idempotent upsert),
  CatalogController (bulk JSON, CSV, index, show, destroy), TargetController (store/index/update),
  BulkUpsertProductsRequest, ProductResource, CsvCatalogReader, ImportCatalogCommand.
- [x] **Phase 3 — Discovery & matching pipeline**: MatchScore/MatchOutcome DTOs, MatchStepInterface,
  EmbeddingProviderInterface + FakeEmbeddingProvider, steps (ExactGtin/MpnBrand/NormalizedName/
  EmbeddingSemantic), Vector cosine, MatchingPipeline + Factory, MatchProposal model+migration,
  CompetitorSourceResolver (adapter inference from host), MatchPersister (confirm/suggest/approve/
  reject), UrlDiscoveryService (search-providers + given_urls auto-confirm), DiscoverCompetitorUrlsJob,
  MatchController (index/approve/reject/manual competitor-product/discover:now). Tested with fake provider.
  - TODO later: dead-link recovery + AI cooldown (lands with Phase 6 scheduling).
- [x] **Phase 4 — Generic scraper + normalizer**: observations migrations+models (price/content/stock/
  promo/fetch_logs), HtmlProductExtractor (JSON-LD + @graph + OpenGraph), PriceParser (EU/US),
  GenericHttpScraper (Http facade, graceful unreachable), FxProviderInterface + FixedFxProvider,
  PriceNormalizer (FX→EUR base), ScrapeService (persist observations + FetchLog). Tested via Http::fake.
  - TODO later: Browsershot driver wiring (spatie/browsershot, skipped without binary), VAT/unit-price refinement, partitioning runtime (PartitionManager) — Phase 11/scale.
- [x] **Phase 5 — Marketplace adapters**: MarketplaceAdapterInterface + AbstractScrapeAdapter,
  Amazon (ASIN)/eBay/GoogleShopping/Idealo/Trovaprezzi/Generic adapters, MarketplaceAdapterFactory
  (config overrides for custom SP-API adapters), ScrapeService resolves adapter from source.adapter_code.
- [x] **Phase 6 — Scheduling + adaptive backoff**: AdaptiveBackoff (pure), ScrapeCompetitorProductJob,
  TargetScheduler (dispatch due + reschedule), RunDueTargetsCommand + everyMinute schedule. Bus::fake tested.
  - TODO later: stability/significant-change inputs feed from real diffs (Phase 7), Horizon tags in prod.
- [x] **Phase 7 — Alerts + Webhooks**: alerts + webhook_subscriptions migrations+models (secret
  encrypted at rest), WebhookSigner (HMAC sha256), PriceChangeEvaluator (pure: dropped/raised/undercut/
  stockout + severity), WebhookDispatcher (Http, per-endpoint failure isolation), AlertDispatcher,
  ScrapeService diffs vs previous obs and raises alerts + signed webhooks, AlertController + WebhookController.
  Tested end-to-end via Http::fake.
- [ ] Phase 8 — AI layer (visual, content-gap, forecast, anomaly, narrative, promo, assortment)
- [ ] Phase 9 — Review sentiment (GDPR-safe, pii-redactor)
- [ ] Phase 10 — Repricer engine (opt, off default)
- [ ] Phase 11 — Compliance hardening (robots, rate-limit, PiiFilter, AiActBridge, audit)
- [ ] Phase 12 — Docs finali + README + COMPETITIVE-MATRIX + openapi.json
- [ ] Phase 13 — Release v1.0 alpha prep (CHANGELOG, phpstan, pint, CI)
- [ ] **Final** — consolidate LESSON.md learnings into AGENTS.md / .claude/rules / skills

### Next action
Phase 4: ProductScraper drivers (GenericHttp + Browsershot), PriceNormalizer (FX/VAT/unit price),
FxProviderInterface, observations migrations (price/content/stock/promo + fetch_logs), PartitionManager,
ScrapeCompetitorProductJob skeleton. Tests against saved HTML fixtures (no live HTTP).
