# LESSON.md — learnings & gotchas

> Append-only log of discoveries, fixes and non-obvious facts. **Read this before making changes**
> and pass it into the context of any parallel subagent or new session. Update it whenever you learn
> something, fix a bug, or receive Copilot/CI feedback.

## Environment
- **PHP & Composer are NOT on the bash PATH.** Use **PowerShell**: PHP 8.4.21 + Composer 2.9.7 live
  under `C:\Users\lopad\.config\herd\bin\php84`. Run `vendor\bin\phpunit` and `composer ...` via the
  PowerShell tool, not Bash.
- **PHPStan OOMs on the default 128M parallel-worker limit on this box** (fatal in php-parser while
  scanning vendor). Always run `vendor\bin\phpstan analyse --memory-limit=1G`. CI is unaffected.
- **`vendor\bin\phpunit --filter "A|B"`** fails on PowerShell — the `|` is parsed by the batch
  wrapper ("'B' is not recognized"). Run multiple test files by path instead:
  `vendor\bin\phpunit tests/Feature/A.php tests/Feature/B.php`.
- Project root for this package: `C:\Users\lopad\Documents\DocLore\Visual Basic\Ai\laravel-ai-price-intelligence`.

## Dependencies
- `padosoft/laravel-ai-search-providers` is consumed **from Packagist** (`^1.0 || dev-main`,
  currently v1.2.1). We extend it via `SearchQueryData.metadata` (country/locale) with **no upstream
  code change**, so the published package is enough and CI-portable. (A local path repository was
  used briefly during early dev, then removed so GitHub Actions `composer install` works.)
- `country` / `locale` for geo-aware discovery are passed through **`SearchQueryData.metadata`**
  (keys `country`, `locale`) — no upstream contract change required. Helper:
  `Support\Discovery\GeoSearchQueryFactory`.

## Testing
- **`tests/E2E/` must exist** or phpunit aborts ("Test directory not found"). Keep a `.gitkeep`.
- **Matcher unit tests must extend the package Orchestra `TestCase`**, NOT plain
  `PHPUnit\Framework\TestCase`. Matchers read Eloquent `Product` attributes; instantiating an
  Eloquent model without a booted app throws *"bootIfNotBooted ... while it is being booted"*.
- Models can be instantiated (`new Product([...])`) without hitting the DB as long as the app is
  booted (Orchestra TestCase) — no `RefreshDatabase` needed for pure read-attribute logic.
- SQLite `:memory:` is the test DB (see `tests/TestCase.php`).

## Conventions (mirror padosoft packages)
- `declare(strict_types=1)` everywhere; `final` classes by default.
- Table names resolved from `config('price-intelligence.tables.*')` via `PriceIntelligenceModel`.
- Migrations are **idempotent**: guard with `if (Schema::hasTable($table)) return;`.
- Tenant isolation: `Models\Concerns\BelongsToTenant` (global scope + auto-fill). The scope is a
  no-op when no tenant is set, so models looked up *before* tenant context exists (e.g. `ApiKey` by
  hash) can still use the trait — just call `->withoutGlobalScope('pi_tenant')` on those auth lookups.
- DTOs are readonly `final` classes with `fromArray()` / `toArray()` (mirrors `SearchQueryData`).

## Domain notes
- GTIN equality compares **GTIN-14 left-padded** forms, so UPC-A 12 and EAN-13 of the same product
  match. Use `GtinValidator::equals()`.
- Matching cascade short-circuits at confidence ≥ 95 (GTIN=100, MPN+brand=95). Near-identical
  product titles legitimately score ≥ 85 → **confirmed** (this is correct, not a bug).
- Confidence band default `[60, 85]`: ≥85 confirmed, 60–85 suggested (review queue), <60 rejected.

## Scraping / extraction
- HTML extraction uses **regex + json_decode** for JSON-LD (handles `@graph` and bare arrays) plus
  meta-tag scan for OpenGraph — no DOMDocument needed, keeps it pure & fixture-testable.
- `GenericHttpScraper` uses the **Http facade** so tests use `Http::fake([...])`. It returns
  `ProductSnapshot::unreachable($url)` on non-2xx / exceptions instead of throwing — a momentarily
  down site must not kill the job or invalidate the URL.
- `PriceParser` infers EU vs US decimal/thousand conventions from separator positions.
- FX: `FixedFxProvider` rates are expressed against the base; `rate(from,to)=rTo/rFrom`.

## Testing gotchas (self-found)
- **Enum-cast attributes**: `Model::pluck('castedEnumColumn')` returns **enum instances**, not the
  backing string. Assert with `->map(fn($m)=>$m->type->value)` or compare against the enum case.
- **`Http::fake([...])` replaces** any previous fake. Don't call it twice (sequence then webhook) —
  the second wipes the first. Use a single `Http::fake([...])` map or a closure branching on
  `$request->url()`.
- A hooked security check blocks writing a PHP method literally named after the JS code-evaluation
  builtin — name test helpers `evaluator()` etc. instead.

## Local Copilot CLI review (run BEFORE pushing) — WORKING invocation
- Command: `copilot --autopilot --yolo -p "<prompt>"` (non-interactive; `--yolo` allows tools/paths
  so it can run git + read files without prompts).
- In the prompt, **scope it to the PR/branch diff**, not just a file list, and trigger the precise
  review skill with **`/review`**. Example prompt:
  `/review the changes on this branch vs origin/main (git diff origin/main...HEAD). Report concrete
   actionable bugs / Laravel best-practice issues / edge cases only as a short bullet list; reply
   'NO ISSUES' if none.`
- It genuinely finds bugs the test suite misses (e.g. on phase 8 it caught that
  `StatisticalForecaster::forecast()` didn't validate `horizonDays > 0` → NAN in the CI formula +
  mislabeled persisted horizon). Run it, fix findings, re-run until 'NO ISSUES', THEN push.
- It is a Premium request and can take several minutes; that's expected.

## Requesting GitHub Copilot review (WORKING method)
- `gh pr edit <PR> --add-reviewer copilot` → **fails** ("Could not resolve user 'copilot'").
- GraphQL `requestReviews(userLogins:...)` → **fails** (input doesn't accept `userLogins`).
- ✅ **REST works**: `gh api --method POST repos/<owner>/<repo>/pulls/<PR>/requested_reviewers
  -f "reviewers[]=copilot-pull-request-reviewer[bot]"`. The response's `requested_reviewers` then
  contains the `Copilot` bot. Use this in the copilot-pr-review-loop.
- After requesting, poll `gh pr view <PR> --json reviews,comments` and the issue timeline for
  `copilot_work_started`; wait for the review, then address every actionable comment.

## Git / repo
- `.gitignore` must exclude `vendor/`, `.phpunit.cache/`, `composer.lock` (this is a library, so the
  lock file is intentionally not committed). Stage with care; verify no `vendor/` paths are staged
  before committing.
- Branch `feat/core-foundation` → PR #1 (bootstrap). Subsequent phases should be smaller PRs.

## Phase 8 AI layer — local Copilot /review findings (all fixed before push)
- Forecaster must reject `horizonDays < 1` (else NAN in the CI sqrt + mislabeled horizon).
- Statistical inputs: **filter non-numeric/null** points, don't `intval()`-coerce to 0 (skews trend,
  false anomalies). Enforce a hard floor of 2 observations so a misconfigured `min_observations`
  (0/neg) can't `DivisionByZeroError`.
- Anomaly outliers must be measured on **detrended residuals** (linear fit), not raw p5/p95, or a
  normal trend continuation is falsely flagged. Floor residual-std at 0.5% of predicted so a
  perfectly linear history still flags genuine breaks.
- Honor feature toggles in the ServiceProvider with **null-object drivers** (NullForecaster/
  NullAnomalyDetector) — binding the real driver unconditionally makes `ai.*.enabled` inert.
- `AiDecisionLogger` must honor BOTH the global `ai_act.enabled` and the `decision_log.enabled`
  sub-toggle, and persist `model_version` (don't leave the schema column orphaned).
- Don't keep config keys the code ignores (removed `ai.forecast.driver`): a "dead setting" is a
  review smell. Driver selection is via the interface binding.
- The local `copilot /review` even runs PHP snippets to verify behavior — treat its findings as
  high-signal, but still verify: it raised one **false positive** (claimed CI assertions were
  reversed when they were correct & green) — push back with reasoning, don't blindly "fix".

## Copilot / CI feedback log
- PR #1 (2026-05-23): review bot flagged a **P1** — `AdaptiveBackoff::class` in the ServiceProvider
  resolved to the wrong namespace (`Padosoft\PriceIntelligence\AdaptiveBackoff`) because the
  `use ...\Services\Scheduling\AdaptiveBackoff;` import was missing. Effect: the container binding
  registered under the wrong abstract, so `TargetScheduler` got default `enabled/max_factor` instead
  of config values. **Lesson**: when binding `Foo::class` in a ServiceProvider, always confirm the
  short class name is imported, or use the fully-qualified name — otherwise it silently resolves to
  the provider's own namespace. Fixed by adding the import + regression test `BackoffBindingTest`.
- PR #1 (2026-05-23) — Copilot full review, batch of valid findings, all fixed:
  - `WebhookDispatcher` didn't scope by tenant → now `withoutTenantScope()->where('tenant_id',$id)`.
  - `WebhookSubscription` could leak `secret_encrypted` in JSON → added `$hidden`.
  - `HtmlProductExtractor` treated `PriceParser::parse()` as array when it can be null (would warn
    under `failOnWarning`) → null-guarded; also reuse `PriceParser` for JSON-LD prices.
  - `BelongsToTenant` skipped auto-fill in database mode while `tenant_id` is non-null → now always
    auto-fills, only the global scope is gated by mode.
  - `price_eur_cents` renamed to **`price_base_cents`** (base currency is configurable, not always EUR).
  - `FetchLog.status` was hardcoded → now records the real HTTP status (`ProductSnapshot::$httpStatus`).
  - Controllers passed `Illuminate\Support\Stringable` into `where()` → `->toString()`.
  - 204 responses → `response()->noContent()`.
  - Doc/comment fixes (MpnNormalizer, PriceParser).
  **Meta-lesson**: the review caught ~15 real issues the passing test suite did not. The strict
  per-phase Copilot loop is worth it.
- **Admin panel API endpoints (2026-05-24)**: `ApiKey` **uses `BelongsToTenant`** so management
  queries (`ApiKeyController::index()`/`::revoke()`) are tenant-isolated automatically. Because the
  `pi_tenant` global scope is a no-op while no tenant is set, and authentication is inherently
  cross-tenant (the tenant is resolved *from* the key), the hash lookups in `ResolveTenant` and
  `TenantController::me()` call `->withoutGlobalScope('pi_tenant')` explicitly — otherwise a leftover
  tenant context (shared worker / single test process) would scope the lookup and reject another
  tenant's valid key. Lesson: prefer the global scope for isolation; bypass it only on auth lookups.

## B1 — LLM provider layer (laravel/ai + laravel-ai-regolo, core v1.3.0)
- **`laravel/ai` constraint `"^0.6.8 || ^0.7"`** resolves to v0.6.8 in the dev environment.
  Note: `padosoft/laravel-ai-regolo` is **`suggest`-only** (not required), so it does NOT drive this
  package's resolution — but a host that installs Regolo v1.0.0 (which pins the v0.6.8 embedding
  contract) will hold laravel/ai at 0.6.x, so keep the lower bound at 0.6.8. `laravel/ai` pulls
  **aws/aws-sdk-php** transitively (Bedrock) — already present for B2's Amazon SP-API.
- **Verified v0.6.8 API surface before coding** (don't trust the v0.7 blog/docs): the `agent()`
  helper is in `vendor/laravel/ai/functions.php` → `Laravel\Ai\agent(instructions, messages, tools,
  schema)`; `Promptable::prompt(string $prompt, array $attachments = [], Lab|array|string|null
  $provider = null, ?string $model = null, ?int $timeout = null)` — **provider accepts a plain string
  config-key** (so `'regolo'` works without the Lab enum). `Embeddings::for([$t])->dimensions($n)
  ->generate($provider, $model)` returns `EmbeddingsResponse` with a public `->embeddings` (float[][]).
  `AgentResponse->text` + `->__toString()` + `->usage->{promptTokens,completionTokens}` (non-null ints).
  `Files\Image::fromUrl($url)`. Lesson: grep the installed vendor source for signatures, version drift is real.
- **Seam pattern keeps CI fully offline**: `AgentRunner`/`EmbeddingRunner` interfaces wrap the only
  two laravel/ai call sites. `LaravelAiLlmProvider`/`LaravelAiEmbeddingProvider` are unit-tested with
  stub runners (no Http::fake even needed); the real `LaravelAi*Runner` adapters are thin (no logic to
  test) and exercised only by the opt-in `tests/Live` suite (`PI_LIVE_LLM=1`).
- **`tests/Live` is auto-excluded from CI** by simply not adding it to any `<testsuite>` in
  phpunit.xml.dist (the no-arg `phpunit` runs only the named suites Unit/Feature/E2E). The
  `markTestSkipped` guard is the second safety; run it on demand via `phpunit tests/Live`.
- **One impl per feature, fake = the fallback**: feature services depend only on
  `LlmProviderInterface`; the "statistical/fake fallback when no provider is configured" is the
  `FakeLlmProvider` being the default binding (returns feature-shaped JSON keyed by `options['feature']`).
  Avoids a Fake+Real class pair per feature.
- **`completeJson()` strips a ```json fence** before `json_decode` (models often wrap JSON) and throws
  `RuntimeException` on undecodable output so callers can fall back deterministically.
- **Borderline-only LLM judge**: added an empty marker interface `BorderlineOnlyStep`; `MatchingPipeline`
  runs such a step only when the running best confidence is in the configured suggested band
  `[low, high)` (default [60,85)) — the admin-review zone, consistent with `decide()`. The judge
  returns MAX-merged confidence, so a fake judge returning 0 never lowers a real score. The judge also
  **zeroes confidence when the model verdict is `same_product=false`** (high certainty-they-differ must
  not read as a high *match*). Only `BorderlineOnlyStep` exceptions are swallowed (reported); deterministic
  steps propagate so real bugs surface.
- **Dead-config discipline (continuing the Phase-8 lesson)**: routing all LLM features through the
  single `ai.llm.driver` made `ai.narrative.driver`, `ai.promo_detection.driver`, `matching.visual`,
  and `matching.llm.model` dead — removed them rather than leave a config the code ignores.
- **`$product->attributes` from OUTSIDE the model** triggers `__get` → the cast `attributes` column
  (Eloquent's protected `$attributes` is inaccessible from outside scope), consistent with
  `ProductResource`. Inside a model method `$this->attributes` would be the raw array — don't read it there.
- **PHPStan parallel worker crashed once on Windows** ("severe error … while running parallel worker")
  but `--no-progress` (effectively single-pass) reported `No errors`. Transient Windows worker flake,
  not a real error; re-run before trusting a worker crash.

## B1 review findings (2026-05-25) — fixed before push
- **Per-feature LLM toggles were dead config**: `ai.visual_match.enabled`, `ai.content_gap.enabled`,
  `ai.narrative.enabled`, `ai.promo_detection.enabled` existed in the published config and were
  surfaced in `TenantController` capabilities map, but the ServiceProvider never checked them —
  setting any to `false` had zero effect (live LLM still called). Fixed by adding null-object drivers
  (`NullNarrativeWriter`, `NullContentGapAnalyzer`, `NullPromoDetector`, `NullVisualMatcher`) and
  guarding each binding with `Flag::enabled()`, mirroring the `NullForecaster`/`NullAnomalyDetector`
  pattern. **Lesson**: every config toggle that callers can set must have a matching guard in the
  ServiceProvider binding; otherwise the toggle silently does nothing.
- **`MatchingPipeline` did not catch `RuntimeException` from steps**: `LlmJudgeMatcher::score()`
  (and any future LLM step) throws `RuntimeException` on a malformed LLM response. The pipeline had
  no try/catch, so one bad LLM output would propagate to the job and exhaust all retries. Fixed by
  wrapping `$step->score()` in a try/catch that `continue`s to the next step, treating the exception
  as a zero-confidence result. **Lesson**: any step that may throw (LLM, network) needs a graceful
  fallback inside the pipeline loop, not just at the job level.

### B1 PR #13 — GitHub Copilot + Codex review findings (all fixed before merge)
- **LLM judge must gate confidence on the verdict**: a `{same_product:false, confidence:95}` reply
  means "95% sure they DIFFER" — returning 95 as MatchScore confidence would wrongly promote it to
  `confirmed`. Zero the confidence when `same_product` is false (both reviewers flagged, Codex P1).
- **Use `Flag::enabled()` not `(bool) config()` for env-driven toggles**: `(bool) 'false'` is `true`,
  so a host disabling `matching.llm.enabled`/`embeddings.enabled` via env would still get paid LLM/
  embedding steps. `Support\Config\Flag::enabled()` parses string booleans correctly — already used
  for the `ai.*` toggles; apply it consistently.
- **Scope exception swallowing to the flaky step only**: catching `RuntimeException` around *every*
  pipeline step hides real bugs in deterministic matchers. Wrap only `BorderlineOnlyStep` steps in
  try/catch+report(); let deterministic steps throw.
- **Band-consistent borderline gate**: `isUncertain()` should reuse the configured `[low, high)`
  (the "suggested"/admin-review band) rather than a magic `high-45`, so a host changing the band
  doesn't silently change which candidates hit the judge.
- **Opt-in providers belong in `suggest`, not `require`**: `laravel-ai-regolo` (and its transitive
  aws-sdk-php via laravel/ai) shouldn't be forced on every consumer. `laravel/ai` is the hard dep;
  Regolo is documented + suggested, installed only when `PI_LLM_PROVIDER=regolo`.

## B2 — Marketplace API adapters (core v1.4.0)
- **API-or-scrape via `AbstractApiAdapter`**: API adapters extend the scrape adapter and override
  `fetch()` to try `apiFetch()` first, falling back to `parent::fetch()` (scrape) when driver=`scrape`,
  creds are missing, or the API throws/returns an unreachable snapshot. `apiFetch` exceptions are
  caught + `report()`ed (guarded by `function_exists`) — never fail the cascade. This mirrors B1's
  "fake is the fallback" philosophy: zero-config still works (scrape), credentials light up the API.
- **No AWS SigV4 needed for SP-API anymore** — Amazon **deprecated the IAM/AWS SigV4 requirement in
  2023** ("Removing IAM/AWS SigV4 from the SP-API authentication model"). Modern SP-API authenticates
  with just the LWA access token (`x-amz-access-token` header) obtained from the refresh-token grant
  at `api.amazon.com/auth/o2/token`. So the whole client is plain `Http` facade → `Http::fake`-testable,
  no `aws/aws-sdk-php` SigV4 dance. (aws-sdk is present transitively via laravel/ai but unused here.)
  Both Copilot and Codex flagged "SP-API requires SigV4" from older training data — **pushed back**
  with the 2023 deprecation citation rather than adding dead signing code. Reviewer knowledge can lag
  vendor changes; verify against current docs before churning.
- **Every client uses the `Http` facade and returns a uniform `ApiProductResult`** (→ `toSnapshot()`),
  so each is fixture-tested with `Http::fake([...])` + `Http::assertSent()` for headers/query, with no
  extra seam. Clients return `null` when their key/token is absent (the adapter then scrapes).
- **Keepa prices are already integer cents** (e.g. `5499` = €54.99); `-1` = unavailable. `stats.current[0]`
  is the Amazon price. Reused `Support\Pricing\PriceParser::parse()` (returns `{cents, currency}`) for
  SERP/Farfetch major-unit price strings to stay consistent with EU formatting.
- **Farfetch** added as `AdapterCode::Farfetch` (first-class); `scrape` default already works via the
  JSON-LD `HtmlProductExtractor`. Commercial `retailed`/`apify` drivers are opt-in (key/token) and a
  judgment call kept them out of the hard deps — config-only.
- **No new hard composer deps** for B2 — all marketplace APIs are plain REST. Credentials are env/config.

## B2 local /review findings (2026-05-25) — all fixed before push
- **`auto` driver `??` cascade doesn't fall through on zero-offer SP-API result**: `AmazonSpApiClient::fetchOffers()`
  returns a non-null `ApiProductResult(priceCents: null)` when the API is reachable but the product has no listed
  offers. The original `spApi->fetchOffers() ?? keepa->fetchByAsin()` only falls through on `null`, so Keepa was
  never tried in that scenario. Fixed by extracting an `autoFetch()` method that explicitly checks `priceCents !== null`
  before accepting the SP-API result. **Lesson**: when cascading two API clients with `??`, ensure the first can't
  return a non-null "empty" result that silently suppresses the fallback.
- **No OAuth token caching in `AmazonSpApiClient` / `EbayBrowseClient`**: every fetch made a fresh LWA/eBay CC
  token grant (2 HTTP calls per product). LWA has a 15 req/s rate limit; eBay CC tokens live 7200 s and can be
  shared. Fixed by using `Cache::get` + `Cache::put` (only caching on success, slightly under TTL: 3500 s / 7000 s).
  Cache key includes client-id + endpoint hash to support multiple tenants. **Lesson**: OAuth CC/refresh tokens are
  reusable — always cache them, and only on success so a transient failure doesn't poison the cache.
- **`FarfetchClient::map()` missed `'SOLD_OUT'` availability variant**: used `in_array` with a hardcoded list of
  mixed-case strings; adding new variants would require updating the list in two places. Fixed by normalising with
  `strtolower()` and comparing only lowercase tokens `['out_of_stock', 'sold_out']`. **Lesson**: normalise availability
  strings to lowercase before comparing — API providers are inconsistent with casing.
- **`ApiProductResult->externalRef` populated but never consumed**: clients stored the API-canonical ref (e.g., Keepa's
  `$product['asin']`) in `externalRef`, but `AbstractApiAdapter` only used `$this->externalRef(url)` (URL regex) when
  updating `external_ref` on the model. For non-standard URLs where the regex fails, the API-returned ref was silently
  dropped. Fixed by adding `externalRef` to `ProductSnapshot` (propagated via `ApiProductResult::toSnapshot()`) and
  updating `AbstractApiAdapter` to use `$this->externalRef(url) ?? $snapshot->externalRef`. **Lesson**: when an API
  returns a canonical identifier, propagate it all the way to the persistence layer rather than discarding it after
  the DTO boundary.

## B3 — API gaps + enterprise scale (core v1.5.0)
- **Dynamic `selectRaw` aliases trip PHPStan** when the query returns Eloquent models: `$row->min_p`
  on a `PriceObservation`/`CompetitorProduct` is "access to undefined property". Use
  `$row->getAttribute('min_p')` (keeps the model's global tenant scope, unlike `->toBase()` which
  strips Eloquent scopes — important for tenant-scoped facets). Type the closure param
  (`fn (PriceObservation $row) => …`) so PHPStan is happy.
- **`updateOrCreate` + a `date`-cast column silently re-inserts**: the cast stores `'Y-m-d 00:00:00'`
  but the WHERE binding for the lookup is the raw `'Y-m-d'` string (casts don't apply to where
  bindings) → no match → UNIQUE violation on re-run. Fix: store `day` as a **plain `Y-m-d` string**
  (drop the `date` cast) so write/read/lookup all use the same value. Idempotency test caught it.
- **PHPStan flags nullsafe on non-nullable**: `$o->captured_at?->toIso8601String()` where the model
  `@property Carbon $captured_at` is non-nullable → `nullsafe.neverNull`. Use `->`.
- **OOM-safe export without a queue**: `response()->streamDownload()` over an Eloquent `cursor()`
  (wrapped in a generator) streams 100k+ rows without materializing them — simpler than a queued
  job + temp file + polling, and satisfies the enterprise acceptance criterion. CSV via `league/csv`
  `Writer::createFromStream(fopen('php://output','w'))`. Promoted league/csv to `require` (runtime).
- **A private test helper named `seed()` fatals** — it collides with Testbench's public `seed()`.
  Name domain helpers `seedX()`.
- **Daily-aggregate command is scale-safe** because the reduction is in SQL (`GROUP BY tenant,
  competitor_product, currency`), so the result set is distinct groups, not raw rows. Runs globally
  in console (tenant global scope is a no-op when no tenant is set) and groups by tenant_id.

## B3 local /review findings (2026-05-25) — all fixed
- **Daily-aggregate unique key must include `currency`**: the original key was `(competitor_product_id, day)`.
  When one competitor product has observations in ≥ 2 currencies on the same day, the second `updateOrCreate`
  matches the first row and overwrites its currency/prices — silent data loss. Fix: extend the unique key to
  `(competitor_product_id, day, currency)` and add `currency` to the `updateOrCreate` match criteria.
- **`to` date filter off-by-one** across all date-filtered endpoints (`ObservationController`,
  `ExportController`, `AiDecisionController`): `$request->date('to')` on a date-only string resolves to
  midnight (`00:00:00`), so `WHERE captured_at <= '2024-01-31 00:00:00'` excludes records from the full
  target day. Fix: use a half-open range `>= startOfDay / < addDay()->startOfDay()` for both `from` and `to`.
- **`whereDate()` defeats the new B3 composite index**: `whereDate('captured_at', $day)` emits
  `DATE(captured_at) = ?` which wraps the column in a function, preventing MySQL from using the
  `(competitor_product_id, captured_at)` index added in B3. Fix: use an explicit range
  (`>= $dayStart / < $dayEnd`) so the index is available for the nightly aggregate command.
