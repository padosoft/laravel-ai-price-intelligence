<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

final class ForecastResult
{
    public function __construct(
        public readonly int $horizonDays,
        public readonly int $forecastCents,
        public readonly ?int $ciLowCents,
        public readonly ?int $ciHighCents,
        public readonly string $modelVersion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'horizon_days' => $this->horizonDays,
            'forecast_price_cents' => $this->forecastCents,
            'ci_low_cents' => $this->ciLowCents,
            'ci_high_cents' => $this->ciHighCents,
            'model_version' => $this->modelVersion,
        ];
    }
}
