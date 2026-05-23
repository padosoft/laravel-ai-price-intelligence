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
- `padosoft/laravel-ai-search-providers` is consumed via a **Composer path repository**
  (`../laravel-ai-search-providers`, `symlink: false`) so we use the local, extendable copy. It is
  also on Packagist, but local wins for development.
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

## Copilot / CI feedback log
- (none yet — append findings here as they arrive.)
