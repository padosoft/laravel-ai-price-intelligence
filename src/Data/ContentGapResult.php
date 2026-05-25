<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

final class ContentGapResult
{
    /**
     * @param  array<int, string>  $missingAttributes
     * @param  array<int, string>  $titleRecommendations
     * @param  array<int, string>  $descriptionRecommendations
     */
    public function __construct(
        public readonly int $seoScoreDelta,
        public readonly array $missingAttributes,
        public readonly array $titleRecommendations,
        public readonly array $descriptionRecommendations,
        public readonly int $imageCountGap,
        public readonly string $model,
    ) {}
}
