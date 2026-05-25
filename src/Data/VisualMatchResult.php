<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

final class VisualMatchResult
{
    public function __construct(
        public readonly bool $sameProduct,
        public readonly int $confidence,
        public readonly string $rationale,
        public readonly string $model,
    ) {}
}
