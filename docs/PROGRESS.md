# PROGRESS.md — build status

> Live tracker so any session (or subagent) can resume instantly after an interruption.
> Update after every meaningful step. Source of truth for "where am I".

## Current status — 2026-05-23

**Roadmap**: see `docs/PROJECT.md` §18 (Phases 0–13). Building the **core** package fully before
the admin panel (user builds the admin template in parallel).

**Test command**: `vendor\bin\phpunit` via PowerShell. Current: **83 tests green**. CI green (8.3/8.4).

**Open PR**: #1 `feat/core-foundation` (phases 0–7). Multiple Copilot review cycles; all actionable
findings addressed (AdaptiveBackoff import, tenant scoping, secret hiding, price parsing, 204 return
types, N+1, doc accuracy). Awaiting final clean review / merge.

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
- [~] **Phase 8 — AI layer (statistical core)** (PR open): ForecastProviderInterface +
  StatisticalForecaster (OLS trend + CI), AnomalyDetectorInterface + StatisticalAnomalyDetector
  (price_error + detrended-residual outliers), forecasts/anomalies/ai_decision_logs tables+models,
  AiDecisionLogger (EU AI Act, toggleable), null-object drivers honor toggles. 91 tests green.
  - **Phase 8b (LLM features, deferred to its own PR)**: NarrativeWriter, PromoDetector,
    ContentGapAnalyzer, AssortmentMapper, VisualMatcher — require LLM provider wiring; grouped with
    review-sentiment (Phase 9). Interfaces land when implemented to avoid untested stubs.
- [ ] Phase 9 — Review sentiment (GDPR-safe, pii-redactor)
- [ ] Phase 10 — Repricer engine (opt, off default)
- [ ] Phase 11 — Compliance hardening (robots, rate-limit, PiiFilter, AiActBridge, audit)
- [ ] Phase 12 — Docs finali + README + COMPETITIVE-MATRIX + openapi.json
- [ ] Phase 13 — Release v1.0 alpha prep (CHANGELOG, phpstan, pint, CI)
- [ ] **Final** — consolidate LESSON.md learnings into AGENTS.md / .claude/rules / skills

### Next action
Close PR #1 (await final clean Copilot review + merge to main). Then **Phase 8 — AI layer** as its
own PR following the strict per-phase loop: ForecastProviderInterface + StatisticalForecaster,
AnomalyDetector, NarrativeWriter, PromoDetector, ContentGapAnalyzer, AssortmentMapper, VisualMatcher,
ai_decision_logs + is_ai_generated, all pluggable with config toggles. Then phases 9–13 + Final.
