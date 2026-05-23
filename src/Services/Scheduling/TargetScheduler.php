<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scheduling;

use Illuminate\Support\Carbon;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Jobs\ScrapeCompetitorProductJob;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;

/**
 * Finds monitoring targets due for a check (next_check_at <= now, active),
 * dispatches a scrape job for each confirmed competitor product, then advances
 * next_check_at using the adaptive backoff. Crosses tenants: callers run it
 * inside a tenant-less context (no global scope) or per tenant.
 */
final class TargetScheduler
{
    public function __construct(
        private readonly AdaptiveBackoff $backoff,
    ) {
    }

    /**
     * @return int number of scrape jobs dispatched
     */
    public function dispatchDue(int $limit = 1000, ?Carbon $now = null): int
    {
        $now ??= now();
        $dispatched = 0;

        $targets = MonitoringTarget::query()
            ->where('status', 'active')
            ->where(function ($q) use ($now): void {
                $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', $now);
            })
            ->orderBy('priority')
            ->limit($limit)
            ->get();

        foreach ($targets as $target) {
            $competitors = CompetitorProduct::query()
                ->where('monitoring_target_id', $target->id)
                ->where('match_status', MatchStatus::Confirmed->value)
                ->get();

            foreach ($competitors as $competitor) {
                ScrapeCompetitorProductJob::dispatch(
                    $competitor->id,
                    $target->tenant_id,
                    ['country' => $target->country, 'locale' => $target->locale],
                );
                $dispatched++;
            }

            $this->reschedule($target, $now);
        }

        return $dispatched;
    }

    private function reschedule(MonitoringTarget $target, Carbon $now): void
    {
        // Stability/significant-change inputs are refined in Phase 7 (diffing);
        // for now treat as neutral so cadence stays at the configured frequency.
        $factor = $this->backoff->nextFactor(
            currentFactor: (float) ($target->backoff_factor ?: 1.0),
            frequency: $target->frequency_preset,
            stabilityScore: 0.5,
            lastChangeSignificant: false,
        );

        $nextEpoch = $this->backoff->nextRunTimestamp($now->getTimestamp(), $target->frequency_preset, $factor);

        $target->forceFill([
            'last_check_at' => $now,
            'next_check_at' => Carbon::createFromTimestamp($nextEpoch),
            'backoff_factor' => $factor,
        ])->save();
    }
}
