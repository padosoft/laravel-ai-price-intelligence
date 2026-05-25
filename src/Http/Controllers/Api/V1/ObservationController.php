<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\ContentSnapshot;
use Padosoft\PriceIntelligence\Models\PriceObservation;
use Padosoft\PriceIntelligence\Models\PromoObservation;
use Padosoft\PriceIntelligence\Models\StockObservation;

/**
 * Read side of competitor products: the time-series price history, the listings index
 * (admin Competitors screen) and the single-listing detail (admin Competitor Detail screen).
 * Writes — manually attaching a URL to a target — live in MatchController as a matching action.
 * All queries are tenant-scoped.
 */
final class ObservationController
{
    public function prices(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'competitor_product_id' => ['nullable', 'integer'],
            'host' => ['nullable', 'string', 'max:191'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $prices = PriceObservation::query()
            ->when($request->filled('competitor_product_id'), fn ($q) => $q->where('competitor_product_id', $request->integer('competitor_product_id')))
            ->when($request->filled('host'), fn ($q) => $q->whereHas('competitorProduct.source', fn ($s) => $s->where('host', $request->string('host')->toString())))
            ->when($request->filled('from'), fn ($q) => $q->where('captured_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('captured_at', '<=', $request->date('to')))
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 100));

        return response()->json($prices);
    }

    public function stock(Request $request): JsonResponse
    {
        $request->validate([
            'competitor_product_id' => ['nullable', 'integer'],
            'host' => ['nullable', 'string', 'max:191'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $rows = StockObservation::query()
            ->when($request->filled('competitor_product_id'), fn ($q) => $q->where('competitor_product_id', $request->integer('competitor_product_id')))
            ->when($request->filled('host'), fn ($q) => $q->whereHas('competitorProduct.source', fn ($s) => $s->where('host', $request->string('host')->toString())))
            ->when($request->filled('from'), fn ($q) => $q->where('captured_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('captured_at', '<=', $request->date('to')))
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 100));

        return response()->json($rows);
    }

    public function promos(Request $request): JsonResponse
    {
        $request->validate([
            'competitor_product_id' => ['nullable', 'integer'],
            'host' => ['nullable', 'string', 'max:191'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $rows = PromoObservation::query()
            ->when($request->filled('competitor_product_id'), fn ($q) => $q->where('competitor_product_id', $request->integer('competitor_product_id')))
            ->when($request->filled('host'), fn ($q) => $q->whereHas('competitorProduct.source', fn ($s) => $s->where('host', $request->string('host')->toString())))
            ->when($request->filled('from'), fn ($q) => $q->where('captured_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('captured_at', '<=', $request->date('to')))
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 100));

        return response()->json($rows);
    }

    /**
     * Competitor listings the admin's Competitors screen renders — confirmed by default,
     * narrowable to any match_status via the `status` filter (and by host/target/product).
     * Each row carries the matched product (via target), the source host, and the latest
     * price observation so the UI can compute the delta versus our retail price without
     * N extra requests.
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
                'latest_price' => $this->latestFor(PriceObservation::class, $id),
                'latest_stock' => $this->latestFor(StockObservation::class, $id),
                'latest_promo' => $this->latestFor(PromoObservation::class, $id),
                'latest_content' => $this->latestFor(ContentSnapshot::class, $id),
            ],
        ]);
    }

    /**
     * Latest observation row for a competitor product, tie-broken on id so a bulk scrape
     * that lands several rows on the same captured_at resolves to the same "latest" row the
     * listings index shows via CompetitorProduct::latestPrice().
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel|null
     */
    private function latestFor(string $model, int $competitorProductId): ?Model
    {
        return $model::query()
            ->where('competitor_product_id', $competitorProductId)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();
    }
}
