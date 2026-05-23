<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\AlertController;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\CatalogController;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\MatchController;
use Padosoft\PriceIntelligence\Http\Controllers\Api\V1\TargetController;
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
    Route::get('/catalog/products', [CatalogController::class, 'index'])->name('price-intelligence.catalog.index');
    Route::get('/catalog/products/{id}', [CatalogController::class, 'show'])->whereNumber('id')->name('price-intelligence.catalog.show');
    Route::post('/catalog/products:bulk', [CatalogController::class, 'bulkUpsert'])->name('price-intelligence.catalog.bulk');
    Route::post('/catalog/products:csv', [CatalogController::class, 'importCsv'])->name('price-intelligence.catalog.csv');
    Route::delete('/catalog/products/{id}', [CatalogController::class, 'destroy'])->whereNumber('id')->name('price-intelligence.catalog.destroy');

    Route::get('/targets', [TargetController::class, 'index'])->name('price-intelligence.targets.index');
    Route::post('/targets', [TargetController::class, 'store'])->name('price-intelligence.targets.store');
    Route::patch('/targets/{id}', [TargetController::class, 'update'])->whereNumber('id')->name('price-intelligence.targets.update');
    Route::post('/targets/{id}/discover:now', [MatchController::class, 'discoverNow'])->whereNumber('id')->name('price-intelligence.targets.discover');

    Route::get('/matches', [MatchController::class, 'index'])->name('price-intelligence.matches.index');
    Route::post('/matches/{id}/approve', [MatchController::class, 'approve'])->whereNumber('id')->name('price-intelligence.matches.approve');
    Route::post('/matches/{id}/reject', [MatchController::class, 'reject'])->whereNumber('id')->name('price-intelligence.matches.reject');
    Route::post('/competitor-products', [MatchController::class, 'storeCompetitorProduct'])->name('price-intelligence.competitor-products.store');

    Route::get('/alerts', [AlertController::class, 'index'])->name('price-intelligence.alerts.index');
    Route::post('/alerts/{id}/ack', [AlertController::class, 'acknowledge'])->whereNumber('id')->name('price-intelligence.alerts.ack');

    Route::get('/webhook-subscriptions', [WebhookController::class, 'index'])->name('price-intelligence.webhooks.index');
    Route::post('/webhook-subscriptions', [WebhookController::class, 'store'])->name('price-intelligence.webhooks.store');
    Route::patch('/webhook-subscriptions/{id}', [WebhookController::class, 'update'])->whereNumber('id')->name('price-intelligence.webhooks.update');
    Route::delete('/webhook-subscriptions/{id}', [WebhookController::class, 'destroy'])->whereNumber('id')->name('price-intelligence.webhooks.destroy');
    Route::post('/webhook-subscriptions/{id}/test', [WebhookController::class, 'test'])->whereNumber('id')->name('price-intelligence.webhooks.test');
});
