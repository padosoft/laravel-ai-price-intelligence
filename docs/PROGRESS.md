# PROGRESS.md — build status

> Live tracker so any session (or subagent) can resume instantly after an interruption.
> Update after every meaningful step. Source of truth for "where am I".

## Current status — 2026-05-23

**Roadmap**: see `docs/PROJECT.md` §18 (Phases 0–13). Building the **core** package fully before
the admin panel (user builds the admin template in parallel).

**Test command**: `vendor\bin\phpunit` via PowerShell. Current: **133 tests green**. CI green (8.3/8.4).

**Merged**: PR #1 (0–7), #2 (AI), #3 (review sentiment), #4 (repricer), #5 (compliance), #6 (docs)
— all via the full local-Copilot → push → CI → GitHub-Copilot → auto-merge loop. `main` @ fedf424.

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
- [x] **Phase 8 — AI layer (statistical core)** (PR #2 merged): ForecastProviderInterface +
  StatisticalForecaster (OLS trend + CI), AnomalyDetectorInterface + StatisticalAnomalyDetector
  (price_error + detrended-residual outliers), forecasts/anomalies/ai_decision_logs tables+models,
  AiDecisionLogger (EU AI Act, toggleable), null-object drivers honor toggles. 91 tests green.
  - **Phase 8b (LLM features, deferred to its own PR)**: NarrativeWriter, PromoDetector,
    ContentGapAnalyzer, AssortmentMapper, VisualMatcher — require LLM provider wiring; grouped with
    review-sentiment (Phase 9). Interfaces land when implemented to avoid untested stubs.
- [x] **Phase 9 — Review sentiment (GDPR-safe)** (PR #3 merged): ReviewSentimentInterface +
  LexiconSentimentAnalyzer, PiiFilterInterface + PiiFilter (laravel-pii-redactor or regex fallback),
  ReviewAggregator (off-by-default, per-domain opt-in, mandatory PII redaction, anonymous aggregates
  only), ReviewInsight model/migration, refusal exception.
- [x] **Phase 10 — Repricer engine** (PR #4 merged): StrategyCalculator (match_cheapest/undercut_pct/
  beat_top_n/match_with_floor/dynamic_demand/custom) with margin floor, max-change clamp, charm
  rounding; RepricerEngine (off by default, advisory-only, RepricingSuggested event); container-resolved
  custom strategies. ~10 Copilot rounds of pricing edge-case hardening.
- [x] **Phase 11 — Compliance hardening** (PR #5 merged): RobotsTxtPolicy, per-domain atomic
  rate limiter, PiiFilter, AiActBridge (null-object), audit retention + PruneAuditLogs command.
- [x] **Phase 12 — Docs + README** (PR #6 merged): README (banner + web-panel screenshot),
  INTEGRATION-GUIDE, EXTENDING, COMPETITIVE-MATRIX; api.tenant_resolver class-string support.
- [x] **Phase 13 — Release v1.0 prep** (PR #7 merged): CHANGELOG, phpstan level 5, pint, CI
  quality job (pint --test + phpstan on PHP 8.3/8.4).
- [x] **Final** — consolidated LESSON.md learnings into AGENTS.md "Distilled lessons" section +
  .claude/rules. CHANGELOG bumped 1.0.0-alpha → 1.0.0.

### Next action
**CORE COMPLETE — 13/13 phases merged.** Tag v1.0.0 + GitHub release on the core, then begin the
admin panel build (`laravel-ai-price-intelligence-admin`) per the saved plan: read the realized
template at ~/Downloads/ai-price-intelligence-web-panel, composer require the core, implement all
15 screens, PHP + Vitest + Playwright per screen, strict local-Copilot → CI → GitHub-Copilot loop,
then tag admin v1.0.0.
