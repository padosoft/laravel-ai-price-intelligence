<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use Padosoft\PriceIntelligence\Services\Ai\StatisticalForecaster;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StatisticalForecasterTest extends TestCase
{
    #[Test]
    public function it_returns_null_without_enough_data(): void
    {
        $forecaster = new StatisticalForecaster(minObservations: 14);

        $this->assertNull($forecaster->forecast([100, 101, 102], 7));
    }

    #[Test]
    public function it_rejects_a_non_positive_horizon(): void
    {
        $forecaster = new StatisticalForecaster(minObservations: 5);
        $series = array_fill(0, 20, 5000);

        $this->assertNull($forecaster->forecast($series, 0));
        $this->assertNull($forecaster->forecast($series, -7));
    }

    #[Test]
    public function it_projects_a_rising_trend_upward(): void
    {
        // Strictly increasing series: forecast should be above the last value.
        $series = range(10000, 10000 + 13 * 50, 50); // 14 points, +50 each
        $forecaster = new StatisticalForecaster(minObservations: 14);

        $result = $forecaster->forecast($series, 7);

        $this->assertNotNull($result);
        $this->assertGreaterThan(end($series), $result->forecastCents);
        $this->assertSame(7, $result->horizonDays);
        $this->assertSame('statistical-v1', $result->modelVersion);
    }

    #[Test]
    public function flat_series_forecasts_near_constant_with_tight_interval(): void
    {
        $series = array_fill(0, 20, 5000);
        $forecaster = new StatisticalForecaster(minObservations: 14);

        $result = $forecaster->forecast($series, 14);

        $this->assertNotNull($result);
        $this->assertSame(5000, $result->forecastCents);
        // No variance -> CI collapses onto the point.
        $this->assertSame(5000, $result->ciLowCents);
        $this->assertSame(5000, $result->ciHighCents);
    }

    #[Test]
    public function confidence_interval_brackets_the_point(): void
    {
        $series = [100, 120, 90, 130, 110, 140, 100, 150, 120, 160, 130, 170, 140, 180];
        $forecaster = new StatisticalForecaster(minObservations: 14);

        $result = $forecaster->forecast($series, 7);

        $this->assertNotNull($result);
        // Explicit ordering (clearer than paired assertLessThan/GreaterThan):
        // ciLow <= forecast <= ciHigh, and the lower bound is never negative.
        $this->assertTrue(
            $result->ciLowCents <= $result->forecastCents
            && $result->forecastCents <= $result->ciHighCents,
            'CI must bracket the point forecast',
        );
        $this->assertGreaterThanOrEqual(0, $result->ciLowCents);
    }

    #[Test]
    public function it_skips_non_numeric_points_instead_of_zeroing_them(): void
    {
        $forecaster = new StatisticalForecaster(minObservations: 5);
        // Two nulls interleaved; the real series is a flat 5000.
        $series = [5000, null, 5000, 5000, null, 5000, 5000, 5000];

        $result = $forecaster->forecast($series, 7);

        $this->assertNotNull($result);
        // If nulls had been coerced to 0 the forecast would be dragged far below 5000.
        $this->assertSame(5000, $result->forecastCents);
    }

    #[Test]
    public function misconfigured_min_observations_cannot_divide_by_zero(): void
    {
        $forecaster = new StatisticalForecaster(minObservations: 0);

        // Empty / tiny series must return null, never a DivisionByZeroError.
        $this->assertNull($forecaster->forecast([], 7));
        $this->assertNull($forecaster->forecast([100], 7));
    }
}
