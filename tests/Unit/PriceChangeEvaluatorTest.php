<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Padosoft\PriceIntelligence\Enums\AlertType;
use Padosoft\PriceIntelligence\Enums\Severity;
use Padosoft\PriceIntelligence\Services\Alerts\PriceChangeEvaluator;

final class PriceChangeEvaluatorTest extends TestCase
{
    private function evaluator(): PriceChangeEvaluator
    {
        return new PriceChangeEvaluator();
    }

    #[Test]
    public function price_drop_raises_dropped_alert(): void
    {
        $decisions = $this->evaluator()->evaluate(10000, 8000, null, true);

        $this->assertCount(1, $decisions);
        $this->assertSame(AlertType::PriceDropped, $decisions[0]['type']);
        // 20% drop -> critical.
        $this->assertSame(Severity::Critical, $decisions[0]['severity']);
    }

    #[Test]
    public function undercut_detected_when_competitor_cheaper_than_us(): void
    {
        $decisions = $this->evaluator()->evaluate(9000, 8000, 8500, true);

        $types = array_map(static fn ($d) => $d['type'], $decisions);
        $this->assertContains(AlertType::PriceDropped, $types);
        $this->assertContains(AlertType::UndercutDetected, $types);
    }

    #[Test]
    public function out_of_stock_raises_stockout_only(): void
    {
        $decisions = $this->evaluator()->evaluate(10000, 10000, 9000, false);

        $this->assertCount(1, $decisions);
        $this->assertSame(AlertType::StockOut, $decisions[0]['type']);
    }

    #[Test]
    public function no_change_no_alert(): void
    {
        // our price (5000) below competitor (10000): we're cheaper, no undercut, no change.
        $this->assertSame([], $this->evaluator()->evaluate(10000, 10000, 5000, true));
    }

    #[Test]
    public function first_observation_no_change_alert(): void
    {
        // previous null, our price below competitor -> no undercut, no change.
        $this->assertSame([], $this->evaluator()->evaluate(null, 10000, 5000, true));
    }
}
