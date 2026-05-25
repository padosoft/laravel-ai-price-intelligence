<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\CompetitorSource;
use Padosoft\PriceIntelligence\Models\Product;

/**
 * SQL-level facet counts for the admin (host chips on Competitors, category tree).
 * Counts are computed in the database (or via a lean cursor) — never page-1 only.
 */
final class FacetController
{
    public function hosts(Request $request): JsonResponse
    {
        $cp = (new CompetitorProduct)->getTable();
        $src = (new CompetitorSource)->getTable();

        $rows = CompetitorProduct::query()
            ->where($cp.'.match_status', MatchStatus::Confirmed->value)
            ->join($src.' as src', 'src.id', '=', $cp.'.competitor_source_id')
            ->selectRaw('src.host as host, COUNT(*) as count')
            ->groupBy('src.host')
            ->orderByDesc('count')
            ->get()
            ->map(fn (CompetitorProduct $r): array => ['host' => (string) $r->getAttribute('host'), 'count' => (int) $r->getAttribute('count')])
            ->all();

        return response()->json(['data' => $rows]);
    }

    public function categories(Request $request): JsonResponse
    {
        // categories is a JSON array per product; aggregate in PHP over a streaming cursor() —
        // a single unbuffered query (constant memory, no OFFSET scans), so runtime stays predictable
        // as the catalog grows. Category cardinality is low, so the $counts map stays small.
        $counts = [];
        Product::query()->select('categories')->cursor()->each(function (Product $p) use (&$counts): void {
            foreach ((array) $p->categories as $cat) {
                if (is_string($cat) && $cat !== '') {
                    $counts[$cat] = ($counts[$cat] ?? 0) + 1;
                }
            }
        });
        arsort($counts);

        $data = array_map(
            static fn (string $cat, int $count): array => ['category' => $cat, 'count' => $count],
            array_keys($counts),
            array_values($counts),
        );

        return response()->json(['data' => $data]);
    }
}
