# B-phases — Design spec (core completion + admin enterprise hardening)

> Umbrella roadmap spanning **both** packages: `padosoft/laravel-ai-price-intelligence` (core) and
> `padosoft/laravel-ai-price-intelligence-admin`. Goal: take the product from "feature-complete per
> the A-plan" to **professional & complete in every part** — real LLM/marketplace integrations,
> enterprise 500k-SKU scale, the remaining core API gaps, real test infrastructure, and release
> hygiene. Every gap found in the core is **implemented and released in the core**, never stubbed.

- Date: 2026-05-25 · Owner: Lorenzo Padovani · License: Apache-2.0
- Prereq state: core at **v1.2.0**, admin at **v1.0.0** (A0–A8 complete, 19 screens live).

---

## 1. Context & audit (what's real vs stub today)

A read-only audit of the core established the baseline:

| Area | Verdict | Notes |
|---|---|---|
| Cursor pagination + indexes | REAL | `cursorPaginate` everywhere, per-page caps, indexes on `(competitor_product_id, captured_at)` etc. |
| Queue jobs (scrape/discovery) | REAL | `ShouldQueue`, 5 named lanes; **no chunk/batch** for bulk |
| Statistical forecaster / anomaly detector | REAL | OLS + CI; z-score/IQR. Complete by design |
| Review sentiment | REAL | Lexicon-based, GDPR-safe (no LLM) |
| Generic HTTP scraper + marketplace URL/extraction | REAL | `GenericHttpScraper`; Amazon/eBay/Idealo/Trovaprezzi/Google URL parsing |
| `ai_decision_logs` table + writer | REAL (model/write) | **No REST endpoint** to read it |
| Config + feature toggles | REAL | 19 top-level keys, honored in bindings |
| **LLM features** (narrative, content-gap, promo, visual, LLM-judge, embeddings) | **STUB** | `driver: 'fake'`, fake embeddings — **no real LLM call wired** |
| **Marketplace API drivers** (Amazon SP-API/Keepa, eBay Finding, Google SERP) | **STUB** | switches point nowhere; only the scraper is real |
| `/observations/prices` host filter | **MISSING** | filters by `competitor_product_id`/`from`/`to` only |
| Stock & promo **history** endpoints | **MISSING** | only latest snapshot via `/competitor-products/{id}` |
| AI-decision-log REST endpoint | **MISSING** | logged but not queryable |
| Bulk CSV/Excel **export** | **MISSING** | only import exists |
| Facet/aggregation (host/category counts) | **MISSING** | dashboard returns global counts only |
| Tenant **settings write** endpoint | **MISSING** | settings are read-only |

## 2. Key decisions

- **LLM layer** (B1): use the **official Laravel AI SDK** as the provider abstraction (provider chosen
  via env/config) and register **`padosoft/laravel-ai-regolo`** so **Regolo** (EU/Italian-safe) is
  selectable alongside OpenAI/Anthropic. Real LLM-backed drivers behind the existing interfaces;
  `fake`/statistical stays the **zero-config default**. (Exact official-SDK package name + API verified
  against its docs during writing-plans/implementation.)
- **Testing strategy** (Option 2): build the real integrations **fully now**; cover request-build /
  response-parse with `Http::fake` + recorded fixtures in CI; add an **opt-in live smoke suite** that
  runs only when real keys/credentials are present (skipped in CI). The true live test is run later by
  the owner with real keys.
- **Admin test infra** (Full): a test Laravel app mounts the core + a real DB (migrate + seed realistic
  data), exposes the live API; a Playwright **integration** suite runs against it (alongside the fast
  mock suite) + **visual-regression** baselines on key screens.
- **Farfetch** is a first-class luxury marketplace adapter (B2) — pluggable `driver`: `scrape` (default,
  JSON-LD + internal JSON API, fixture-tested) and opt-in `retailed`/`apify` commercial APIs.
  (`far-fetch`/farfetch.js.org is a generic Fetch wrapper — **not** related; discarded.)
- **Core-first, no stubs.** If any later (admin) phase needs a missing core API/option, work stops on
  the admin, the core feature is implemented + released, then the admin consumes it.

## 3. Phase decomposition

Each phase = one PR + the strict loop (local-Copilot → CI → GitHub-Copilot until zero actionable →
auto-merge). `docs/PROGRESS.md` + `docs/LESSON.md` updated per repo.

### CORE

**B1 — LLM provider layer → release core v1.3.0**
- Add the official Laravel AI SDK dependency + `padosoft/laravel-ai-regolo`; a `LlmProvider` contract
  with drivers: `openai`, `anthropic`, `regolo`, `fake` (default). Config `ai.llm.driver` + per-feature
  model.
- Real implementations behind existing interfaces: `NarrativeWriter`, `ContentGapAnalyzer`,
  `PromoDetector`, `VisualMatcher` (vision), the matching **LLM-judge** step, and a real
  `EmbeddingProvider` (replacing the fake hash) — each falling back to fake/statistical when no provider
  is configured.
- Every LLM call writes an `ai_decision_logs` row (model, input hash, output, confidence, cost).
- Tests: `Http::fake` + recorded fixtures per provider; opt-in live suite gated on env keys.
- Acceptance: with a provider configured, each AI feature produces a real model-backed result; with none,
  the fake/statistical default still works; CI green with no live calls.

**B2 — Marketplace API adapters → release core v1.4.0**
- Real drivers behind `MarketplaceAdapterInterface`, config-selectable, generic scraper as fallback:
  Amazon **SP-API** (+**Keepa** for history), **eBay Finding/Browse**, **Google Shopping SERP** (via the
  search-providers), and **Farfetch** (`scrape` default + `retailed`/`apify` opt-in).
- OAuth/credential handling per provider; rate-limit + robots policy respected.
- Tests: fixture-based request/parse; opt-in live suite.
- Acceptance: each adapter maps a real (fixtured) response to the normalized `ProductSnapshot`; selection
  is config-driven; missing creds → graceful fallback to scrape.

**B3 — API gaps + enterprise scale → release core v1.5.0**
- `GET /observations/prices` **host filter** (join/whereHas source).
- `GET /observations/stock` & `GET /observations/promos` — time-series history (cursor, filters).
- `GET /ai-decisions` — paginated AI-decision log (apikeys/admin scope) for the Compliance screen.
- **Facet** endpoint(s): host & category counts via DB-level `groupBy` (not page-1).
- Bulk **export**: CSV/Excel for catalog + observations (queued for large sets, streamed download).
- Tenant **settings write** endpoint (`PATCH /tenants/me/settings` or similar) with validation.
- Enterprise scale: **chunk/batch** in jobs for large catalogs; daily-aggregate materialization;
  index review on hot tables; confirm cursor pagination + caps everywhere.
- Acceptance: new endpoints documented + tested; export works for 100k+ rows without OOM (queued);
  facets computed in SQL; settings round-trip.

### ADMIN (bump core dep to v1.5)

**B4 — Real test harness (Laravel+DB) + visual regression**
- Testbench/Sail-based test app mounting the core + DB, migrate + realistic seed, real API.
- Playwright **integration** project against it (alongside the mock project); **visual-regression**
  baselines (`toHaveScreenshot`) on key screens; CI wiring.
- Acceptance: integration suite green against real data; baselines committed; both suites in CI.

**B5 — Wire placeholder actions + forms**
- New target / SKU / repricing rule (Repricer editor) / webhook subscription; Import CSV; Add-by-URL;
  Trigger discovery; Export CSV/PDF/digest (consumes B3 export); Compliance risk-register/attestation;
  **Settings write** (consumes B3). Forms validated, optimistic + rollback, integration-tested.
- Acceptance: no dead buttons remain; each action hits a real endpoint; covered by integration tests.

**B6 — Enterprise UX (500k SKU)**
- Infinite-scroll / cursor pagination on every list; table **virtualization** (`@tanstack/react-virtual`);
  Competitors **host-count chips** from the B3 facet endpoint; **AI-decision-log viewer** in Compliance.
- Acceptance: long lists render smoothly (virtualized), pagination fetches next cursor, facets exact.

**B7 — SSE bearer/polling fallback**
- Implement the **polling fallback** (interval refetch of `['alerts']`) when SSE is unavailable
  (bearer/headless or no EventSource), so "live" degrades gracefully; keep cookie-mode SSE as primary.
- Acceptance: bearer mode shows periodic refresh; cookie mode stays live; both tested.

**B8 — Release hygiene**
- Admin **CHANGELOG.md**; deploy guide + user/admin guide; consolidate B-phase lessons into
  AGENTS.md/.claude/rules/skills; tag admin **v1.1.0** + release.

## 4. Cross-cutting standards
- One PR per phase; strict local-Copilot → CI → GitHub-Copilot loop until zero actionable comments;
  push back with evidence when a reviewer finding is wrong.
- `docs/PROGRESS.md` (resume state) + `docs/LESSON.md` (learnings) per repo, updated each step.
- Core releases (v1.3 → v1.5) tagged with CHANGELOG entries; admin bumps the core dependency before B5.
- PowerShell for php/composer/vendor; `--memory-limit=1G` for PHPStan; tests-by-path (no `--filter "A|B"`).

## 5. Risks & mitigations
- **External creds (LLM, SP-API, eBay, Keepa, Farfetch APIs)** — built fully + fixture-tested; live
  validation deferred to the owner's opt-in suite. Graceful fallback to scrape/fake when absent.
- **Farfetch scrape fragility / anti-bot** — default scrape driver is best-effort; commercial drivers
  (retailed/apify) are the robust opt-in path.
- **500k-SKU performance** — DB-level facets/aggregates, cursor pagination, chunked jobs, virtualization;
  validate with a seeded large dataset in the integration harness.
- **Scope** — 8 phases, each independently shippable; core fully released before the admin consumes it.

## 6. Out of scope (future roadmap, per owner)
- Remaining axe items (heading-order h1→h3 sweep), deeper usability, and **full screen-level i18n**
  (today i18n covers the shell) are deferred to a later roadmap.
