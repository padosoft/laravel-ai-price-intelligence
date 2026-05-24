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
        $prices = PriceObservation::query()
            ->when($request->filled('competitor_product_id'), fn ($q) => $q->where('competitor_product_id', $request->integer('competitor_product_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('captured_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('captured_at', '<=', $request->date('to')))
            ->orderByDesc('captured_at')
            ->cursorPaginate((int) $request->integer('per_page', 100));

        return response()->json($prices);
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
