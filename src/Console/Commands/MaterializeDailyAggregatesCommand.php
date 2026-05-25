<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Padosoft\PriceIntelligence\Models\PriceDailyAggregate;
use Padosoft\PriceIntelligence\Models\PriceObservation;

/**
 * Materialize daily price aggregates (min/max/avg/samples) from raw observations.
 * The reduction happens in SQL (GROUP BY), so memory is bounded by the number of distinct
 * (tenant, competitor_product, currency) groups for the day — not the raw row count.
 */
final class MaterializeDailyAggregatesCommand extends Command
{
    protected $signature = 'piprice:aggregates:daily {--date= : ISO date to aggregate (default: yesterday)}';

    protected $description = 'Materialize daily price aggregates (min/max/avg) from raw observations.';

    public function handle(): int
    {
        $option = $this->option('date');
        $day = is_string($option) && $option !== ''
            ? Carbon::parse($option)->toDateString()
            : now()->subDay()->toDateString();

        $dayStart = Carbon::parse($day)->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();

        PriceObservation::query()
            ->where('captured_at', '>=', $dayStart)
            ->where('captured_at', '<', $dayEnd)
            ->whereNotNull('price_cents')
            ->select('tenant_id', 'competitor_product_id', 'currency')
            ->selectRaw('MIN(price_cents) as min_p, MAX(price_cents) as max_p, ROUND(AVG(price_cents)) as avg_p, COUNT(*) as samples')
            ->groupBy('tenant_id', 'competitor_product_id', 'currency')
            ->cursor()
            ->each(function (PriceObservation $row) use ($day): void {
                // Coalesce a null observation currency to the table's '' sentinel so the unique key
                // (competitor_product_id, day, currency) has no nullable component (keeps idempotency).
                $currency = is_string($row->currency) ? $row->currency : '';

                PriceDailyAggregate::query()->updateOrCreate(
                    ['competitor_product_id' => (int) $row->competitor_product_id, 'day' => $day, 'currency' => $currency],
                    [
                        'tenant_id' => $row->tenant_id,
                        'min_price_cents' => (int) $row->getAttribute('min_p'),
                        'max_price_cents' => (int) $row->getAttribute('max_p'),
                        'avg_price_cents' => (int) $row->getAttribute('avg_p'),
                        'samples' => (int) $row->getAttribute('samples'),
                    ],
                );
            });

        $this->info('Daily aggregates materialized for '.$day.'.');

        return self::SUCCESS;
    }
}
