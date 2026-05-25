<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Models\PriceObservation;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Services\Export\CsvStreamWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streamed bulk CSV export for the admin. Uses Eloquent cursor() + streamDownload so large
 * catalogs / observation histories export without OOM (no queued job / temp file needed).
 */
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

        return response()->streamDownload(
            $this->writer->callback($header, $rows),
            'catalog.csv',
            ['Content-Type' => 'text/csv'],
        );
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
                yield [$o->competitor_product_id, $o->captured_at->toIso8601String(), $o->price_cents, $o->currency, $o->price_base_cents, $o->available ? 1 : 0];
            }
        })();

        return response()->streamDownload(
            $this->writer->callback($header, $rows),
            'prices.csv',
            ['Content-Type' => 'text/csv'],
        );
    }
}
