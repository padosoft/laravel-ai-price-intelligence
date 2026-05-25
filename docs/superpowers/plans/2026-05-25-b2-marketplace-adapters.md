# B2 — Marketplace API Adapters Implementation Plan (core → v1.4.0)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans / subagent-driven-development. Steps use `- [ ]` checkboxes.

**Goal:** Add real, config-selectable marketplace **API** adapters — Amazon (Keepa + SP-API), eBay Browse, Google Shopping SERP, and a new **Farfetch** multi-driver adapter — behind the existing `MarketplaceAdapterInterface`, each falling back to the current HTML scrape path when credentials are absent, all fixture-tested with `Http::fake` and an opt-in live suite.

**Architecture:** A new `AbstractApiAdapter extends AbstractScrapeAdapter`. Its `fetch()` resolves the configured driver for the marketplace; if an API driver is selected and credentials are present, it calls `apiFetch()` (a per-adapter API call via the `Http` facade, mapped to `ProductSnapshot`); otherwise — or when the API call yields an unreachable/empty result — it falls back to `parent::fetch()` (the existing scrape path). API access is plain `Http` facade calls (so CI tests use `Http::fake` recorded JSON fixtures; no live calls). Credentials/driver come from the extended `config('price-intelligence.marketplaces.*')` block. A new `AdapterCode::Farfetch` + factory wiring + `CompetitorSourceResolver` host mapping make Farfetch first-class.

**Tech Stack:** PHP 8.3, Laravel 11/12/13, `Illuminate\Support\Facades\Http`, Orchestra Testbench, PHPUnit (`#[Test]`). No new hard dependencies (SP-API uses LWA bearer token via Http — no AWS SigV4; Keepa/eBay/SERP/Farfetch are plain REST).

**Conventions:** PowerShell for `vendor\bin\*`; tests by path; `final` classes, `declare(strict_types=1)`; one PR (`feat/phase-b2-marketplace-adapters`); strict local-Copilot → CI → GitHub-Copilot loop; restore line-ending-only Pint churn on untouched files.

---

## File Structure

**New API clients** (`src/Services/Scraping/Marketplaces/Api/`), each a `final` class using `Http`:
- `KeepaClient.php` — Amazon price + history via Keepa `GET /product`.
- `AmazonSpApiClient.php` — LWA token exchange + SP-API Catalog/Pricing (`x-amz-access-token` bearer).
- `EbayBrowseClient.php` — OAuth2 client-credentials token + Browse API `getItem`.
- `SerpShoppingClient.php` — SerpApi-style Google Shopping product lookup.
- `FarfetchClient.php` — `retailed` + `apify` drivers (scrape handled by the adapter fallback).

**New DTO:** `src/Data/ApiProductResult.php` — normalized API response (price/currency/available/title/brand/image/seller) the adapters map into `ProductSnapshot`. (Keeps clients framework-DTO-free and uniformly testable.)

**New base + adapters:**
- `src/Services/Scraping/Marketplaces/AbstractApiAdapter.php` — driver resolution + API-or-scrape `fetch()`.
- Rewrite `AmazonAdapter.php`, `EbayAdapter.php`, `GoogleShoppingAdapter.php` to extend `AbstractApiAdapter` (keep their `externalRef()` regexes).
- New `FarfetchAdapter.php`.

**Modified:**
- `src/Enums/AdapterCode.php` — add `Farfetch = 'farfetch'`.
- `src/Services/Scraping/MarketplaceAdapterFactory.php` — construct API adapters with their clients; add Farfetch case.
- `src/Services/Discovery/CompetitorSourceResolver.php` — map `farfetch.com`/`*.farfetch.com` → `AdapterCode::Farfetch` (verify file; add host mapping).
- `config/price-intelligence.php` — extend `marketplaces` with driver + credential sub-arrays for amazon/ebay/google_shopping/farfetch.
- `composer.json` — add `suggest` notes for the (optional, env-keyed) APIs. No hard deps.
- `docs/PROGRESS.md`, `docs/LESSON.md`, `CHANGELOG.md`, `README.md`.

**Tests (new):**
- `tests/Feature/Marketplaces/KeepaClientTest.php`, `AmazonSpApiClientTest.php`, `EbayBrowseClientTest.php`, `SerpShoppingClientTest.php`, `FarfetchClientTest.php`
- `tests/Feature/Marketplaces/MarketplaceApiAdapterTest.php` (driver selection + scrape fallback through `ScrapeService`)
- `tests/Live/LiveMarketplaceSmokeTest.php` (opt-in, `PI_LIVE_MARKETPLACE=1`, skipped in CI)

---

## Driver semantics (config)

Each marketplace gets `marketplaces.<key>.driver`:
- **amazon**: `auto` (default) → SP-API if its creds present, else Keepa if its key present, else scrape · `sp_api` · `keepa` · `scrape`.
- **ebay**: `auto` (default) → Browse if creds present else scrape · `api` · `scrape`.
- **google_shopping**: `auto` (default) → SERP if key present else scrape · `serp` · `scrape`.
- **farfetch**: `scrape` (default) · `retailed` · `apify` (last two need keys; missing key → scrape).

`auto` + missing creds **never throws** — it silently uses scrape (the offline-safe default), mirroring B1's fake-default philosophy. An explicit API driver with missing creds also falls back to scrape and `report()`s a warning (guarded by `function_exists('report')`).

---

## Task 1: `AdapterCode::Farfetch` + config block

**Files:** Modify `src/Enums/AdapterCode.php`, `config/price-intelligence.php`
- Test: `tests/Feature/Marketplaces/MarketplaceApiAdapterTest.php` (later tasks assert config-driven behavior)

- [ ] **Step 1: Add the enum case**

In `src/Enums/AdapterCode.php`, add after `Trovaprezzi`:
```php
    case Farfetch = 'farfetch';
```

- [ ] **Step 2: Replace the `marketplaces` config block**

In `config/price-intelligence.php`, replace the existing `'marketplaces' => [ ... ]` with:
```php
'marketplaces' => [
    'amazon' => [
        'driver' => env('PI_AMAZON_DRIVER', 'auto'), // auto|sp_api|keepa|scrape
        'rate_limit_rpm' => (int) env('PI_AMAZON_RPM', 20),
        'sp_api' => [
            'client_id' => env('PI_AMAZON_SPAPI_CLIENT_ID'),
            'client_secret' => env('PI_AMAZON_SPAPI_CLIENT_SECRET'),
            'refresh_token' => env('PI_AMAZON_SPAPI_REFRESH_TOKEN'),
            'endpoint' => env('PI_AMAZON_SPAPI_ENDPOINT', 'https://sellingpartnerapi-eu.amazon.com'),
            'token_endpoint' => env('PI_AMAZON_SPAPI_TOKEN_ENDPOINT', 'https://api.amazon.com/auth/o2/token'),
            'marketplace_id' => env('PI_AMAZON_MARKETPLACE_ID', 'APJ6JRA9NG5V4'), // IT
        ],
        'keepa' => [
            'key' => env('PI_KEEPA_KEY'),
            'domain' => (int) env('PI_KEEPA_DOMAIN', 8), // 8 = amazon.it
            'endpoint' => env('PI_KEEPA_ENDPOINT', 'https://api.keepa.com'),
        ],
    ],
    'ebay' => [
        'driver' => env('PI_EBAY_DRIVER', 'auto'), // auto|api|scrape
        'client_id' => env('PI_EBAY_CLIENT_ID'),
        'client_secret' => env('PI_EBAY_CLIENT_SECRET'),
        'endpoint' => env('PI_EBAY_ENDPOINT', 'https://api.ebay.com'),
        'marketplace_id' => env('PI_EBAY_MARKETPLACE_ID', 'EBAY_IT'),
    ],
    'google_shopping' => [
        'driver' => env('PI_GOOGLE_DRIVER', 'auto'), // auto|serp|scrape
        'serp' => [
            'key' => env('PI_SERPAPI_KEY'),
            'endpoint' => env('PI_SERPAPI_ENDPOINT', 'https://serpapi.com/search'),
            'gl' => env('PI_SERPAPI_GL', 'it'),
            'hl' => env('PI_SERPAPI_HL', 'it'),
        ],
    ],
    'idealo' => ['driver' => 'scrape'],
    'trovaprezzi' => ['driver' => 'scrape'],
    'farfetch' => [
        'driver' => env('PI_FARFETCH_DRIVER', 'scrape'), // scrape|retailed|apify
        'retailed' => [
            'key' => env('PI_RETAILED_KEY'),
            'endpoint' => env('PI_RETAILED_ENDPOINT', 'https://app.retailed.io/api/v1/scraper/farfetch/product'),
        ],
        'apify' => [
            'token' => env('PI_APIFY_TOKEN'),
            'actor' => env('PI_APIFY_FARFETCH_ACTOR', 'autofacts~farfetch'),
            'endpoint' => env('PI_APIFY_ENDPOINT', 'https://api.apify.com/v2'),
        ],
    ],
],
```

- [ ] **Step 3: Verify config parses + enum case exists**

Run: `vendor\bin\phpunit tests/Feature/Marketplaces` (will be created; for now run existing `tests/Feature/MarketplaceAdapterTest.php` to confirm no regression).
Run: `php -r "require 'vendor/autoload.php'; var_dump(Padosoft\PriceIntelligence\Enums\AdapterCode::Farfetch->value);"` → `string(8) "farfetch"`.

- [ ] **Step 4: Commit** — `feat(b2): add AdapterCode::Farfetch + marketplaces driver/credential config`

---

## Task 2: `ApiProductResult` DTO

**Files:** Create `src/Data/ApiProductResult.php`

- [ ] **Step 1: Write the DTO** — uniform output every API client returns; the adapter maps it onto `ProductSnapshot`.
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

final class ApiProductResult
{
    /** @param array<int, string> $images */
    public function __construct(
        public readonly ?int $priceCents = null,
        public readonly ?string $currency = null,
        public readonly bool $available = true,
        public readonly ?string $title = null,
        public readonly ?string $brand = null,
        public readonly ?string $gtin = null,
        public readonly ?string $mpn = null,
        public readonly array $images = [],
        public readonly ?string $buyboxSeller = null,
        public readonly ?string $externalRef = null,
    ) {}

    public function toSnapshot(string $url): ProductSnapshot
    {
        return new ProductSnapshot(
            url: $url,
            priceCents: $this->priceCents,
            currency: $this->currency,
            available: $this->available,
            title: $this->title,
            images: $this->images,
            gtin: $this->gtin,
            mpn: $this->mpn,
            brand: $this->brand,
            buyboxSeller: $this->buyboxSeller,
        );
    }
}
```
- [ ] **Step 2:** `phpstan analyse src/Data/ApiProductResult.php --memory-limit=1G` → no errors. Commit `feat(b2): add ApiProductResult DTO`.

---

## Task 3: `AbstractApiAdapter`

**Files:** Create `src/Services/Scraping/Marketplaces/AbstractApiAdapter.php`

- [ ] **Step 1: Write the base class**
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces;

use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;

/**
 * Base for marketplaces that have a real API path with graceful scrape fallback.
 * Subclasses pick a driver from config and implement apiFetch(); when it returns null
 * (driver=scrape, missing creds, or a failed/empty API call) the inherited scrape path runs.
 */
abstract class AbstractApiAdapter extends AbstractScrapeAdapter
{
    /** Config key under price-intelligence.marketplaces (e.g. 'amazon'). */
    abstract protected function configKey(): string;

    /**
     * Try the configured API driver. Return null to fall back to scraping.
     *
     * @param  array<string, mixed>  $options
     */
    abstract protected function apiFetch(CompetitorProduct $competitorProduct, string $driver, array $options): ?ProductSnapshot;

    /**
     * @param  array<string, mixed>  $options
     */
    public function fetch(CompetitorProduct $competitorProduct, array $options = []): ProductSnapshot
    {
        $driver = (string) config('price-intelligence.marketplaces.'.$this->configKey().'.driver', 'scrape');

        if ($driver !== 'scrape') {
            try {
                $snapshot = $this->apiFetch($competitorProduct, $driver, $options);
            } catch (\Throwable $e) {
                if (function_exists('report')) {
                    report($e);
                }
                $snapshot = null;
            }

            if ($snapshot !== null && $snapshot->reachable) {
                $ref = $this->externalRef($competitorProduct->url);
                if ($ref !== null && $competitorProduct->external_ref === null) {
                    $competitorProduct->forceFill(['external_ref' => $ref])->save();
                }

                return $snapshot;
            }
        }

        // scrape fallback (also persists external_ref)
        return parent::fetch($competitorProduct, $options);
    }

    protected function config(string $path, mixed $default = null): mixed
    {
        return config('price-intelligence.marketplaces.'.$this->configKey().'.'.$path, $default);
    }
}
```
- [ ] **Step 2:** phpstan clean. Commit `feat(b2): add AbstractApiAdapter (API-or-scrape fetch)`.

---

## Task 4: `KeepaClient` + Amazon Keepa driver

**Files:** Create `src/Services/Scraping/Marketplaces/Api/KeepaClient.php`; Test `tests/Feature/Marketplaces/KeepaClientTest.php`

Keepa: `GET {endpoint}/product?key=KEY&domain=N&asin=ASIN`. Response `products[0]`: `csv` arrays (price history, index 0 = Amazon price in cents-ish "Keepa units" = price*100 for EUR with -1 = unavailable), `title`, `brand`, `eanList`. Current price: `products[0].stats.current[0]` (Amazon) or `csv[0]` last value. Keepa prices are integer cents already (e.g. 5499 = 54.99). -1 = no price/unavailable.

- [ ] **Step 1: Write the failing test**
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Marketplaces;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\KeepaClient;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class KeepaClientTest extends TestCase
{
    #[Test]
    public function it_returns_null_when_no_key_configured(): void
    {
        config()->set('price-intelligence.marketplaces.amazon.keepa.key', null);
        $this->assertNull(app(KeepaClient::class)->fetchByAsin('B07PFFMP9P'));
    }

    #[Test]
    public function it_maps_a_keepa_product_response(): void
    {
        config()->set('price-intelligence.marketplaces.amazon.keepa.key', 'k');
        config()->set('price-intelligence.marketplaces.amazon.keepa.domain', 8);

        Http::fake(['api.keepa.com/*' => Http::response([
            'products' => [[
                'asin' => 'B07PFFMP9P',
                'title' => 'Echo Dot',
                'brand' => 'Amazon',
                'eanList' => ['0840080553856'],
                'stats' => ['current' => [5499, -1, 5999]],
            ]],
        ], 200)]);

        $result = app(KeepaClient::class)->fetchByAsin('B07PFFMP9P');

        $this->assertNotNull($result);
        $this->assertSame(5499, $result->priceCents);
        $this->assertSame('Echo Dot', $result->title);
        $this->assertSame('Amazon', $result->brand);
        $this->assertSame('0840080553856', $result->gtin);
        $this->assertTrue($result->available);
    }

    #[Test]
    public function unavailable_price_marks_not_available(): void
    {
        config()->set('price-intelligence.marketplaces.amazon.keepa.key', 'k');
        Http::fake(['api.keepa.com/*' => Http::response([
            'products' => [['asin' => 'X', 'title' => 'T', 'stats' => ['current' => [-1]]]],
        ], 200)]);

        $result = app(KeepaClient::class)->fetchByAsin('X');
        $this->assertNotNull($result);
        $this->assertNull($result->priceCents);
        $this->assertFalse($result->available);
    }
}
```
- [ ] **Step 2:** Run → fails (class missing).
- [ ] **Step 3: Write `KeepaClient`**
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Data\ApiProductResult;

final class KeepaClient
{
    public function fetchByAsin(string $asin): ?ApiProductResult
    {
        $key = config('price-intelligence.marketplaces.amazon.keepa.key');
        if (! is_string($key) || $key === '') {
            return null;
        }

        $endpoint = (string) config('price-intelligence.marketplaces.amazon.keepa.endpoint', 'https://api.keepa.com');
        $domain = (int) config('price-intelligence.marketplaces.amazon.keepa.domain', 8);

        $response = Http::timeout(20)->get($endpoint.'/product', [
            'key' => $key, 'domain' => $domain, 'asin' => $asin,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $product = $response->json('products.0');
        if (! is_array($product)) {
            return null;
        }

        $current = $product['stats']['current'][0] ?? -1;
        $priceCents = is_numeric($current) && (int) $current >= 0 ? (int) $current : null;

        $ean = $product['eanList'][0] ?? null;

        return new ApiProductResult(
            priceCents: $priceCents,
            currency: $this->currencyForDomain($domain),
            available: $priceCents !== null,
            title: is_string($product['title'] ?? null) ? $product['title'] : null,
            brand: is_string($product['brand'] ?? null) ? $product['brand'] : null,
            gtin: is_string($ean) ? $ean : null,
            externalRef: is_string($product['asin'] ?? null) ? $product['asin'] : $asin,
        );
    }

    private function currencyForDomain(int $domain): string
    {
        return match ($domain) {
            1 => 'USD', 2 => 'GBP', 3, 4, 5, 8, 9 => 'EUR', 6 => 'CAD', 10 => 'JPY', default => 'EUR',
        };
    }
}
```
- [ ] **Step 4:** Run test → passes. Commit `feat(b2): add Keepa client for Amazon price/history`.

---

## Task 5: `AmazonSpApiClient` (LWA token + pricing)

**Files:** Create `src/Services/Scraping/Marketplaces/Api/AmazonSpApiClient.php`; Test `tests/Feature/Marketplaces/AmazonSpApiClientTest.php`

SP-API (2023+): no AWS SigV4 — only an LWA access token. (1) POST `token_endpoint` `{grant_type:refresh_token, refresh_token, client_id, client_secret}` → `access_token`. (2) GET `{endpoint}/products/pricing/v0/items/{asin}/offers?MarketplaceId=..&ItemCondition=New` with header `x-amz-access-token`. Map `payload.Summary.LowestPrices[0].LandedPrice.Amount` (major units → cents) + `BuyBoxPrices`.

- [ ] **Step 1: Failing test**
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Marketplaces;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\AmazonSpApiClient;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class AmazonSpApiClientTest extends TestCase
{
    private function configureCreds(): void
    {
        config()->set('price-intelligence.marketplaces.amazon.sp_api', [
            'client_id' => 'cid', 'client_secret' => 'secret', 'refresh_token' => 'rt',
            'endpoint' => 'https://sellingpartnerapi-eu.amazon.com',
            'token_endpoint' => 'https://api.amazon.com/auth/o2/token',
            'marketplace_id' => 'APJ6JRA9NG5V4',
        ]);
    }

    #[Test]
    public function it_returns_null_without_credentials(): void
    {
        config()->set('price-intelligence.marketplaces.amazon.sp_api', []);
        $this->assertNull(app(AmazonSpApiClient::class)->fetchOffers('B07PFFMP9P'));
    }

    #[Test]
    public function it_exchanges_token_then_maps_offers(): void
    {
        $this->configureCreds();
        Http::fake([
            'api.amazon.com/auth/o2/token' => Http::response(['access_token' => 'atk', 'expires_in' => 3600], 200),
            'sellingpartnerapi-eu.amazon.com/*' => Http::response([
                'payload' => [
                    'Summary' => [
                        'LowestPrices' => [['LandedPrice' => ['Amount' => 54.99, 'CurrencyCode' => 'EUR']]],
                        'BuyBoxPrices' => [['LandedPrice' => ['Amount' => 54.99, 'CurrencyCode' => 'EUR']]],
                        'TotalOfferCount' => 3,
                    ],
                ],
            ], 200),
        ]);

        $result = app(AmazonSpApiClient::class)->fetchOffers('B07PFFMP9P');

        $this->assertNotNull($result);
        $this->assertSame(5499, $result->priceCents);
        $this->assertSame('EUR', $result->currency);
        $this->assertTrue($result->available);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/products/pricing/v0/items/B07PFFMP9P/offers')
            && $r->hasHeader('x-amz-access-token', 'atk'));
    }

    #[Test]
    public function zero_offers_means_unavailable(): void
    {
        $this->configureCreds();
        Http::fake([
            'api.amazon.com/auth/o2/token' => Http::response(['access_token' => 'atk'], 200),
            'sellingpartnerapi-eu.amazon.com/*' => Http::response(['payload' => ['Summary' => ['TotalOfferCount' => 0]]], 200),
        ]);

        $result = app(AmazonSpApiClient::class)->fetchOffers('X');
        $this->assertNotNull($result);
        $this->assertNull($result->priceCents);
        $this->assertFalse($result->available);
    }
}
```
- [ ] **Step 2:** fails. **Step 3: Write the client**
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Data\ApiProductResult;

final class AmazonSpApiClient
{
    public function fetchOffers(string $asin): ?ApiProductResult
    {
        $cfg = (array) config('price-intelligence.marketplaces.amazon.sp_api', []);
        foreach (['client_id', 'client_secret', 'refresh_token'] as $required) {
            if (empty($cfg[$required])) {
                return null;
            }
        }

        $token = $this->accessToken($cfg);
        if ($token === null) {
            return null;
        }

        $endpoint = rtrim((string) ($cfg['endpoint'] ?? 'https://sellingpartnerapi-eu.amazon.com'), '/');
        $marketplaceId = (string) ($cfg['marketplace_id'] ?? '');

        $response = Http::withHeaders(['x-amz-access-token' => $token])
            ->timeout(20)
            ->get($endpoint.'/products/pricing/v0/items/'.$asin.'/offers', [
                'MarketplaceId' => $marketplaceId, 'ItemCondition' => 'New',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $summary = $response->json('payload.Summary');
        if (! is_array($summary)) {
            return null;
        }

        $priceMajor = $summary['BuyBoxPrices'][0]['LandedPrice']['Amount']
            ?? $summary['LowestPrices'][0]['LandedPrice']['Amount']
            ?? null;
        $currency = $summary['BuyBoxPrices'][0]['LandedPrice']['CurrencyCode']
            ?? $summary['LowestPrices'][0]['LandedPrice']['CurrencyCode']
            ?? null;
        $offerCount = (int) ($summary['TotalOfferCount'] ?? 0);

        $priceCents = is_numeric($priceMajor) ? (int) round((float) $priceMajor * 100) : null;

        return new ApiProductResult(
            priceCents: $priceCents,
            currency: is_string($currency) ? $currency : null,
            available: $priceCents !== null && $offerCount > 0,
            externalRef: $asin,
        );
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function accessToken(array $cfg): ?string
    {
        $response = Http::asForm()->timeout(20)->post(
            (string) ($cfg['token_endpoint'] ?? 'https://api.amazon.com/auth/o2/token'),
            [
                'grant_type' => 'refresh_token',
                'refresh_token' => (string) $cfg['refresh_token'],
                'client_id' => (string) $cfg['client_id'],
                'client_secret' => (string) $cfg['client_secret'],
            ],
        );

        $token = $response->successful() ? $response->json('access_token') : null;

        return is_string($token) ? $token : null;
    }
}
```
- [ ] **Step 4:** passes. Commit `feat(b2): add Amazon SP-API pricing client (LWA bearer)`.

---

## Task 6: Rewrite `AmazonAdapter` to use the API drivers

**Files:** Modify `src/Services/Scraping/Marketplaces/AmazonAdapter.php`; the factory passes the two clients.

- [ ] **Step 1: Rewrite the adapter**
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces;

use Padosoft\PriceIntelligence\Contracts\ProductScraperInterface;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\AdapterCode;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\AmazonSpApiClient;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\KeepaClient;

final class AmazonAdapter extends AbstractApiAdapter
{
    public function __construct(
        ProductScraperInterface $scraper,
        private readonly AmazonSpApiClient $spApi,
        private readonly KeepaClient $keepa,
    ) {
        parent::__construct($scraper);
    }

    public function code(): AdapterCode
    {
        return AdapterCode::Amazon;
    }

    protected function configKey(): string
    {
        return 'amazon';
    }

    protected function externalRef(string $url): ?string
    {
        if (preg_match('#/(?:dp|gp/product)/([A-Z0-9]{10})#i', $url, $m) === 1) {
            return strtoupper($m[1]);
        }

        return null;
    }

    protected function apiFetch(CompetitorProduct $competitorProduct, string $driver, array $options): ?ProductSnapshot
    {
        $asin = $competitorProduct->external_ref ?? $this->externalRef($competitorProduct->url);
        if ($asin === null) {
            return null;
        }

        $result = match ($driver) {
            'sp_api' => $this->spApi->fetchOffers($asin),
            'keepa' => $this->keepa->fetchByAsin($asin),
            default => $this->spApi->fetchOffers($asin) ?? $this->keepa->fetchByAsin($asin), // auto
        };

        return $result?->toSnapshot($competitorProduct->url);
    }
}
```
- [ ] **Step 2:** Update the factory's Amazon case (Task 10). Commit with Task 10.

---

## Task 7: `EbayBrowseClient` + eBay adapter

**Files:** Create `src/Services/Scraping/Marketplaces/Api/EbayBrowseClient.php`; rewrite `EbayAdapter.php`; Test `tests/Feature/Marketplaces/EbayBrowseClientTest.php`

eBay Browse: (1) POST `{endpoint}/identity/v1/oauth2/token` (Basic base64(client_id:client_secret), `grant_type=client_credentials&scope=https://api.ebay.com/oauth/api_scope`) → `access_token`. (2) GET `{endpoint}/buy/browse/v1/item/v1|<legacy>` — use `getItemByLegacyId?legacy_item_id=<id>` with header `Authorization: Bearer` + `X-EBAY-C-MARKETPLACE-ID`. Map `price.value`/`price.currency`, `title`, `brand` (from `localizedAspects`), `image.imageUrl`, `estimatedAvailabilities[0].estimatedAvailabilityStatus`.

- [ ] **Step 1: Failing test**
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Marketplaces;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\EbayBrowseClient;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class EbayBrowseClientTest extends TestCase
{
    private function creds(): void
    {
        config()->set('price-intelligence.marketplaces.ebay', [
            'client_id' => 'cid', 'client_secret' => 'sec',
            'endpoint' => 'https://api.ebay.com', 'marketplace_id' => 'EBAY_IT',
        ]);
    }

    #[Test]
    public function it_returns_null_without_credentials(): void
    {
        config()->set('price-intelligence.marketplaces.ebay', ['endpoint' => 'https://api.ebay.com']);
        $this->assertNull(app(EbayBrowseClient::class)->fetchByLegacyId('123456789012'));
    }

    #[Test]
    public function it_tokenizes_then_maps_item(): void
    {
        $this->creds();
        Http::fake([
            'api.ebay.com/identity/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 7200], 200),
            'api.ebay.com/buy/browse/*' => Http::response([
                'itemId' => 'v1|123456789012|0',
                'title' => 'Vintage Camera',
                'price' => ['value' => '129.90', 'currency' => 'EUR'],
                'image' => ['imageUrl' => 'https://i.ebayimg.com/x.jpg'],
                'estimatedAvailabilities' => [['estimatedAvailabilityStatus' => 'IN_STOCK']],
            ], 200),
        ]);

        $result = app(EbayBrowseClient::class)->fetchByLegacyId('123456789012');

        $this->assertNotNull($result);
        $this->assertSame(12990, $result->priceCents);
        $this->assertSame('EUR', $result->currency);
        $this->assertSame('Vintage Camera', $result->title);
        $this->assertTrue($result->available);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'getItemByLegacyId')
            && $r->hasHeader('Authorization', 'Bearer tok')
            && $r->hasHeader('X-EBAY-C-MARKETPLACE-ID', 'EBAY_IT'));
    }
}
```
- [ ] **Step 2:** fails. **Step 3: client**
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Data\ApiProductResult;

final class EbayBrowseClient
{
    public function fetchByLegacyId(string $legacyId): ?ApiProductResult
    {
        $cfg = (array) config('price-intelligence.marketplaces.ebay', []);
        if (empty($cfg['client_id']) || empty($cfg['client_secret'])) {
            return null;
        }

        $endpoint = rtrim((string) ($cfg['endpoint'] ?? 'https://api.ebay.com'), '/');
        $marketplaceId = (string) ($cfg['marketplace_id'] ?? 'EBAY_US');

        $token = $this->token($endpoint, (string) $cfg['client_id'], (string) $cfg['client_secret']);
        if ($token === null) {
            return null;
        }

        $response = Http::withToken($token)
            ->withHeaders(['X-EBAY-C-MARKETPLACE-ID' => $marketplaceId])
            ->timeout(20)
            ->get($endpoint.'/buy/browse/v1/item/getItemByLegacyId', ['legacy_item_id' => $legacyId]);

        if (! $response->successful()) {
            return null;
        }

        $value = $response->json('price.value');
        $priceCents = is_numeric($value) ? (int) round((float) $value * 100) : null;
        $status = $response->json('estimatedAvailabilities.0.estimatedAvailabilityStatus');
        $image = $response->json('image.imageUrl');

        return new ApiProductResult(
            priceCents: $priceCents,
            currency: is_string($response->json('price.currency')) ? $response->json('price.currency') : null,
            available: $priceCents !== null && $status !== 'OUT_OF_STOCK',
            title: is_string($response->json('title')) ? $response->json('title') : null,
            brand: is_string($response->json('brand')) ? $response->json('brand') : null,
            images: is_string($image) ? [$image] : [],
            externalRef: $legacyId,
        );
    }

    private function token(string $endpoint, string $clientId, string $clientSecret): ?string
    {
        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()->timeout(20)
            ->post($endpoint.'/identity/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
                'scope' => 'https://api.ebay.com/oauth/api_scope',
            ]);

        $token = $response->successful() ? $response->json('access_token') : null;

        return is_string($token) ? $token : null;
    }
}
```
- [ ] **Step 4: rewrite `EbayAdapter`** (mirror AmazonAdapter): inject `EbayBrowseClient`, `configKey()='ebay'`, keep `externalRef()` regex, `apiFetch()` → `$client->fetchByLegacyId($competitorProduct->external_ref ?? $this->externalRef($url))?->toSnapshot($url)`. Run tests. Commit `feat(b2): add eBay Browse client + adapter`.

---

## Task 8: `SerpShoppingClient` + Google Shopping adapter

**Files:** Create `src/Services/Scraping/Marketplaces/Api/SerpShoppingClient.php`; rewrite `GoogleShoppingAdapter.php`; Test `tests/Feature/Marketplaces/SerpShoppingClientTest.php`

SerpApi product: `GET {endpoint}?engine=google_product&product_id=<id>&gl=&hl=&api_key=`. Map `product_results.prices`/`pricing` — use `sellers_results.online_sellers[0].base_price` or `product_results.prices[0]`. Pragmatic: read `pricing` major-unit string (strip currency symbols) → cents; `product_results.title`; first `media[0].link` image.

- [ ] **Step 1: Failing test** (Http::fake `serpapi.com/*`, returns `{product_results:{title:'Pixel 8', pricing:'€699.00'}, ...}`; assert priceCents 69900, title, and that `api_key`/`product_id` sent). Returns null when key absent.
- [ ] **Step 2-3:** client reads `marketplaces.google_shopping.serp.{key,endpoint,gl,hl}`; null when key empty; `GET` with query; parse price via a small regex `'/([0-9][0-9.,]*)/'` then reuse `PriceParser::parse()` if available, else `(int) round(floatval(str_replace([',',' '],['',''],...)) * 100)` — **use the existing `Padosoft\PriceIntelligence\Support\Pricing\PriceParser`** (verify class path under src/Support; the HtmlProductExtractor already uses it) to stay consistent with EU formatting. Map currency from a leading symbol (€→EUR, £→GBP, $→USD).
- [ ] **Step 4: rewrite `GoogleShoppingAdapter`** like the others (`configKey()='google_shopping'`, keep regex, `apiFetch` uses the product id from `externalRef`). Commit `feat(b2): add Google Shopping SERP client + adapter`.

> Verify `PriceParser` location/signature before writing (Explore noted `HtmlProductExtractor` uses `PriceParser::parse()`); if it returns `[cents, currency]` reuse it, else parse inline. Record the real signature in LESSON.md.

---

## Task 9: `FarfetchClient` + `FarfetchAdapter` (multi-driver)

**Files:** Create `src/Services/Scraping/Marketplaces/Api/FarfetchClient.php`, `src/Services/Scraping/Marketplaces/FarfetchAdapter.php`; Test `tests/Feature/Marketplaces/FarfetchClientTest.php`

Drivers: `scrape` (default — handled by `AbstractApiAdapter` fallback to the JSON-LD scraper, which already works for farfetch.com product pages), `retailed` (GET `{retailed.endpoint}?url=<product url>` or `?id=`, header `x-api-key`), `apify` (POST `{apify.endpoint}/acts/{actor}/run-sync-get-dataset-items?token=` with `{startUrls:[{url}]}` → array of items).

- [ ] **Step 1: Failing test** — two cases: `retailed` (Http::fake `app.retailed.io/*` → `{title, brand, price:{amount:'450.00',currency:'EUR'}, images:[..], availability:'in_stock'}`; assert mapping + `x-api-key` header sent); `apify` (Http::fake `api.apify.com/*` → `[{title, priceValue:450, currency:'EUR', ...}]`); both return null without their key/token.
- [ ] **Step 2-3:** `FarfetchClient` with `fetchViaRetailed(string $url): ?ApiProductResult` and `fetchViaApify(string $url): ?ApiProductResult`, each null when its key/token missing. Parse major-unit prices → cents.
- [ ] **Step 4: `FarfetchAdapter extends AbstractApiAdapter`**: `code()` → `AdapterCode::Farfetch`; `configKey()='farfetch'`; `externalRef()` extracts the numeric id from `farfetch.com/.../item-<id>.aspx` (`#/(?:shopping/.*?)?(\d{6,})\.aspx#` — verify against a real URL; fall back to `storeid`/`pid` query). `apiFetch()` switches on driver `retailed`/`apify` → `FarfetchClient`; scrape is the inherited fallback. Commit `feat(b2): add Farfetch multi-driver adapter (scrape default + retailed/apify)`.

---

## Task 10: Factory + CompetitorSourceResolver wiring

**Files:** Modify `src/Services/Scraping/MarketplaceAdapterFactory.php`, `src/Services/Discovery/CompetitorSourceResolver.php`; Test `tests/Feature/Marketplaces/MarketplaceApiAdapterTest.php`

- [ ] **Step 1: Factory** — construct the API adapters with their clients (resolved from the container) and add the Farfetch case:
```php
return match ($code) {
    AdapterCode::Amazon => new AmazonAdapter($this->scraper, app(AmazonSpApiClient::class), app(KeepaClient::class)),
    AdapterCode::Ebay => new EbayAdapter($this->scraper, app(EbayBrowseClient::class)),
    AdapterCode::GoogleShopping => new GoogleShoppingAdapter($this->scraper, app(SerpShoppingClient::class)),
    AdapterCode::Farfetch => new FarfetchAdapter($this->scraper, app(FarfetchClient::class)),
    AdapterCode::Idealo => new IdealoAdapter($this->scraper),
    AdapterCode::Trovaprezzi => new TrovaprezziAdapter($this->scraper),
    AdapterCode::Generic => new GenericAdapter($this->scraper),
};
```
(Keep the existing `config('price-intelligence.adapters')` override block above the match.)

- [ ] **Step 2: CompetitorSourceResolver** — read the file; add host→AdapterCode mapping so `farfetch.com` and `*.farfetch.com` resolve to `AdapterCode::Farfetch` (mirror how amazon/ebay hosts are mapped). If resolution is regex/needle-based, add `farfetch` alongside.

- [ ] **Step 3: Integration test** (`MarketplaceApiAdapterTest`): through `ScrapeService::scrapeAndStore`:
  - amazon `driver=keepa` + Keepa key set + `Http::fake(['api.keepa.com/*'=>...])` → PriceObservation persisted from the API (assert price), `FetchLog.driver='amazon'`.
  - amazon `driver=auto` + **no creds** + `Http::fake(['amazon.it/*'=>'<jsonld>'])` → falls back to scrape (assert price comes from the HTML fixture).
  - farfetch host resolves to Farfetch adapter; `driver=scrape` default → scrape path used.
- [ ] **Step 4:** Run `tests/Feature/Marketplaces` + existing `tests/Feature/MarketplaceAdapterTest.php` + `ScrapeServiceTest.php` (no regression). Commit `feat(b2): wire API adapters + Farfetch in factory + source resolver`.

---

## Task 11: Opt-in live marketplace suite

**Files:** Create `tests/Live/LiveMarketplaceSmokeTest.php`

- [ ] **Step 1:** `#[Group('live')]`, `setUp` skips unless `env('PI_LIVE_MARKETPLACE')==='1'`; one test per provider that has its creds present (skip individually otherwise) calling the client and asserting a non-null result. Not in any `<testsuite>` → excluded from CI. Run by path → all skipped. Commit `test(b2): opt-in live marketplace smoke suite`.

---

## Task 12: Gate, docs, Copilot loop, release v1.4.0

- [ ] **Step 1:** Full local gate — `composer validate --strict`, `vendor\bin\phpunit`, `vendor\bin\pint --test <changed files>`, `vendor\bin\phpstan analyse --memory-limit=1G`. Restore line-ending-only churn on untouched files before staging.
- [ ] **Step 2:** `composer.json` `suggest`: add notes for Keepa / eBay / SerpApi / retailed / apify env keys (no hard deps). 
- [ ] **Step 3:** Update `docs/PROGRESS.md` (B2 done, B3 next), append B2 lessons to `docs/LESSON.md`, add `CHANGELOG.md` `[1.4.0]` entry, README marketplace-driver subsection.
- [ ] **Step 4:** Local Copilot `/review` loop until NO ISSUES; re-run full gate (it edits + skips build).
- [ ] **Step 5:** Commit, push `feat/phase-b2-marketplace-adapters`, open PR, request Copilot review.
- [ ] **Step 6:** Remote loop: CI green + Copilot zero actionable (verify findings; push back when wrong). Fix → push → re-check.
- [ ] **Step 7:** Squash-merge + delete branch, sync main, tag **v1.4.0**, GitHub release. Advance to **B3**.

---

## Self-Review (against spec §B2)
- Amazon SP-API + Keepa → Tasks 4/5/6. ✅  eBay Browse → Task 7. ✅  Google Shopping SERP → Task 8. ✅  Farfetch `scrape`+`retailed`+`apify` → Task 9. ✅  Generic scraper fallback → `AbstractApiAdapter::fetch()` → `parent::fetch()` (Task 3). ✅
- "config-selectable; missing creds → graceful fallback to scrape" → driver semantics + `auto` + null-on-missing-creds in every client. ✅  "OAuth/credential handling per provider" → LWA (Amazon), client-credentials (eBay), api_key (SERP/Keepa/retailed), token (apify). ✅  "rate-limit + robots respected" → unchanged; `ScrapeService`/`DomainRateLimiter` still gate the scrape path; API calls are first-party and not subject to robots. ✅
- "each adapter maps a real (fixtured) response to the normalized ProductSnapshot" → `ApiProductResult::toSnapshot()` + per-client `Http::fake` tests. ✅  "opt-in live suite" → Task 11. ✅
- **Placeholder scan:** the three "verify before writing" notes (PriceParser signature, Farfetch URL id regex, CompetitorSourceResolver mapping shape) name the exact file + adaptation — not vague. **Type consistency:** `ApiProductResult` fields + `toSnapshot()` used uniformly by all clients/adapters; `AbstractApiAdapter::apiFetch(): ?ProductSnapshot` matches every override.
