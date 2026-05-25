<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

final class PromoResult
{
    public function __construct(
        public readonly bool $hasPromo,
        public readonly ?string $promoType,
        public readonly ?string $validFrom,
        public readonly ?string $validTo,
        public readonly ?string $conditions,
        public readonly ?float $effectiveDiscountPct,
        public readonly string $model,
    ) {}
}
