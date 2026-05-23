<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Padosoft\PriceIntelligence\Services\Ai\StatisticalAnomalyDetector;

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
    public function it_flags_an_outlier_below_p5(): void
    {
        $history = [9800, 9900, 10000, 10100, 10200, 9950, 10050, 10000, 9900, 10100];
        $decisions = $this->detector()->detect($history, 8000); // clearly below p5 but > 10% median

        $this->assertNotEmpty($decisions);
        $this->assertSame('outlier', $decisions[0]['type']);
    }

    #[Test]
    public function normal_price_yields_no_anomaly(): void
    {
        $history = [9800, 9900, 10000, 10100, 10200, 9950, 10050, 10000, 9900, 10100];
        $this->assertSame([], $this->detector()->detect($history, 10000));
    }
}
