<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\AlertController;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\ApiKeyController;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\AuditController;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\CatalogController;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\IntelligenceController;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\MatchController;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\ObservationController;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\RuleController;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\TargetController;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\TenantController;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\WebhookController;
use Padosoft\PriceIntelligence\Http\Middleware\ResolveTenant;

/*
|--------------------------------------------------------------------------
| Price Intelligence API (v1)
|--------------------------------------------------------------------------
| Mounted by the service provider under config('price-intelligence.api.prefix').
| Endpoints are added per build phase. Health check is always available.
*/

Route::get('/health', static fn (): array => [
    'data' => ['status' => 'ok', 'package' => 'laravel-ai-price-intelligence'],
])->name('price-intelligence.health');

Route::middleware(ResolveTenant::class)->group(function (): void {
    Route::get('/tenants/me', [TenantController::class, 'me'])->name('price-intelligence.tenants.me');

    Route::get('/catalog/products', [CatalogController::class, 'index'])->name('price-intelligence.catalog.index');
    Route::get('/catalog/products/{id}', [CatalogController::class, 'show'])->whereNumber('id')->name('price-intelligence.catalog.show');
    Route::post('/catalog/products:bulk', [CatalogController::class, 'bulkUpsert'])->name('price-intelligence.catalog.bulk');
    Route::post('/catalog/products:csv', [CatalogController::class, 'importCsv'])->name('price-intelligence.catalog.csv');
    Route::delete('/catalog/products/{id}', [CatalogController::class, 'destroy'])->whereNumber('id')->name('price-intelligence.catalog.destroy');

    Route::get('/targets', [TargetController::class, 'index'])->name('price-intelligence.targets.index');
    Route::post('/targets', [TargetController::class, 'store'])->name('price-intelligence.targets.store');
    Route::patch('/targets/{id}', [TargetController::class, 'update'])->whereNumber('id')->name('price-intelligence.targets.update');
    Route::post('/targets/{id}/discover:now', [MatchController::class, 'discoverNow'])->whereNumber('id')->name('price-intelligence.targets.discover');
    Route::post('/targets/{id}/scrape:now', [TargetController::class, 'scrapeNow'])->whereNumber('id')->name('price-intelligence.targets.scrape');

    Route::get('/matches', [MatchController::class, 'index'])->name('price-intelligence.matches.index');
    Route::post('/matches/{id}/approve', [MatchController::class, 'approve'])->whereNumber('id')->name('price-intelligence.matches.approve');
    Route::post('/matches/{id}/reject', [MatchController::class, 'reject'])->whereNumber('id')->name('price-intelligence.matches.reject');
    Route::post('/competitor-products', [MatchController::class, 'storeCompetitorProduct'])->name('price-intelligence.competitor-products.store');

    Route::get('/observations/prices', [ObservationController::class, 'prices'])->name('price-intelligence.observations.prices');
    Route::get('/competitor-products/{id}', [ObservationController::class, 'show'])->whereNumber('id')->name('price-intelligence.competitor-products.show');

    Route::get('/forecasts', [IntelligenceController::class, 'forecasts'])->name('price-intelligence.forecasts.index');
    Route::get('/anomalies', [IntelligenceController::class, 'anomalies'])->name('price-intelligence.anomalies.index');
    Route::get('/reviews', [IntelligenceController::class, 'reviews'])->name('price-intelligence.reviews.index');

    Route::get('/alerts', [AlertController::class, 'index'])->name('price-intelligence.alerts.index');
    Route::post('/alerts/{id}/ack', [AlertController::class, 'acknowledge'])->whereNumber('id')->name('price-intelligence.alerts.ack');

    Route::get('/rules', [RuleController::class, 'index'])->name('price-intelligence.rules.index');
    Route::post('/rules', [RuleController::class, 'store'])->name('price-intelligence.rules.store');
    Route::patch('/rules/{id}', [RuleController::class, 'update'])->whereNumber('id')->name('price-intelligence.rules.update');
    Route::post('/rules/{id}/simulate', [RuleController::class, 'simulate'])->whereNumber('id')->name('price-intelligence.rules.simulate');
    Route::delete('/rules/{id}', [RuleController::class, 'destroy'])->whereNumber('id')->name('price-intelligence.rules.destroy');
    Route::get('/rule-decisions', [RuleController::class, 'decisions'])->name('price-intelligence.rule-decisions.index');

    Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('price-intelligence.api-keys.index');
    Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('price-intelligence.api-keys.store');
    Route::delete('/api-keys/{id}', [ApiKeyController::class, 'revoke'])->whereNumber('id')->name('price-intelligence.api-keys.revoke');

    Route::get('/audit/fetch-logs', [AuditController::class, 'fetchLogs'])->name('price-intelligence.audit.fetch-logs');

    Route::get('/webhook-subscriptions', [WebhookController::class, 'index'])->name('price-intelligence.webhooks.index');
    Route::post('/webhook-subscriptions', [WebhookController::class, 'store'])->name('price-intelligence.webhooks.store');
    Route::patch('/webhook-subscriptions/{id}', [WebhookController::class, 'update'])->whereNumber('id')->name('price-intelligence.webhooks.update');
    Route::delete('/webhook-subscriptions/{id}', [WebhookController::class, 'destroy'])->whereNumber('id')->name('price-intelligence.webhooks.destroy');
    Route::post('/webhook-subscriptions/{id}/test', [WebhookController::class, 'test'])->whereNumber('id')->name('price-intelligence.webhooks.test');
});
