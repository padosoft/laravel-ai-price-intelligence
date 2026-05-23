<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Enums;

enum MatchStatus: string
{
    case Confirmed = 'confirmed';
    case Suggested = 'suggested';
    case Rejected = 'rejected';
    case Dead = 'dead';

    public function isActive(): bool
    {
        return $this === self::Confirmed;
    }
}
