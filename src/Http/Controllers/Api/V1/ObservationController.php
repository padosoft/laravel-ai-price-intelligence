<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\ContentSnapshot;
use Padosoft\PriceIntelligence\Models\PriceObservation;
use Padosoft\PriceIntelligence\Models\PromoObservation;
use Padosoft\PriceIntelligence\Models\StockObservation;

/**
 * Time-series read endpoints (price history) and the competitor-product detail view
 * the admin's Competitor Detail screen consumes. All queries are tenant-scoped.
 */
final class ObservationController
{
    public function prices(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'competitor_product_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $prices = PriceObservation::query()
            ->when($request->filled('competitor_product_id'), fn ($q) => $q->where('competitor_product_id', $request->integer('competitor_product_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('captured_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('captured_at', '<=', $request->date('to')))
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 100));

        return response()->json($prices);
    }

    /**
     * Confirmed competitor listings the admin's Competitors screen renders. Each row carries
     * the matched product (via target), the source host, and the latest price observation so
     * the UI can compute the delta versus our retail price without N extra requests.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'in:confirmed,suggested,rejected,dead'],
            'host' => ['nullable', 'string', 'max:191'],
            'monitoring_target_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $competitors = CompetitorProduct::query()
            ->with(['target.product', 'source', 'latestPrice'])
            ->where('match_status', $request->string('status', 'confirmed')->toString())
            ->when($request->filled('monitoring_target_id'), fn ($q) => $q->where('monitoring_target_id', $request->integer('monitoring_target_id')))
            ->when($request->filled('host'), fn ($q) => $q->whereHas('source', fn ($s) => $s->where('host', $request->string('host')->toString())))
            ->when($request->filled('product_id'), fn ($q) => $q->whereHas('target', fn ($t) => $t->where('product_id', $request->integer('product_id'))))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($competitors);
    }

    public function show(int $id): JsonResponse
    {
        $competitor = CompetitorProduct::query()->with(['target', 'source'])->findOrFail($id);

        return response()->json([
            'data' => [
                'competitor_product' => $competitor,
                'latest_price' => PriceObservation::query()->where('competitor_product_id', $id)->latest('captured_at')->first(),
                'latest_stock' => StockObservation::query()->where('competitor_product_id', $id)->latest('captured_at')->first(),
                'latest_promo' => PromoObservation::query()->where('competitor_product_id', $id)->latest('captured_at')->first(),
                'latest_content' => ContentSnapshot::query()->where('competitor_product_id', $id)->latest('captured_at')->first(),
            ],
        ]);
    }
}
