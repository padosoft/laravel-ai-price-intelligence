<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use Padosoft\PriceIntelligence\Services\Ai\StatisticalAnomalyDetector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StatisticalAnomalyDetectorTest extends TestCase
{
    private function detector(): StatisticalAnomalyDetector
    {
        return new StatisticalAnomalyDetector(minObservations: 8, priceErrorRatio: 0.1);
    }

    #[Test]
    public function it_needs_enough_history(): void
    {
        $this->assertSame([], $this->detector()->detect([100, 100, 100], 1));
    }

    #[Test]
    public function it_flags_a_price_error_when_current_is_tiny_vs_median(): void
    {
        $history = array_fill(0, 10, 10000); // €100 median
        $decisions = $this->detector()->detect($history, 50); // €0.50

        $this->assertCount(1, $decisions);
        $this->assertSame('price_error', $decisions[0]['type']);
    }

    #[Test]
    public function it_flags_an_outlier_far_from_the_trend(): void
    {
        // Roughly flat history; 8000 is far from the ~10000 trend prediction
        // (and still above 10% of the median, so it is an outlier, not a price_error).
        $history = [9800, 9900, 10000, 10100, 10200, 9950, 10050, 10000, 9900, 10100];
        $decisions = $this->detector()->detect($history, 8000);

        $this->assertNotEmpty($decisions);
        $this->assertSame('outlier', $decisions[0]['type']);
    }

    #[Test]
    public function normal_price_yields_no_anomaly(): void
    {
        $history = [9800, 9900, 10000, 10100, 10200, 9950, 10050, 10000, 9900, 10100];
        $this->assertSame([], $this->detector()->detect($history, 10000));
    }

    #[Test]
    public function a_steady_trend_continuation_is_not_flagged(): void
    {
        // Strictly rising series; the next on-trend value must NOT be an outlier
        // (detrended residuals ~0), unlike a naive p95 check.
        $history = [10000, 10100, 10200, 10300, 10400, 10500, 10600, 10700, 10800, 10900];
        $this->assertSame([], $this->detector()->detect($history, 11000));
    }

    #[Test]
    public function a_break_from_the_trend_is_flagged(): void
    {
        $history = [10000, 10100, 10200, 10300, 10400, 10500, 10600, 10700, 10800, 10900];
        $decisions = $this->detector()->detect($history, 7000);

        $this->assertNotEmpty($decisions);
        $this->assertSame('outlier', $decisions[0]['type']);
    }
}
