<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\ContentGapAnalyzerInterface;
use Padosoft\PriceIntelligence\Data\ContentGapResult;
use Padosoft\PriceIntelligence\Models\Product;

/**
 * No-op analyzer bound when ai.content_gap.enabled is false, so the feature toggle
 * is actually honored (callers receive a zero-delta result instead of a live LLM call).
 */
final class NullContentGapAnalyzer implements ContentGapAnalyzerInterface
{
    public function analyze(Product $product, array $competitorSnapshots): ContentGapResult
    {
        return new ContentGapResult(
            seoScoreDelta: 0,
            missingAttributes: [],
            titleRecommendations: [],
            descriptionRecommendations: [],
            imageCountGap: 0,
            model: 'disabled',
        );
    }
}
