<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

final class NarrativeResult
{
    /**
     * @param  array<int, mixed>  $highlights
     */
    public function __construct(
        public readonly string $summaryMd,
        public readonly array $highlights,
        public readonly string $model,
    ) {}
}
