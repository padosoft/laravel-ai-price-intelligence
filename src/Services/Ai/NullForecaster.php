<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\ForecastProviderInterface;
use Padosoft\PriceIntelligence\Data\ForecastResult;

/**
 * No-op forecaster bound when ai.forecast.enabled is false, so the feature flag
 * is actually honored (callers receive null instead of a live forecast).
 */
final class NullForecaster implements ForecastProviderInterface
{
    public function forecast(array $priceSeriesCents, int $horizonDays): ?ForecastResult
    {
        return null;
    }
}
