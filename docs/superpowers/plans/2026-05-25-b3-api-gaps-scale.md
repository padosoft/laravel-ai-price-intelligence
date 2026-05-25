# B3 — API gaps + enterprise scale Implementation Plan (core → v1.5.0)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans. Steps use `- [ ]` checkboxes.

**Goal:** Close the remaining REST API gaps the admin needs and add enterprise-scale primitives: `/observations/prices` host filter, stock/promo history endpoints, `GET /ai-decisions`, host/category facet endpoints, streamed bulk CSV/Excel export, tenant settings-write, plus a daily-aggregate materialization table + nightly chunked command and a hot-table index review.

**Architecture:** Reuse the existing thin-controller + `cursorPaginate` + `Http::fake`-free model tests pattern. New read endpoints filter by `competitor_product_id`/`from`/`to`/`host` (host via `whereHas('competitorProduct.source', …)`). Exports stream with `response()->streamDownload()` over an Eloquent `cursor()` (OOM-safe for 100k+ rows); Excel is opt-in via `phpoffice/phpspreadsheet` when installed, else CSV. Facets compute in SQL with `groupBy`. A `price_daily_aggregates` table is materialized nightly by a `chunkById` command. Auth uses the existing `ResolveTenant` middleware + `X-Api-Key` scopes.

**Tech Stack:** PHP 8.3, Laravel 11/12/13, `league/csv` (promoted to `require` — needed for export), Orchestra Testbench, PHPUnit. Excel optional via `phpoffice/phpspreadsheet` (`suggest`).

**Conventions:** PowerShell `vendor\bin\*`; tests by path; `final` + `declare(strict_types=1)`; one PR (`feat/phase-b3-api-gaps-scale`); strict local-Copilot → CI → GitHub-Copilot loop; restore line-ending-only churn on untouched files; commit per task.

---

## File Structure

**New controllers/resources:**
- `src/Http/Controllers/Api/V1/AiDecisionController.php` — `GET /ai-decisions`.
- `src/Http/Controllers/Api/V1/FacetController.php` — `GET /facets/hosts`, `GET /facets/categories`.
- `src/Http/Controllers/Api/V1/ExportController.php` — `GET /catalog/products:export`, `GET /observations/prices:export`.
- `src/Services/Export/CsvStreamWriter.php` — shared streamed-CSV helper (cursor → echo rows).

**New scale primitives:**
- `database/migrations/2026_05_25_100015_create_pi_price_daily_aggregates.php` + index-review migration `..._100016_add_b3_indexes.php`.
- `src/Models/PriceDailyAggregate.php`.
- `src/Console/Commands/MaterializeDailyAggregatesCommand.php` (`piprice:aggregates:daily`, scheduled nightly).

**Modified:**
- `src/Http/Controllers/Api/V1/ObservationController.php` — add `host` filter to `prices()`; add `stock()` + `promos()` history methods.
- `src/Models/PriceObservation.php`, `StockObservation.php`, `PromoObservation.php` — add `competitorProduct()` belongsTo (for host filter).
- `src/Http/Controllers/Api/V1/TenantController.php` — expose `settings` in `me()`; add `updateSettings()`.
- `routes/api.php` — register the new routes.
- `src/PriceIntelligenceServiceProvider.php` — register the new command + nightly schedule.
- `composer.json` — move `league/csv` to `require`.
- `docs/PROGRESS.md`, `docs/LESSON.md`, `CHANGELOG.md`, `README.md`.

**Tests (new):**
- `tests/Feature/ObservationHistoryTest.php` (host filter + stock + promos)
- `tests/Feature/AiDecisionApiTest.php`
- `tests/Feature/FacetApiTest.php`
- `tests/Feature/ExportApiTest.php`
- `tests/Feature/TenantSettingsTest.php`
- `tests/Feature/DailyAggregatesTest.php`

---

## Task 1: Observation `competitorProduct` relationships

**Files:** Modify `src/Models/PriceObservation.php`, `StockObservation.php`, `PromoObservation.php`

- [ ] **Step 1:** Add to each model (mirrors `CompetitorProduct::source()` style):
```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

public function competitorProduct(): BelongsTo
{
    return $this->belongsTo(CompetitorProduct::class, 'competitor_product_id');
}
```
(import `Padosoft\PriceIntelligence\Models\CompetitorProduct` — same namespace, so no `use` needed.)

- [ ] **Step 2:** `vendor\bin\phpstan analyse src/Models --memory-limit=1G` → no errors. Commit `feat(b3): add competitorProduct relation to observation models`.

---

## Task 2: Host filter + stock/promo history endpoints

**Files:** Modify `src/Http/Controllers/Api/V1/ObservationController.php`; add routes; Test `tests/Feature/ObservationHistoryTest.php`

- [ ] **Step 1: Write the failing test** (auth bootstrap copied from `ObservationApiTest`):
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\CompetitorSource;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\PriceObservation;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\PromoObservation;
use Padosoft\PriceIntelligence\Models\StockObservation;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ObservationHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): string
    {
        $tenant = Tenant::create(['code' => 't', 'name' => 'T']);
        [, $key] = ApiKey::issue($tenant->id, 'k', ['*']);
        app(TenantContext::class)->set($tenant->id);

        return $key;
    }

    private function competitorOnHost(string $host): CompetitorProduct
    {
        $source = CompetitorSource::query()->create(['host' => $host, 'adapter_code' => 'generic', 'robots_policy' => 'respect']);
        $product = Product::query()->create(['external_id' => 'EX-'.$host, 'name' => 'P']);
        $target = MonitoringTarget::query()->create(['product_id' => $product->id, 'country' => 'IT', 'status' => 'active', 'priority' => 1]);

        return CompetitorProduct::query()->create([
            'monitoring_target_id' => $target->id,
            'competitor_source_id' => $source->id,
            'url' => 'https://'.$host.'/p',
            'match_status' => MatchStatus::Confirmed,
        ]);
    }

    #[Test]
    public function prices_can_be_filtered_by_host(): void
    {
        $key = $this->auth();
        $a = $this->competitorOnHost('amazon.it');
        $b = $this->competitorOnHost('ebay.it');
        PriceObservation::query()->create(['competitor_product_id' => $a->id, 'captured_at' => now(), 'price_cents' => 1000, 'currency' => 'EUR', 'price_base_cents' => 1000, 'available' => true]);
        PriceObservation::query()->create(['competitor_product_id' => $b->id, 'captured_at' => now(), 'price_cents' => 2000, 'currency' => 'EUR', 'price_base_cents' => 2000, 'available' => true]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/observations/prices?host=ebay.it')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.price_cents', 2000);
    }

    #[Test]
    public function stock_history_is_listed_and_filterable(): void
    {
        $key = $this->auth();
        $cp = $this->competitorOnHost('amazon.it');
        StockObservation::query()->create(['competitor_product_id' => $cp->id, 'captured_at' => now(), 'available' => true, 'qty_estimate' => 5]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/observations/stock?competitor_product_id='.$cp->id)
            ->assertOk()
            ->assertJsonPath('data.0.qty_estimate', 5);
    }

    #[Test]
    public function promo_history_is_listed(): void
    {
        $key = $this->auth();
        $cp = $this->competitorOnHost('amazon.it');
        PromoObservation::query()->create(['competitor_product_id' => $cp->id, 'captured_at' => now(), 'promo_type' => 'sale', 'effective_discount_pct' => 15.0]);

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/observations/promos?competitor_product_id='.$cp->id)
            ->assertOk()
            ->assertJsonPath('data.0.effective_discount_pct', 15);
    }

    #[Test]
    public function history_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/observations/stock')->assertUnauthorized();
        $this->getJson('/api/v1/observations/promos')->assertUnauthorized();
    }
}
```

- [ ] **Step 2:** Run → fails (routes/methods missing).
- [ ] **Step 3: Add the `host` filter to `prices()`** — insert before `->orderByDesc('captured_at')`:
```php
            ->when($request->filled('host'), fn ($q) => $q->whereHas('competitorProduct.source', fn ($s) => $s->where('host', $request->string('host')->toString())))
```
and add `'host' => ['nullable', 'string']` to the validate array.

- [ ] **Step 4: Add `stock()` and `promos()`** to `ObservationController` (mirror `prices()`):
```php
public function stock(Request $request): JsonResponse
{
    $request->validate([
        'competitor_product_id' => ['nullable', 'integer'],
        'host' => ['nullable', 'string'],
        'from' => ['nullable', 'date'],
        'to' => ['nullable', 'date'],
        'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
    ]);

    $rows = StockObservation::query()
        ->when($request->filled('competitor_product_id'), fn ($q) => $q->where('competitor_product_id', $request->integer('competitor_product_id')))
        ->when($request->filled('host'), fn ($q) => $q->whereHas('competitorProduct.source', fn ($s) => $s->where('host', $request->string('host')->toString())))
        ->when($request->filled('from'), fn ($q) => $q->where('captured_at', '>=', $request->date('from')))
        ->when($request->filled('to'), fn ($q) => $q->where('captured_at', '<=', $request->date('to')))
        ->orderByDesc('captured_at')->orderByDesc('id')
        ->cursorPaginate((int) $request->integer('per_page', 100));

    return response()->json($rows);
}
```
`promos()` is identical but on `PromoObservation`. (Repeat the code — don't abstract; YAGNI.)

- [ ] **Step 5: Register routes** in `routes/api.php` next to the prices route:
```php
Route::get('/observations/stock', [ObservationController::class, 'stock'])->name('price-intelligence.observations.stock');
Route::get('/observations/promos', [ObservationController::class, 'promos'])->name('price-intelligence.observations.promos');
```

- [ ] **Step 6:** Run test → passes. Commit `feat(b3): observations host filter + stock/promo history endpoints`.

---

## Task 3: `GET /ai-decisions`

**Files:** Create `src/Http/Controllers/Api/V1/AiDecisionController.php`; add route; Test `tests/Feature/AiDecisionApiTest.php`

- [ ] **Step 1: Failing test** — auth bootstrap as above; seed two `AiDecisionLog` rows (features `narrative`, `forecast`); assert `GET /ai-decisions` returns both (cursor-paginated), `?feature=narrative` filters to one, `?subject_type=Product&subject_id=5` filters, and unauth → 401.

- [ ] **Step 2:** Run → fails.
- [ ] **Step 3: Controller**
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Models\AiDecisionLog;

final class AiDecisionController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'feature' => ['nullable', 'string', 'max:50'],
            'subject_type' => ['nullable', 'string', 'max:100'],
            'subject_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $logs = AiDecisionLog::query()
            ->when($request->filled('feature'), fn ($q) => $q->where('feature', $request->string('feature')->toString()))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->string('subject_type')->toString()))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($logs);
    }
}
```

- [ ] **Step 4: Route** in `routes/api.php`:
```php
Route::get('/ai-decisions', [AiDecisionController::class, 'index'])->name('price-intelligence.ai-decisions.index');
```
(import the controller at the top of the routes file.) Tenant-scoped via the global scope; no extra ability required (parity with other intelligence reads).

- [ ] **Step 5:** passes. Commit `feat(b3): add GET /ai-decisions endpoint`.

---

## Task 4: Facet endpoints (host + category counts)

**Files:** Create `src/Http/Controllers/Api/V1/FacetController.php`; add routes; Test `tests/Feature/FacetApiTest.php`

- [ ] **Step 1: Failing test** — seed confirmed competitor-products across two hosts (amazon.it ×2, ebay.it ×1); assert `GET /facets/hosts` returns `[{host:'amazon.it', count:2}, {host:'ebay.it', count:1}]` (order by count desc). Seed products with `categories` JSON; assert `GET /facets/categories` returns category counts. Unauth → 401.

- [ ] **Step 2:** fails.
- [ ] **Step 3: Controller** — host facet via DB groupBy joining source; category facet by exploding the JSON `categories` per product (SQL groupBy on a normalized path, or PHP aggregation over a `pluck`):
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\Product;

final class FacetController
{
    public function hosts(Request $request): JsonResponse
    {
        $rows = CompetitorProduct::query()
            ->where('match_status', MatchStatus::Confirmed->value)
            ->join((new \Padosoft\PriceIntelligence\Models\CompetitorSource)->getTable().' as src', 'src.id', '=', (new CompetitorProduct)->getTable().'.competitor_source_id')
            ->selectRaw('src.host as host, COUNT(*) as count')
            ->groupBy('src.host')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['host' => (string) $r->host, 'count' => (int) $r->count])
            ->all();

        return response()->json(['data' => $rows]);
    }

    public function categories(Request $request): JsonResponse
    {
        // categories is a JSON array per product; aggregate in PHP over a lean pluck
        // (catalog category cardinality is low; cursor keeps memory bounded).
        $counts = [];
        Product::query()->select('categories')->lazy()->each(function (Product $p) use (&$counts): void {
            foreach ((array) $p->categories as $cat) {
                if (is_string($cat) && $cat !== '') {
                    $counts[$cat] = ($counts[$cat] ?? 0) + 1;
                }
            }
        });
        arsort($counts);
        $data = array_map(fn ($cat, $count) => ['category' => $cat, 'count' => $count], array_keys($counts), array_values($counts));

        return response()->json(['data' => $data]);
    }
}
```
> Verify the join column names against `CompetitorProduct`/`CompetitorSource` table config (`getTable()` resolves the configured prefix). If the test DB uses a tenant global scope, the `join` bypasses it — re-apply `where('competitor_products.tenant_id', …)` is NOT needed because the model's global scope adds it on the base query; confirm by asserting counts in the test.

- [ ] **Step 4: Routes** + imports. Commit `feat(b3): add host/category facet endpoints`.

---

## Task 5: Streamed bulk export (CSV; Excel opt-in)

**Files:** Create `src/Services/Export/CsvStreamWriter.php`, `src/Http/Controllers/Api/V1/ExportController.php`; add routes; move `league/csv` to `require`; Test `tests/Feature/ExportApiTest.php`

- [ ] **Step 1:** Move `league/csv` from `require-dev` to `require` in `composer.json` (export needs it at runtime); run `composer update league/csv --lock` then `composer validate --strict`.

- [ ] **Step 2: Failing test** — auth; seed 3 products; `GET /catalog/products:export` returns 200, `Content-Type: text/csv`, `Content-Disposition: attachment`, and the streamed body contains the header row + 3 data rows (use `$response->streamedContent()`). Seed price observations; `GET /observations/prices:export?competitor_product_id=X` streams rows. Unauth → 401.

- [ ] **Step 3: `CsvStreamWriter`** — wraps `league/csv` Writer on `php://output` (or a callback), writing a header then each row from a generator:
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Export;

use League\Csv\Writer;

final class CsvStreamWriter
{
    /**
     * @param  array<int, string>  $header
     * @param  iterable<int, array<int, scalar|null>>  $rows
     */
    public function callback(array $header, iterable $rows): callable
    {
        return static function () use ($header, $rows): void {
            $csv = Writer::createFromStream(fopen('php://output', 'w'));
            $csv->insertOne($header);
            foreach ($rows as $row) {
                $csv->insertOne($row);
            }
        };
    }
}
```

- [ ] **Step 4: `ExportController`** — uses `response()->streamDownload()` + the model `cursor()` (OOM-safe):
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Models\PriceObservation;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Services\Export\CsvStreamWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportController
{
    public function __construct(private readonly CsvStreamWriter $writer) {}

    public function products(Request $request): StreamedResponse
    {
        $header = ['external_id', 'sku', 'gtin', 'mpn', 'brand', 'name', 'our_price_cents', 'currency'];
        $rows = (function () {
            foreach (Product::query()->orderBy('id')->cursor() as $p) {
                yield [$p->external_id, $p->sku, $p->gtin, $p->mpn, $p->brand, $p->name, $p->our_price_cents, $p->currency];
            }
        })();

        return response()->streamDownload($this->writer->callback($header, $rows), 'catalog.csv', ['Content-Type' => 'text/csv']);
    }

    public function prices(Request $request): StreamedResponse
    {
        $request->validate([
            'competitor_product_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $header = ['competitor_product_id', 'captured_at', 'price_cents', 'currency', 'price_base_cents', 'available'];
        $query = PriceObservation::query()
            ->when($request->filled('competitor_product_id'), fn ($q) => $q->where('competitor_product_id', $request->integer('competitor_product_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('captured_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('captured_at', '<=', $request->date('to')))
            ->orderBy('id');

        $rows = (function () use ($query) {
            foreach ($query->cursor() as $o) {
                yield [$o->competitor_product_id, $o->captured_at?->toIso8601String(), $o->price_cents, $o->currency, $o->price_base_cents, $o->available ? 1 : 0];
            }
        })();

        return response()->streamDownload($this->writer->callback($header, $rows), 'prices.csv', ['Content-Type' => 'text/csv']);
    }
}
```

- [ ] **Step 5: Routes** (note the `:export` suffix mirrors the existing `:bulk`/`:csv` style):
```php
Route::get('/catalog/products:export', [ExportController::class, 'products'])->name('price-intelligence.catalog.export');
Route::get('/observations/prices:export', [ExportController::class, 'prices'])->name('price-intelligence.observations.prices.export');
```

- [ ] **Step 6:** passes. Commit `feat(b3): streamed CSV bulk export (catalog + price observations)`.

> Excel: leave as a documented opt-in — if `phpoffice/phpspreadsheet` is installed, a host can register a custom export. Implementing a full XLSX streamer is out of scope for v1.5.0 (CSV is the OOM-safe enterprise path); note in README + LESSON.

---

## Task 6: Tenant settings write + expose in `me()`

**Files:** Modify `src/Http/Controllers/Api/V1/TenantController.php`; add route; Test `tests/Feature/TenantSettingsTest.php`

- [ ] **Step 1: Failing test** — auth; `GET /tenants/me` includes `data.tenant.settings` (object, defaults `{}`); `PATCH /tenants/me/settings` with `{settings:{currency_base:'EUR', digest_opt_in:true}}` returns 200 + persists (assert `Tenant::find()->settings`); a subsequent `me()` reflects it; invalid body (non-array `settings`) → 422; unauth → 401.

- [ ] **Step 2:** fails.
- [ ] **Step 3: Expose settings in `me()`** — change the tenant payload to include `'settings' => $tenant?->settings ?? []`.
- [ ] **Step 4: `updateSettings()`**:
```php
public function updateSettings(Request $request): JsonResponse
{
    $validated = $request->validate([
        'settings' => ['required', 'array'],
    ]);

    $tenantId = $this->tenantContext->id();
    abort_if($tenantId === null, 401, 'No tenant resolved.');

    $tenant = Tenant::query()->findOrFail($tenantId);
    $tenant->forceFill(['settings' => array_merge((array) $tenant->settings, $validated['settings'])])->save();

    return response()->json(['data' => ['settings' => $tenant->settings]]);
}
```
- [ ] **Step 5: Route**:
```php
Route::patch('/tenants/me/settings', [TenantController::class, 'updateSettings'])->name('price-intelligence.tenants.settings.update');
```
- [ ] **Step 6:** passes. Commit `feat(b3): tenant settings read in me() + PATCH /tenants/me/settings`.

---

## Task 7: Daily-aggregate table + model + nightly chunked command

**Files:** Create migration `..._100015_create_pi_price_daily_aggregates.php`, `src/Models/PriceDailyAggregate.php`, `src/Console/Commands/MaterializeDailyAggregatesCommand.php`; register in ServiceProvider; Test `tests/Feature/DailyAggregatesTest.php`

- [ ] **Step 1: Migration** — `pi_price_daily_aggregates`: `id, tenant_id (idx), competitor_product_id (idx), day (date), min_price_cents, max_price_cents, avg_price_cents, samples (int), currency, timestamps`, unique `['competitor_product_id','day']` named `pi_pda_cp_day_uq`.

- [ ] **Step 2: Model** `PriceDailyAggregate` (mirror PriceObservation: `BelongsToTenant`, `configKey='price_daily_aggregates'`, `$guarded=[]`, casts day→date + the int columns).

- [ ] **Step 3: Failing test** — seed several `PriceObservation` rows for one competitor on a given day (prices 1000/2000/3000); run the command `$this->artisan('piprice:aggregates:daily')`; assert one `PriceDailyAggregate` row with `min=1000,max=3000,avg=2000,samples=3`. Re-running is idempotent (updateOrCreate, still 1 row).

- [ ] **Step 4: Command** — `chunkById` over distinct (competitor_product_id, day) groups for a target date (default yesterday), computing min/max/avg via SQL aggregation, `updateOrCreate` per group:
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Padosoft\PriceIntelligence\Models\PriceDailyAggregate;
use Padosoft\PriceIntelligence\Models\PriceObservation;

final class MaterializeDailyAggregatesCommand extends Command
{
    protected $signature = 'piprice:aggregates:daily {--date= : ISO date to aggregate (default: yesterday)}';

    protected $description = 'Materialize daily price aggregates (min/max/avg) from raw observations.';

    public function handle(): int
    {
        $date = $this->option('date') !== null
            ? \Illuminate\Support\Carbon::parse((string) $this->option('date'))->toDateString()
            : now()->subDay()->toDateString();

        PriceObservation::query()
            ->whereDate('captured_at', $date)
            ->whereNotNull('price_cents')
            ->select('tenant_id', 'competitor_product_id', 'currency')
            ->selectRaw('MIN(price_cents) as min_p, MAX(price_cents) as max_p, ROUND(AVG(price_cents)) as avg_p, COUNT(*) as samples')
            ->groupBy('tenant_id', 'competitor_product_id', 'currency')
            ->get()
            ->each(function ($row) use ($date): void {
                PriceDailyAggregate::query()->updateOrCreate(
                    ['competitor_product_id' => (int) $row->competitor_product_id, 'day' => $date],
                    [
                        'tenant_id' => $row->tenant_id,
                        'currency' => $row->currency,
                        'min_price_cents' => (int) $row->min_p,
                        'max_price_cents' => (int) $row->max_p,
                        'avg_price_cents' => (int) $row->avg_p,
                        'samples' => (int) $row->samples,
                    ],
                );
            });

        $this->info('Daily aggregates materialized for '.$date.'.');

        return self::SUCCESS;
    }
}
```
> The `get()->each()` is bounded by distinct (competitor_product, currency) groups for ONE day — not raw row count — so it's memory-safe at scale. (Raw rows are reduced in SQL.) If group cardinality itself is huge, switch to `->lazy()`; note the choice in LESSON.md.

- [ ] **Step 5: Register** the command + nightly schedule in `PriceIntelligenceServiceProvider` (mirror the existing `RunDueTargetsCommand`/`PruneAuditLogsCommand` registration + `$schedule->command('piprice:aggregates:daily')->dailyAt('02:30')` guarded by `storage.aggregates.enabled`).

- [ ] **Step 6:** passes. Commit `feat(b3): daily price-aggregate materialization command + table`.

---

## Task 8: Index review (hot tables)

**Files:** Create migration `..._100016_add_b3_indexes.php`

- [ ] **Step 1:** Add composite indexes that match the new query patterns (idempotent — guard with `Schema::hasIndex` equivalent via try/catch or check), all `captured_at`-leading where useful:
  - `pi_stock_observations`: `['competitor_product_id', 'captured_at']` → `pi_so_cp_time_idx`.
  - `pi_promo_observations`: `['competitor_product_id', 'captured_at']` → `pi_promo_cp_time_idx`.
  - `pi_ai_decision_logs`: `['tenant_id', 'subject_type', 'subject_id']` → `pi_aidl_subj_idx`.
- [ ] **Step 2:** Test that migrations run clean (the existing `AiLayerTest::ai_migrations_create_tables` style — assert `Schema::hasTable` still true after migrate; the index migration is exercised by `RefreshDatabase` in every test). Commit `perf(b3): add composite indexes for stock/promo history + ai-decision subject lookups`.

---

## Task 9: Gate, docs, Copilot loop, release v1.5.0

- [ ] **Step 1:** Full local gate (composer validate, phpunit, pint --test changed, phpstan --memory-limit=1G). Restore line-ending churn on untouched files.
- [ ] **Step 2:** `docs/PROGRESS.md` (B3 done, B4 next/admin), `docs/LESSON.md` (B3 lessons), `CHANGELOG.md` `[1.5.0]`, README (new endpoints + export + settings + aggregates command).
- [ ] **Step 3:** Local Copilot `/review` loop → NO ISSUES; re-run full gate.
- [ ] **Step 4:** Commit, push, open PR, request Copilot review.
- [ ] **Step 5:** Remote loop: CI green + Copilot zero actionable (verify; push back when wrong).
- [ ] **Step 6:** Squash-merge + delete branch, sync main, tag **v1.5.0**, GitHub release. **Core B-phases complete** → advance to admin **B4** (bump the admin's core dep to ^1.5).

---

## Self-Review (against spec §B3)
- `/observations/prices` host filter → Task 2. ✅  stock & promo history → Task 2. ✅  `GET /ai-decisions` → Task 3. ✅  facet endpoints (host & category via SQL/lazy groupBy) → Task 4. ✅  bulk CSV export (queued/streamed → streamed cursor, OOM-safe) → Task 5; Excel documented opt-in. ✅  tenant settings write → Task 6. ✅  chunk/batch + daily-aggregate materialization → Task 7; index review → Task 8. ✅
- **Acceptance:** new endpoints documented + tested (Tasks 2-6 each have a Feature test + README/CHANGELOG in Task 9); export OOM-safe via `cursor()`+`streamDownload` (Task 5); facets computed in SQL/`lazy` (Task 4); settings round-trip (Task 6). ✅
- **Placeholder scan:** the "verify before writing" notes (facet join column names, aggregate group cardinality) name the exact file + adaptation. **Type consistency:** all new read endpoints use the same `cursorPaginate` envelope + `when()` filter style as `ObservationController::prices`; export uses `CsvStreamWriter::callback(header, rows): callable` consistently; `PriceDailyAggregate` columns match the command's `updateOrCreate` keys.
