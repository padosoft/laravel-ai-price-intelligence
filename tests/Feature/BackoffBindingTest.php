<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Padosoft\PriceIntelligence\Enums\Frequency;
use Padosoft\PriceIntelligence\Services\Scheduling\AdaptiveBackoff;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regression: the AdaptiveBackoff binding must register under the correct FQCN
 * so config('price-intelligence.resilience.adaptive_backoff.*') actually applies
 * (previously a missing import bound it under the wrong namespace -> defaults).
 */
final class BackoffBindingTest extends TestCase
{
    #[Test]
    public function container_honors_adaptive_backoff_config(): void
    {
        config()->set('price-intelligence.resilience.adaptive_backoff.enabled', false);

        $backoff = app(AdaptiveBackoff::class);

        // Disabled -> neutral factor regardless of stability/change.
        $this->assertSame(1.0, $backoff->nextFactor(3.0, Frequency::Daily, 0.99, true));
    }

    #[Test]
    public function container_honors_max_factor_config(): void
    {
        config()->set('price-intelligence.resilience.adaptive_backoff.enabled', true);
        config()->set('price-intelligence.resilience.adaptive_backoff.max_factor', 2);

        $backoff = app(AdaptiveBackoff::class);

        // max_factor=2 caps the slow-down.
        $this->assertSame(2.0, $backoff->nextFactor(3.0, Frequency::Daily, 0.99, false));
    }
}
