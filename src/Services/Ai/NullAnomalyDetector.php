<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\AnomalyDetectorInterface;

/**
 * No-op detector bound when ai.anomaly.enabled is false, so the feature flag is
 * actually honored (callers receive no anomalies).
 */
final class NullAnomalyDetector implements AnomalyDetectorInterface
{
    public function detect(array $priceSeriesCents, int $currentCents): array
    {
        return [];
    }
}
