<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\AnomalyDetectorInterface;
use Padosoft\PriceIntelligence\Enums\Severity;

/**
 * Statistical anomaly detection over a price history:
 *  - outlier: current price below p5 or above p95 of history
 *  - price_error: current price is a tiny fraction of the historical median
 *    (likely a data-entry / "civetta" bait error worth exploiting or ignoring)
 * Pure and deterministic.
 */
final class StatisticalAnomalyDetector implements AnomalyDetectorInterface
{
    public function __construct(
        private readonly int $minObservations = 8,
        private readonly float $priceErrorRatio = 0.1,
    ) {
    }

    /**
     * @param  array<int, int>  $priceSeriesCents
     * @return array<int, array{type: string, severity: string, evidence: array<string, mixed>}>
     */
    public function detect(array $priceSeriesCents, int $currentCents): array
    {
        $series = array_values(array_map('intval', $priceSeriesCents));

        if (count($series) < $this->minObservations) {
            return [];
        }

        $decisions = [];
        $median = $this->percentile($series, 50);

        // Price error / civetta: current price is an implausibly small fraction of median.
        if ($median > 0 && $currentCents > 0 && $currentCents < $median * $this->priceErrorRatio) {
            $decisions[] = [
                'type' => 'price_error',
                'severity' => Severity::High->value,
                'evidence' => ['current_cents' => $currentCents, 'median_cents' => $median],
            ];

            return $decisions; // a price error subsumes the outlier signal
        }

        $p5 = $this->percentile($series, 5);
        $p95 = $this->percentile($series, 95);

        if ($currentCents < $p5 || $currentCents > $p95) {
            $decisions[] = [
                'type' => 'outlier',
                'severity' => Severity::Medium->value,
                'evidence' => ['current_cents' => $currentCents, 'p5_cents' => $p5, 'p95_cents' => $p95],
            ];
        }

        return $decisions;
    }

    /**
     * Linear-interpolated percentile.
     *
     * @param  array<int, int>  $values
     */
    private function percentile(array $values, float $p): int
    {
        $sorted = $values;
        sort($sorted);
        $count = count($sorted);

        if ($count === 1) {
            return $sorted[0];
        }

        $rank = ($p / 100) * ($count - 1);
        $low = (int) floor($rank);
        $high = (int) ceil($rank);
        $frac = $rank - $low;

        return (int) round($sorted[$low] + ($sorted[$high] - $sorted[$low]) * $frac);
    }
}
