<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Data\ForecastResult;

/**
 * Predicts a competitor's price at a future horizon from a historical series.
 * The package ships only a zero-dependency Statistical driver; the interface is
 * public so third parties can register an ML-backed driver.
 */
interface ForecastProviderInterface
{
    /**
     * @param  array<int, int>  $priceSeriesCents  chronological, oldest first
     * @return ForecastResult|null  null if there is not enough data
     */
    public function forecast(array $priceSeriesCents, int $horizonDays): ?ForecastResult;
}
