<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scheduling;

use Padosoft\PriceIntelligence\Enums\Frequency;

/**
 * Computes the next backoff factor for a target based on price stability:
 *  - very stable (no recent changes) -> slow down (factor up to max)
 *  - just changed significantly       -> speed up (factor 0.5)
 *  - otherwise                         -> drift back toward 1.0
 * Weekly cadence is never slowed further. Pure & unit-testable.
 */
final class AdaptiveBackoff
{
    public function __construct(
        private readonly bool $enabled = true,
        private readonly float $maxFactor = 4.0,
    ) {}

    /**
     * @param  float  $stabilityScore  fraction in [0,1] of recent observations with no price change
     * @param  bool  $lastChangeSignificant  whether the most recent observation changed > threshold
     */
    public function nextFactor(
        float $currentFactor,
        Frequency $frequency,
        float $stabilityScore,
        bool $lastChangeSignificant,
    ): float {
        if (! $this->enabled) {
            return 1.0;
        }

        if ($lastChangeSignificant) {
            return 0.5;
        }

        if ($frequency === Frequency::Weekly) {
            return 1.0;
        }

        if ($stabilityScore > 0.95) {
            return min($currentFactor <= 0 ? 1.0 : $currentFactor * 2, $this->maxFactor);
        }

        // Drift back toward normal cadence.
        return max(1.0, $currentFactor / 2);
    }

    public function nextRunTimestamp(int $lastCheckEpoch, Frequency $frequency, float $factor, int $fallbackSeconds = 86400): int
    {
        $base = $frequency->seconds() ?? $fallbackSeconds;

        return $lastCheckEpoch + (int) round($base * max(0.25, $factor));
    }
}
