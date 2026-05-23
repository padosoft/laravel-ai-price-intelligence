# laravel-ai-price-intelligence — Project Specification (PROJECT.md)

> **Enterprise Product & Price Intelligence / Competitor Monitoring** per ecommerce.
> Package Laravel-native, open-source **Apache-2.0**, PHP **8.3+**, Laravel **11/12/13**.
> Alternativa self-hostable, AI-native ed **EU AI Act-ready** a Netrivals (Lengow) e Competitoor.

- **Package**: `padosoft/laravel-ai-price-intelligence`
- **Repo**: https://github.com/padosoft/laravel-ai-price-intelligence
- **Admin panel**: https://github.com/padosoft/laravel-ai-price-intelligence-admin
- **Owner**: Padosoft / Lorenzo Padovani

---

## Table of Contents

1. [Vision & posizionamento](#1-vision--posizionamento)
2. [Boundary: cosa fa e cosa NON fa](#2-boundary-cosa-fa-e-cosa-non-fa)
3. [Architettura](#3-architettura)
4. [Dipendenze](#4-dipendenze)
5. [Multi-tenancy](#5-multi-tenancy)
6. [Data model](#6-data-model)
7. [API REST pubblica](#7-api-rest-pubblica)
8. [Workflows](#8-workflows)
9. [Product matching pipeline](#9-product-matching-pipeline)
10. [Scraping & marketplace adapters](#10-scraping--marketplace-adapters)
11. [AI features](#11-ai-features)
12. [Repricing engine (opzionale)](#12-repricing-engine-opzionale)
13. [Alerts, notifications, webhooks](#13-alerts-notifications-webhooks)
14. [Compliance: GDPR & EU AI Act](#14-compliance-gdpr--eu-ai-act)
15. [Configuration reference](#15-configuration-reference)
16. [Sizing & deployment](#16-sizing--deployment)
17. [Integration guide (ecommerce host)](#17-integration-guide-ecommerce-host)
18. [Build phases](#18-build-phases)
19. [Testing & verification](#19-testing--verification)
20. [Competitive matrix](#20-competitive-matrix)
21. [Roadmap](#21-roadmap)

---

## 1. Vision & posizionamento

Gli ecommerce monitorano i competitor per: dynamic pricing, MAP enforcement, gap d'assortimento,
benchmark contenuti. Oggi pagano licenze SaaS costose (Netrivals, Competitoor) che sono black-box,
non self-hostable e non integrate nello stack Laravel.

`laravel-ai-price-intelligence` è il **motore di intelligence**: riceve gli SKU da monitorare,
scopre i competitor, scrapa prezzi/stock/contenuti, li normalizza, applica AI (matching, forecast,
anomaly, narrative, content-gap, assortment, promo, review sentiment) ed espone **API + webhook**
che l'ecommerce consuma per le proprie decisioni (es. MarginOS per il repricing).

**Tagline**: *"The only open-source, AI-native, EU-compliant-by-design competitor price monitor,
self-hostable, that plugs natively into your Laravel/ecommerce stack."*

---

## 2. Boundary: cosa fa e cosa NON fa

### Fa (dentro il package)
- Ingest catalogo SKU (bulk JSON, CSV/Excel, webhook, console, event listener host).
- Discovery URL competitor via `padosoft/laravel-ai-search-providers` (AI search geo-aware).
- Product matching a cascata con confidence + human-review queue.
- Scraping prezzo/stock/contenuto (generic + marketplace adapter + Browsershot).
- Normalizzazione prezzi (multi-currency FX, IVA, unit price, shipping).
- Storage time-series partizionato + retention.
- AI: visual match, content gap, forecast, anomaly, narrative, promo, assortment, review sentiment.
- Repricing engine **opzionale** (solo suggerimenti, mai apply).
- Alert + Notifications + Webhook firmati.
- Compliance GDPR (PII redaction) + EU AI Act (disclosure + bridge).

### NON fa (resta nell'ecommerce host)
- **Apply del prezzo finale** / gestione margini / pricing definitivo (→ MarginOS o logica host).
- Gestione ordini, carrello, checkout, inventario proprio.
- Promo planning operativo del proprio catalogo.
- Il package **fornisce segnali**, l'host **decide e agisce**.

---

## 3. Architettura

```
┌─────────────────────────────────────────────────────────────────┐
│ Ecommerce Host (Laravel)                                          │
│  POST /catalog · webhook receiver · PriceChanged listener · MarginOS│
└───────────────┬───────────────────────────────▲───────────────────┘
   Bulk/CSV/Webhook                              │ Webhook HMAC + Events
                ▼                                │
┌──────────────────────────────────────────────────────────────────┐
│ CORE: laravel-ai-price-intelligence                                │
│  API (Sanctum/ApiKey) → TenantContext                              │
│  UrlDiscovery → MatchingPipeline → Validation queue                │
│  Scheduler (Horizon, per-tenant queues, adaptive backoff)          │
│   ├─ GenericProductScraper (search-providers / Browsershot)        │
│   └─ Marketplace adapters (Amazon SP-API+Keepa, eBay, GShopping,   │
│      Idealo, Trovaprezzi)                                          │
│  PriceNormalizer (FX, VAT, unit price)                             │
│  Storage: price_observations (partitioned) + content/stock/promo   │
│  AI layer (8 moduli pluggable) · Repricer (opt) · ReviewInsight    │
│  Compliance: pii-redactor · AiActBridge                            │
│  Alerts (Laravel Notifications) · Webhook dispatcher HMAC          │
└───────────────────────────────▲────────────────────────────────────┘
                                 │ HTTPS Sanctum
┌────────────────────────────────────────────────────────────────────┐
│ ADMIN: laravel-ai-price-intelligence-admin (React 19 + Vite + TS)   │
└────────────────────────────────────────────────────────────────────┘
```

### Principi
- **Tutto pluggable via Interface + Driver**: scraper, matcher, embedding, forecast, FX, anomaly,
  promo, repricer, proxy. Cambio provider = riga di config.
- **Null-object pattern** per dipendenze opzionali (AiActBridge, PiiRedactor): no-op se assenti.
- **Tenant-scoped** ovunque (global scope Eloquent).
- **Queue-first**: ogni operazione costosa (discovery, scrape, enrich, AI) è un Job idempotente.

### Struttura cartelle
```
src/
├─ PriceIntelligenceServiceProvider.php
├─ Contracts/          ProductScraperInterface, MarketplaceAdapterInterface,
│                      ProductMatcherInterface, EmbeddingProviderInterface,
│                      ForecastProviderInterface, FxProviderInterface,
│                      AnomalyDetectorInterface, PromoDetectorInterface,
│                      RepricerEngineInterface, ProxyResolverInterface,
│                      ReviewInsightInterface, AiActBridgeInterface
├─ Models/             Tenant, Product, MonitoringTarget, CompetitorSource,
│                      CompetitorProduct, MatchProposal, PriceObservation,
│                      ContentSnapshot, StockObservation, PromoObservation,
│                      FetchLog, RepricingRule, RuleDecision, Alert,
│                      WebhookSubscription, ApiKey, Forecast, Anomaly,
│                      Narrative, AssortmentGap, ContentGap, ReviewInsight,
│                      AiDecisionLog
├─ Data/               DTO spatie/laravel-data
├─ Enums/              MatchStatus, FetchStatus, PromoType, AlertType,
│                      Frequency, AdapterCode, RuleStrategy, Severity
├─ Http/               Controllers/Api/V1, Requests, Resources, Middleware
├─ Services/
│  ├─ Discovery/       UrlDiscoveryService
│  ├─ Scraping/        GenericProductScraper, Drivers/{SearchProvider,Browsershot},
│  │                   Marketplaces/{Amazon,Ebay,GoogleShopping,Idealo,Trovaprezzi}Adapter
│  ├─ Matching/        MatchingPipeline, Steps/{ExactGtin,MpnBrand,NormalizedName,
│  │                   EmbeddingSemantic,VisualMatch,LlmJudge}Matcher
│  ├─ Pricing/         PriceNormalizer, RepricerEngine
│  ├─ Compliance/      RobotsTxtPolicy, RateLimiter, PiiFilter, AiActBridge
│  ├─ Alerts/          AlertDispatcher
│  ├─ Webhooks/        WebhookSigner, WebhookDispatcher
│  └─ Ai/              VisualMatcher, ContentGapAnalyzer, Forecaster,
│                      AnomalyDetector, NarrativeWriter, PromoDetector,
│                      AssortmentMapper, ReviewInsight/{Scraper,Aggregator,Sentiment}
├─ Jobs/               DiscoverCompetitorUrlsJob, ScrapeCompetitorProductJob,
│                      ValidateMatchJob, EvaluateRepricingRulesJob,
│                      GenerateWeeklyNarrativeJob, DetectAnomaliesJob,
│                      ImportCatalogJob, DispatchWebhookJob, MapAssortmentJob
├─ Events/             PriceChanged, PriceDropped, PriceRaised, UndercutDetected,
│                      StockOut, StockBackIn, BuyBoxLost, BuyBoxWon, MapViolated,
│                      CompetitorFound, CompetitorUrlDead, MatchSuggested,
│                      MatchConfirmed, MatchRejected, AnomalyDetected,
│                      PromoStarted, PromoEnded, RepricingSuggested, NarrativeGenerated
├─ Listeners/
├─ Notifications/      AlertNotification (webhook/mail/slack/teams/db)
├─ Console/Commands/   piprice:catalog:import, piprice:scrape:now,
│                      piprice:partition:create, piprice:discover, piprice:narrative
├─ Support/            Partitioning/PartitionManager, Tenant/TenantContext,
│                      Identifiers/{Gtin,Ean,Upc}Validator, MpnNormalizer, SlugNormalizer
└─ Facades/            PriceIntelligence
```

---

## 4. Dipendenze

### Obbligatorie
| Package | Uso |
|---|---|
| `padosoft/laravel-ai-search-providers` | Discovery URL + scraping AI-assisted (esteso, vedi §10.6) |
| `laravel/sanctum` | Auth API (bearer token SPA) |
| `spatie/laravel-data` | DTO tipizzati |
| `spatie/laravel-query-builder` | Filtering/sorting API |
| `spatie/laravel-permission` | RBAC tenant/admin |
| `league/csv` | Import/export CSV |

### Opzionali (suggest) — attive se presenti
| Package | Uso | Default |
|---|---|---|
| `laravel/horizon` | Queue dashboard + supervisione | consigliato in prod |
| `padosoft/laravel-pii-redactor` | **Redaction PII su contenuti scraped + obbligatorio in review sentiment** | attivo se presente |
| `padosoft/laravel-ai-act-compliance` | Auto-wiring EU AI Act (disclosure, risk register, human-review) | attivo se presente |
| `spatie/browsershot` | Rendering Chrome headless per siti JS-heavy / fallback economico | driver opt-in |
| `stancl/tenancy` | Solo per modalità DB-per-tenant | off |
| `phpoffice/phpspreadsheet` | Import Excel | opt |
| `openai-php/laravel` / `mistral-ai/sdk` | LLM judge / narrative / vision | configurabile |

> **Forecasting**: solo driver `Statistical` PHP nel core (zero-deps). Nessun microservizio Python.

---

## 5. Multi-tenancy

**Dual-mode**, scelto via `config('price-intelligence.tenancy.mode')`:

- **`single` (default)**: una sola DB, `tenant_id` su ogni tabella, global scope `BelongsToTenant`.
  Risoluzione tenant da Sanctum token o ApiKey via `TenantContext`. Queue taggate
  `tenant:{id}:{lane}`. Rate-limit per-tenant.
- **`database` (opzionale)**: `stancl/tenancy` multi-database. Ogni tenant enorme (5M+ SKU) ha il
  proprio DB dedicato. **Niente sharding**. Le migration girano su entrambi via
  `TenancyBootstrapper`.

`TenantContext` espone `currentTenantId()`, `runForTenant($id, $callback)`. Tutti i Job ricevono
`tenant_id` e ripristinano il contesto in `handle()`.

---

## 6. Data model

> Tutte le tabelle hanno `tenant_id` (indicizzato). Le time-series sono **partizionate per mese**
> (`PartitionManager`). Prezzi sempre in **interi (cents)** + currency ISO-4217.

### 6.1 Catalogo & monitoring
- **tenants**: `id, code, name, settings(json), created_at`
- **products** (catalogo host): `id, tenant_id, external_id (uniq/tenant), sku, gtin, mpn, brand,
  model, name, attributes(json), images(json), categories(json), our_price_cents, currency,
  base_country, deleted_at`
- **monitoring_targets** (product × country): `id, tenant_id, product_id, country(ISO),
  locale, frequency_preset(enum), cron_custom, status(active|paused|stopped), priority,
  options(json), last_check_at, next_check_at, backoff_factor`
- **competitor_sources**: `id, host, display_name, country, adapter_code(enum), robots_policy,
  rate_limit_rpm, options(json)`
- **competitor_products** (match): `id, tenant_id, monitoring_target_id, competitor_source_id,
  url, external_ref(ASIN/...), match_status(confirmed|suggested|rejected|dead), match_confidence,
  match_method(enum), validated_by, validated_at, last_seen_at, dead_since`
- **match_proposals** (review queue 60–85%): `id, tenant_id, monitoring_target_id, candidate_url,
  evidence(json), confidence, source(ai|manual), status(pending|approved|rejected), reviewer_id,
  reviewed_at`

### 6.2 Observations (partizionate per mese)
- **price_observations**: `id, tenant_id, competitor_product_id, captured_at, price_cents,
  currency, price_eur_cents, shipping_cents, available, raw_price_text, source_job_id, fetch_log_id`
- **content_snapshots**: `id, tenant_id, competitor_product_id, captured_at, title,
  description_md, attributes(json), og(json), jsonld(json), images(json), html_hash, dom_diff_score`
- **stock_observations**: `id, tenant_id, competitor_product_id, captured_at, available,
  qty_estimate, buybox_winner, seller_name, seller_rating`
- **promo_observations**: `id, tenant_id, competitor_product_id, captured_at, promo_type(enum),
  valid_from, valid_to, condition_text, effective_discount_pct`
- **fetch_logs** (audit): `id, tenant_id, competitor_source_id, url, method, status, latency_ms,
  ua, ip_egress, proxy_used, error, body_hash, response_bytes, robots_allowed, search_provider,
  driver, captured_at`

### 6.3 AI / derived
- **forecasts**: `competitor_product_id, horizon_days, forecast_price_cents, ci_low, ci_high,
  model_version, generated_at, is_ai_generated`
- **anomalies**: `competitor_product_id, type(enum), detected_at, severity, evidence(json),
  acknowledged_by, is_ai_generated`
- **narratives**: `tenant_id, period(ISO week), summary_md, highlights(json), generated_at,
  is_ai_generated`
- **assortment_gaps**: `tenant_id, category_path, competitor_source_id, competitor_product_url,
  importance_score, status`
- **content_gaps**: `tenant_id, product_id, suggestions(json), seo_score_delta, generated_at,
  is_ai_generated`
- **review_insights** (GDPR-safe, aggregati): `tenant_id, competitor_product_id, period,
  sentiment_score, themes(json), sample_count, generated_at, is_ai_generated`
- **ai_decision_logs** (EU AI Act): `tenant_id, subject_type, subject_id, model, model_version,
  input_hash, output(json), confidence, cost_cents, human_reviewed, created_at`

### 6.4 Rules, alerts, webhooks, auth
- **repricing_rules**: `tenant_id, name, target_filter(json), strategy(enum), parameters(json),
  priority, status`
- **rule_decisions**: `tenant_id, repricing_rule_id, product_id, current_price_cents,
  suggested_price_cents, applied, reason, created_at`
- **alerts**: `tenant_id, type(enum), severity, payload(json), product_id, competitor_product_id,
  channel_status(json), acknowledged_at`
- **webhook_subscriptions**: `tenant_id, url, events(json), secret_encrypted, active, last_status,
  last_at`
- **api_keys**: `tenant_id, name, key_hash, scopes(json), last_used_at, expires_at, revoked_at`

---

## 7. API REST pubblica

Base path `/api/v1`. Auth: `Authorization: Bearer <sanctum>` (UI) o `X-Api-Key: <key>` (M2M).
Tenant resolto dal token. Paginazione cursor. Filtering via `spatie/query-builder`.
Response envelope JSON:API-ish `{ data, meta, links }`. Errori RFC-7807 `application/problem+json`.

### 7.1 Catalog & target
```
POST   /catalog/products:bulk      upsert massivo (≤5000/req, idempotent via external_id)
GET    /catalog/products           lista filtrabile (brand, category, country, has_match)
GET    /catalog/products/{id}
DELETE /catalog/products/{id}
POST   /catalog/products:csv       multipart CSV
POST   /catalog/products:excel     multipart XLSX
POST   /targets                    crea monitoring {product_id, country, locale, frequency,
                                    given_urls?, given_domains?}
PATCH  /targets/{id}               pause/resume/reschedule
POST   /targets/{id}/discover:now
POST   /targets/{id}/scrape:now
```

### 7.2 Matches
```
GET    /matches?status=suggested   review queue
POST   /matches/{id}/approve
POST   /matches/{id}/reject
POST   /competitor-products        manuale (URL diretto, salta AI search)
GET    /competitor-products/{id}   include ultimo snapshot
```

### 7.3 Observations / analytics
```
GET /observations/prices?target_id=&from=&to=&aggregate=hourly|daily
GET /observations/prices/diff?target_id=&since=7d
GET /forecasts?target_id=&horizon=14
GET /anomalies?since=24h
GET /narratives?period=2026-W21
GET /content-gaps?product_id=
GET /assortment-gaps?category=
GET /review-insights?competitor_product_id=     (solo se modulo abilitato)
```

### 7.4 Pricing/repricing (opt)
```
GET/POST/PATCH/DELETE /rules
POST /rules/{id}/simulate          dry-run, ritorna decisioni
GET  /rule-decisions?since=
```

### 7.5 Alerts / webhooks
```
GET  /alerts?ack=false
POST /alerts/{id}/ack
GET  /alerts/stream                SSE real-time (per admin)
GET/POST/PATCH/DELETE /webhook-subscriptions
POST /webhook-subscriptions/{id}/test
```

### 7.6 Admin / system
```
GET  /tenants/me                   settings
GET/POST /api-keys                 (scope admin)
GET  /jobs/stats                   Horizon proxy
GET  /audit/fetch-logs?since=
GET  /compliance/ai-decisions      (EU AI Act log)
GET  /openapi.json                 schema OpenAPI 3.1 (per client typed admin)
```

### 7.7 Webhook eventi outbound (HMAC `X-PI-Signature: sha256=<hex>`)
`price.changed`, `price.dropped`, `price.raised`, `undercut.detected`, `stock.out`,
`stock.back_in`, `buybox.lost`, `buybox.won`, `map.violated`, `competitor.new_found`,
`competitor.url_dead`, `match.suggested`, `match.confirmed`, `match.rejected`,
`anomaly.detected`, `promo.started`, `promo.ended`, `repricing.suggested`,
`narrative.generated`, `digest.daily` (batch opt-in).

Payload: `{ id, event, tenant_id, occurred_at, data:{...}, is_ai_generated? }`.
Firma: `HMAC-SHA256(secret, raw_body)`. Retry: backoff esponenziale 5 tentativi.

---

## 8. Workflows

### 8.1 Onboarding + discovery
1. Host `POST /catalog/products:bulk` (SKU con gtin/mpn/brand/model/categories).
2. Host `POST /targets {product_id, country:"IT", frequency:daily}` (può passare `given_urls`
   per saltare discovery o `given_domains` per restringere).
3. Core enqueue `DiscoverCompetitorUrlsJob` su `tenant:{id}:discovery`.
4. Job → `SearchProviderManager.searchWeb({brand, model, color, country, site?})` (geo-aware).
5. Per candidato URL → normalize/canonical/dedup → fetch leggero → `MatchingPipeline` (vedi §9).
6. conf≥85 → `CompetitorProduct` confirmed; 60–85 → `MatchProposal` pending; <60 → reject log.
7. Dispatch `MatchConfirmed`/`MatchSuggested`.

### 8.2 Scrape ricorrente
```
Ogni minuto: scheduler seleziona target con next_check_at<=now (active), LIMIT batch.
Per ogni CompetitorProduct confirmed → ScrapeCompetitorProductJob:
  1. RobotsTxtPolicy.check → skip se disallow (log)
  2. RateLimiter.acquire(domain) → defer se exceeded
  3. Resolve adapter (amazon|ebay|google_shopping|idealo|trovaprezzi|generic)
  4. Adapter → ProductSnapshot DTO normalizzato
  5. PriceNormalizer (VAT, FX→EUR, unit price, shipping)
  6. PiiFilter.redact(testi) se pii-redactor presente
  7. Diff vs ultima observation → emit events (PriceChanged, StockOut, ...)
  8. Persist price/content/stock/promo observations (dedup via hash)
  9. Update last_seen_at; aggiorna next_check_at (adaptive backoff §8.4)
 10. Async: DetectAnomaliesJob, EvaluateRepricingRulesJob (se abilitato)
```

### 8.3 Dead-link recovery
Su 404/canonical-drift/match-loss → `CompetitorProduct.match_status=dead`. Se l'URL era stato
trovato dall'AI → 1 retry `DiscoverCompetitorUrlsJob(exclude_dead=true)`; se trova nuovo URL
conf≥85 → nuovo record confirmed; altrimenti `competitor.url_dead` + cooldown AI configurabile.
Se URL fornito manualmente → solo alert, no AI search.

### 8.4 Adaptive backoff
```
base = frequency_preset
stability = % observations senza variazione prezzo nelle ultime 14
if stability > 0.95 && base < weekly → factor = min(factor*2, 4)
if ultima variazione > 5% → factor = 0.5 (accelera)
next_check_at = last_check_at + base * factor
```
Toggle: `resilience.adaptive_backoff.enabled`, `.max_factor`.

### 8.5 Weekly narrative
`GenerateWeeklyNarrativeJob` (cron lunedì 07:00/tenant): aggrega top movers/promo/anomalie/new
competitor/assortment gap → 1 chiamata LLM → `Narrative` (`is_ai_generated=true`) → notify.

### 8.6 Anomaly detection
Dopo ogni `PriceChanged`: statistical (p5/p95 outlier, batch-update window, civetta) + LLM judge
solo se borderline. Persist `Anomaly` + `Alert`.

---

## 9. Product matching pipeline

Cascata in `MatchingPipeline`, ogni step ritorna `MatchScore(0–100, method, evidence)`:

1. **ExactGtinMatcher** — GTIN/EAN/UPC validato (checksum) uguale → **100**.
2. **MpnBrandMatcher** — MPN normalizzato + brand uguali → **95**.
3. **NormalizedNameMatcher** — brand + model normalizzati, Jaro-Winkler + token overlap → **70–90**.
4. **EmbeddingSemanticMatcher** — cosine su embedding(titolo+attributi) → **50–90**
   (`EmbeddingProviderInterface`: OpenAI / Voyage / local; cache su `match_embeddings`).
5. **VisualMatcher** — vision LLM confronta foto (prioritario fashion/lusso) → riallinea score.
6. **LlmJudgeMatcher** — eseguito **solo** se score borderline 60–85 → giudizio finale 0–100.

**Threshold** (config `matching.confidence_band`, default `[60,85]`):
- `≥85` → `confirmed` automatico.
- `60–85` → `MatchProposal` pending (admin review).
- `<60` → reject (log only).

**Costo controllato**: LLM/vision solo su 5–10% borderline; embedding in cache; ricalcolo solo se
`dom_diff_score > matching.recompute_on_dom_diff_threshold`. Stato persistito: **mai ri-AI** lo
stesso match confermato salvo cambio DOM significativo.

---

## 10. Scraping & marketplace adapters

`MarketplaceAdapterInterface::fetch(CompetitorProduct): ProductSnapshot`.
`AdapterCode` enum → factory risolve l'implementazione.

### 10.1 GenericProductScraper (default)
Driver selezionabile (`scraping.default_driver`):
- **`search_provider`**: usa `ProductScraperInterface` di laravel-ai-search-providers
  (Firecrawl `/scrape`, Exa `/contents`, Tavily `/extract`) → estrae title/desc/og/jsonld/
  schema.org Product/breadcrumb/images/prices.
- **`browsershot`**: Chrome headless (siti JS-heavy / fallback economico). Cluster-ready via
  `scraping.browsershot.cluster_nodes[]`.
- **`auto`**: prova search_provider, fallback browsershot se estrazione insufficiente.

### 10.2 AmazonAdapter
`marketplaces.amazon.driver`: `sp_api | keepa | scrape | auto`.
- **SP-API** (ufficiale, legale): prezzo, buy-box, offers, seller. Richiede credenziali host.
- **Keepa**: storico prezzi/rank, fallback se SP-API rate-limited.
- **scrape**: fallback generico. Adaptive backoff aggressivo per evitare block.

### 10.3 EbayAdapter — eBay Browse/Finding API.
### 10.4 GoogleShoppingAdapter — Brave/SERP + parsing JSON-LD offerte.
### 10.5 Idealo/Trovaprezzi — generic + selettori CSS registrati per-source.

### 10.6 Estensioni richieste a `padosoft/laravel-ai-search-providers`
PR backward-compatible upstream (release minor):
1. `SearchQueryData.country` + `.locale` propagati ai driver (Brave country/search_lang,
   Tavily country, Firecrawl `location`, WebSearchAPI gl/hl, DuckDuckGo `kl`).
2. **`ProductScraperInterface`** + `searchAndScrape(): ProductExtractionResult` con driver
   Firecrawl/Exa/Tavily/GenericHttp → output ricco (og, jsonld, breadcrumb, prices[], html_hash).
3. **Caching decorator** (Redis, chiave `provider+driver+query_hash`, TTL).
4. **Rate-limit runtime** (oggi advisory) leaky bucket per provider.
5. **Perceptual-hash dedup** immagini.

---

## 11. AI features

Tutte pluggable (Interface+Driver), tutte con toggle `ai.<feature>.enabled` e provider/model
configurabili. Ogni output marcato `is_ai_generated=true` e loggato in `ai_decision_logs`.

| # | Feature | Cosa fa | Default |
|---|---|---|---|
| 11.1 | **Visual matching** | vision LLM confronta foto prodotto (fashion/lusso) | on |
| 11.2 | **Content gap** | confronta tuoi titoli/desc/foto vs competitor → suggerimenti SEO | on |
| 11.3 | **Forecast** | driver `Statistical` PHP: MA + seasonal index + confidence interval | on |
| 11.4 | **Anomaly** | outlier p5/p95, batch night-update, prezzo civetta | on |
| 11.5 | **Narrative** | digest LLM settimanale leggibile per category manager | on |
| 11.6 | **Promo detection** | distingue listino/sconto/bundle/loyalty/clearance, normalizza window | on |
| 11.7 | **Assortment** | scopre prodotti competitor che tu NON hai, gap-scoring | on |
| 11.8 | **Review sentiment** | aggregati anonimi GDPR-safe (vedi §14) | **off** |

Dettagli forecast: `ForecastProviderInterface::forecast(observations[], horizon): Forecast`. Solo
`StatisticalDriver` fornito; interface pubblica per estensioni di terzi (no microservizio Python).

---

## 12. Repricing engine (opzionale)

`repricer.enabled` default **false** (Lorenzo usa MarginOS; per la community è gratis).
**Non applica mai** il prezzo: emette `repricing.suggested` e logga `rule_decisions`.

Strategie built-in: `match_cheapest`, `beat_top_n`, `undercut_pct`, `match_with_floor`,
`dynamic_demand` (forecast+stock), `custom` (closure registrata).

Esempio rule:
```json
{
  "name": "Beat Amazon by 2% with margin floor",
  "target_filter": { "categories": ["smartphones"], "countries": ["IT"] },
  "strategy": "beat_top_n",
  "parameters": {
    "top_n": 3, "domains_priority": ["amazon.it"], "delta_pct": -2,
    "min_margin_pct": 18, "max_change_per_day_pct": 5, "round_to_charm": 0.99
  },
  "schedule": "after_each_observation"
}
```

---

## 13. Alerts, notifications, webhooks

- **Alert** persistito per ogni evento significativo (severità calcolata da delta/tipo).
- **Laravel Notifications**: channel `webhook`, `mail`, `slack`, `teams`, `database`, + custom
  pluggable. Cliente sceglie channel per tipo di alert via settings.
- **WebhookDispatcher**: firma HMAC, retry backoff, log delivery. `digest.daily` opt-in.

---

## 14. Compliance: GDPR & EU AI Act

### 14.1 GDPR / PII
- `padosoft/laravel-pii-redactor` integrato: se presente, ogni testo scraped passa per
  `Pii::redact()` prima della persistenza (`pii.enabled` auto).
- **No PII at rest**: ContentSnapshot ripulito (email/telefono/IBAN/CF/P.IVA).
- **Robots.txt**: default-respect per-domain; opt-out esplicito audit-logged.
- **Rate-limit gentleman** per-domain (leaky bucket Redis, default 30 rpm).
- **Audit log** `fetch_logs` con retention configurabile.

### 14.2 EU AI Act
- **Classificazione**: rischio limitato/minimo (non Annex III). Tratta prodotti, non persone.
- **Nativo (sempre)**: flag `is_ai_generated`, `ai_decision_logs`, human-in-the-loop nel matching.
- **Bridge opzionale** `padosoft/laravel-ai-act-compliance` (`AiActBridge`, no-op se assente):
  RiskRegister, Disclosure (Art. 50), HumanReviewTracker, Consent (opt-in review sentiment),
  Incident, RegulatoryFeed, ComplianceAttestation.
- Config `ai_act.enabled` (auto-detect), `ai_act.disclosure.enabled`, `ai_act.decision_log.enabled`.

### 14.3 Review sentiment (modulo GDPR-safe, OFF default)
`review_insight.enabled=false` + opt-in **per-domain** audit-logged. Pipeline:
1. `ReviewScraper` solo se domain in `review_insight.allowed_domains[]`.
2. **Ogni review → `laravel-pii-redactor` (obbligatorio)**: rimossa ogni PII PRIMA di persist/LLM.
3. `SentimentAnalyzer` (LLM) → **solo aggregati anonimi** (sentiment, temi, keyword cluster).
4. `ReviewAggregator` salva `review_insights` (no testo grezzo, no autore).

---

## 15. Configuration reference

`config/price-intelligence.php` (estratto delle chiavi principali):
```php
return [
    'tenancy' => ['mode' => 'single'],                 // single|database
    'discovery' => [
        'providers_priority' => ['brave', 'tavily', 'firecrawl'],
        'cache' => ['enabled' => true, 'ttl' => 86400],
        'monthly_budget_guard' => null,                 // max search/month per tenant
        'ai_search_cooldown_days' => 7,                 // dopo dead-link
    ],
    'scraping' => [
        'default_driver' => 'auto',                     // search_provider|browsershot|auto
        'browsershot' => ['cluster_nodes' => []],
    ],
    'marketplaces' => [
        'amazon' => ['driver' => 'auto', 'rate_limit_rpm' => 20],
        'ebay' => ['driver' => 'api'],
        // ...
    ],
    'matching' => [
        'confidence_band' => [60, 85],
        'llm' => ['enabled' => true, 'model' => 'gpt-4o-mini'],
        'embeddings' => ['driver' => 'openai', 'cache_ttl' => 2592000],
        'recompute_on_dom_diff_threshold' => 0.3,
    ],
    'storage' => [
        'partitioning' => ['enabled' => true, 'months_ahead' => 3],
        'retention' => ['raw_days' => 90],
        'aggregates' => ['enabled' => true],
    ],
    'resilience' => [
        'adaptive_backoff' => ['enabled' => true, 'max_factor' => 4],
    ],
    'ai' => [
        'visual_match' => ['enabled' => true],
        'content_gap' => ['enabled' => true],
        'forecast' => ['enabled' => true, 'min_observations' => 14, 'show_confidence_interval' => true],
        'anomaly' => ['enabled' => true],
        'narrative' => ['enabled' => true],
        'promo_detection' => ['enabled' => true],
        'assortment' => ['enabled' => true],
    ],
    'review_insight' => ['enabled' => false, 'allowed_domains' => []],
    'repricer' => ['enabled' => false, 'dry_run_only' => true],
    'pii' => ['enabled' => 'auto'],
    'ai_act' => ['enabled' => 'auto', 'disclosure' => ['enabled' => true], 'decision_log' => ['enabled' => true]],
    'compliance' => ['robots' => ['default' => 'respect'], 'audit' => ['enabled' => true]],
    'fx' => ['driver' => 'openexchangerates', 'base' => 'EUR'],
    'webhooks' => ['retry' => 5, 'daily_digest' => false],
];
```

---

## 16. Sizing & deployment

Target enterprise: **500k SKU × 5 paesi × 10–20 competitor × daily + spot hourly**.
- ~25M fetch/day per tenant grande; ~75M righe/mese `price_observations`.
- **DB**: MySQL 8 / MariaDB 11 / PostgreSQL 15+, InnoDB compression, partitioning mensile.
  Tenant enormi → DB-per-tenant.
- **Redis** cluster (cache + queue + rate-limit).
- **Horizon** code separate: `discovery` (low), `scrape` (high), `enrich` (medium),
  `notifications` (high), `ai` (low).
- **Retention** default 90gg raw + `price_daily_aggregates` (materialized, nightly) eterni.
- **Storage immagini**: opzionale S3/R2 (default solo URL ref).

---

## 17. Integration guide (ecommerce host)

```php
// 1. Sync catalogo (bulk)
Http::withToken($key)->post("$base/api/v1/catalog/products:bulk", [
    'products' => [[
        'external_id' => 'SKU-123', 'gtin' => '8001234567890',
        'brand' => 'Acme', 'model' => 'X1', 'name' => 'Acme X1 64GB',
        'categories' => ['Electronics','Phones'], 'our_price_cents' => 19900,
        'currency' => 'EUR', 'base_country' => 'IT',
    ]],
]);

// 2. Crea target di monitoraggio
Http::withToken($key)->post("$base/api/v1/targets", [
    'product_external_id' => 'SKU-123', 'country' => 'IT',
    'locale' => 'it-IT', 'frequency' => 'daily',
    // 'given_urls' => ['https://www.amazon.it/dp/B0...'],  // salta discovery
]);

// 3. Reagisci ai segnali (webhook listener nell'host)
Route::post('/webhooks/price-intel', function (Request $r) {
    abort_unless(WebhookSigner::valid($r), 401);
    match ($r->input('event')) {
        'price.dropped', 'undercut.detected' => MarginOS::reevaluate($r->input('data')),
        default => null,
    };
});
```
Eventi consigliati per dynamic pricing: `price.changed`, `undercut.detected`, `buybox.lost`,
`repricing.suggested` (se si vuole usare l'engine come advisor).

---

## 18. Build phases

0. **Foundations** — scaffolding, ServiceProvider, `composer.json` (→ registra Packagist),
   config, migrations base (tenants/products/targets/sources), Sanctum + ApiKey + EnsureTenantScope.
1. **Search-providers extension** — PR upstream: country/locale, ProductScraperInterface, caching.
2. **Catalog & onboarding** — bulk JSON + CSV + Excel + webhook in + ImportCatalogJob + command.
3. **Discovery & matching** — UrlDiscoveryService, MatchingPipeline + steps, MatchProposal review.
4. **Generic scraper + Browsershot + normalizer** — PriceNormalizer, FX, partitioning.
5. **Marketplace adapters** — Amazon (SP-API+Keepa+scrape), eBay, Google Shopping, Idealo, Trovaprezzi.
6. **Scheduling + adaptive backoff + Horizon tags**.
7. **Alerts + Webhooks + Notifications**.
8. **AI layer** — visual, content-gap, forecast, anomaly, narrative, promo, assortment.
9. **Review sentiment** (GDPR-safe, pii-redactor obbligatorio).
10. **Repricer engine** (feature-flag).
11. **Compliance hardening** — robots, rate-limit, PII filter, AiActBridge, audit retention.
12. **Docs finali** — README, INTEGRATION-GUIDE, EXTENDING, COMPETITIVE-MATRIX.
13. **Release v1.0 alpha** + benchmark.

> Ogni fase = PR brevi (≤800 LOC) con `copilot-pr-review-loop`. Gates: composer validate, PHPUnit/Pest, PHPStan, Pint.

---

## 19. Testing & verification

- **Unit** 80%+: matchers, normalizer, repricer eval, backoff, identifiers (checksum GTIN/CF).
- **Feature**: API endpoints + OpenAPI contract test.
- **Integration**: adapters contro **fixture HTML salvate** (no live HTTP in CI).
- **E2E opt-in**: testsuite separata con API key vere (Firecrawl/Tavily/Brave), skip senza chiavi.
- **Load test**: k6, 100k target × hourly su MySQL+Redis di test, SLO ritardo medio < 5 min.
- **Compliance test**: verifica PII redaction su fixture review, verifica `is_ai_generated` su output AI.

---

## 20. Competitive matrix

| Capability | Netrivals | Competitoor | **questo package** |
|---|:-:|:-:|:-:|
| Price/stock multi-paese | ✅ | ✅ | ✅ |
| AI product matching | ✅ | ⚠️ | ✅ cascata + confidence |
| **Visual matching (vision LLM)** | ❌ | ❌ | ✅ WOW |
| Marketplace dedicati (Amazon buy-box) | ✅ | ⚠️ | ✅ SP-API+Keepa+eBay+GShop+Idealo+Trova |
| Repricing | ✅ | ✅ | ✅ no-code opzionale |
| Assortment intelligence | ✅ | ✅ | ✅ + gap-scoring AI |
| **Promo normalization** | ⚠️ | ⚠️ | ✅ WOW |
| **Price forecasting** | ❌ | ❌ | ✅ WOW |
| **Anomaly detection** | ❌ | ❌ | ✅ WOW |
| **Weekly AI narrative** | ❌ | ❌ | ✅ WOW |
| **Content gap analysis** | ⚠️ | ⚠️ | ✅ WOW |
| **Review sentiment GDPR-safe** | ❌ | ❌ | ✅ WOW |
| **EU AI Act-ready by design** | ❌ | ❌ | ✅ WOW |
| **GDPR PII redaction integrata** | ❌ | ⚠️ | ✅ WOW |
| Open-source self-hostable | ❌ | ❌ | ✅ Apache-2.0 |
| API-first + webhook HMAC | ⚠️ | ✅ | ✅ |

**9 feature WOW** non offerte (integrate) da nessuno dei due.

---

## 21. Roadmap (post-MVP)

- Marketplace extra: Allegro PL, Bol NL, Otto DE, Cdiscount FR.
- B2B price API: Amazon Vendor Central offer mode.
- Browsershot cluster scaling orizzontale gestito.

---

*Documento di progetto del core. Per l'admin panel vedi i repo/docs di
`laravel-ai-price-intelligence-admin` (TEMPLATE.md + IMPLEMENTATION.md).*
