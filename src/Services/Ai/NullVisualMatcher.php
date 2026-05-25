<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\VisualMatcherInterface;
use Padosoft\PriceIntelligence\Data\VisualMatchResult;

/**
 * No-op matcher bound when ai.visual_match.enabled is false, so the feature toggle
 * is actually honored (callers receive an inconclusive result instead of a live LLM call).
 */
final class NullVisualMatcher implements VisualMatcherInterface
{
    public function isSameProduct(int|string $tenantId, string $imageUrlA, string $imageUrlB): VisualMatchResult
    {
        return new VisualMatchResult(
            sameProduct: false,
            confidence: 0,
            rationale: 'disabled',
            model: 'disabled',
        );
    }
}
