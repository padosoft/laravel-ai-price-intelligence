<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Enums;

enum Frequency: string
{
    case FifteenMinutes = '15min';
    case Hourly = '1h';
    case FourHours = '4h';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Custom = 'custom';

    /**
     * Base interval in seconds. Custom returns null (cron-driven).
     */
    public function seconds(): ?int
    {
        return match ($this) {
            self::FifteenMinutes => 900,
            self::Hourly => 3600,
            self::FourHours => 14400,
            self::Daily => 86400,
            self::Weekly => 604800,
            self::Custom => null,
        };
    }

    public function isCustom(): bool
    {
        return $this === self::Custom;
    }
}
