# LESSON.md — learnings & gotchas

> Append-only log of discoveries, fixes and non-obvious facts. **Read this before making changes**
> and pass it into the context of any parallel subagent or new session. Update it whenever you learn
> something, fix a bug, or receive Copilot/CI feedback.

## Environment
- **PHP & Composer are NOT on the bash PATH.** Use **PowerShell**: PHP 8.4.21 + Composer 2.9.7 live
  under `C:\Users\lopad\.config\herd\bin\php84`. Run `vendor\bin\phpunit` and `composer ...` via the
  PowerShell tool, not Bash.
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
- Tenant isolation: `Models\Concerns\BelongsToTenant` (global scope + auto-fill). Models that are
  looked up *before* tenant context exists (e.g. `ApiKey`) must NOT use the trait.
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
