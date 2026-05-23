<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\ForecastProviderInterface;
use Padosoft\PriceIntelligence\Data\ForecastResult;

/**
 * Zero-dependency price forecaster: ordinary-least-squares linear trend projected
 * to the horizon, with a confidence interval derived from the residual standard
 * deviation. Deterministic and unit-testable; no external service.
 */
final class StatisticalForecaster implements ForecastProviderInterface
{
    public function __construct(
        private readonly int $minObservations = 14,
    ) {
    }

    public function forecast(array $priceSeriesCents, int $horizonDays): ?ForecastResult
    {
        $series = array_values(array_map('intval', $priceSeriesCents));
        $n = count($series);

        if ($n < $this->minObservations) {
            return null;
        }

        // x = 0..n-1 (one step per observation). Project one step past the last
        // observation per horizon "bucket" — we treat horizon_days as the number
        // of steps ahead (caller maps days->steps via its sampling cadence).
        [$slope, $intercept] = $this->ols($series);

        $futureX = ($n - 1) + max(1, $horizonDays);
        $point = (int) round($intercept + $slope * $futureX);
        $point = max(0, $point);

        $stdErr = $this->residualStdDev($series, $slope, $intercept);
        // ~95% interval (1.96 sigma), widened slightly with the horizon distance.
        $margin = (int) round(1.96 * $stdErr * sqrt(1 + ($horizonDays / max(1, $n))));

        return new ForecastResult(
            horizonDays: $horizonDays,
            forecastCents: $point,
            ciLowCents: max(0, $point - $margin),
            ciHighCents: $point + $margin,
            modelVersion: 'statistical-v1',
        );
    }

    /**
     * @param  array<int, int>  $y
     * @return array{0: float, 1: float}  [slope, intercept]
     */
    private function ols(array $y): array
    {
        $n = count($y);
        $sumX = 0.0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumXX = 0.0;

        foreach ($y as $x => $value) {
            $sumX += $x;
            $sumY += $value;
            $sumXY += $x * $value;
            $sumXX += $x * $x;
        }

        $denom = ($n * $sumXX) - ($sumX * $sumX);

        if ($denom === 0.0) {
            return [0.0, $sumY / $n];
        }

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denom;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        return [$slope, $intercept];
    }

    /**
     * @param  array<int, int>  $y
     */
    private function residualStdDev(array $y, float $slope, float $intercept): float
    {
        $n = count($y);
        $sumSq = 0.0;

        foreach ($y as $x => $value) {
            $predicted = $intercept + $slope * $x;
            $sumSq += ($value - $predicted) ** 2;
        }

        // Sample std dev of residuals (guard small n).
        return $n > 2 ? sqrt($sumSq / ($n - 2)) : 0.0;
    }
}
