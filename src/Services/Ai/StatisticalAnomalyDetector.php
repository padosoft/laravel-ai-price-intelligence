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

        // Outlier check on DETRENDED residuals so a normal continuation of a steady
        // up/down trend is NOT flagged. Fit a linear trend, predict the next point,
        // and flag when the deviation exceeds ~1.96 std-dev of historical residuals.
        [$slope, $intercept, $residualStd] = $this->linearFit($series);
        $predicted = $intercept + $slope * count($series);
        $deviation = abs($currentCents - $predicted);

        // Floor the residual std at 0.5% of the predicted level so a perfectly
        // linear history (residualStd == 0) still flags a genuine break, while
        // noisy histories keep their own (larger) tolerance.
        $effectiveStd = max($residualStd, abs($predicted) * 0.005);

        if ($effectiveStd > 0.0 && $deviation > 1.96 * $effectiveStd) {
            $decisions[] = [
                'type' => 'outlier',
                'severity' => Severity::Medium->value,
                'evidence' => [
                    'current_cents' => $currentCents,
                    'expected_cents' => (int) round($predicted),
                    'deviation_cents' => (int) round($deviation),
                ],
            ];
        }

        return $decisions;
    }

    /**
     * Ordinary least squares fit returning [slope, intercept, residualStdDev].
     *
     * @param  array<int, int>  $y
     * @return array{0: float, 1: float, 2: float}
     */
    private function linearFit(array $y): array
    {
        $n = count($y);
        $sumX = $sumY = $sumXY = $sumXX = 0.0;

        foreach ($y as $x => $value) {
            $sumX += $x;
            $sumY += $value;
            $sumXY += $x * $value;
            $sumXX += $x * $x;
        }

        $denom = ($n * $sumXX) - ($sumX * $sumX);
        $slope = $denom === 0.0 ? 0.0 : (($n * $sumXY) - ($sumX * $sumY)) / $denom;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        $sumSq = 0.0;
        foreach ($y as $x => $value) {
            $sumSq += ($value - ($intercept + $slope * $x)) ** 2;
        }

        return [$slope, $intercept, $n > 2 ? sqrt($sumSq / ($n - 2)) : 0.0];
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
