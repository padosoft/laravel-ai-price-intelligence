<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Contracts\VisualMatcherInterface;
use Padosoft\PriceIntelligence\Data\VisualMatchResult;

final class VisualMatcher implements VisualMatcherInterface
{
    public function __construct(
        private readonly LlmProviderInterface $llm,
        private readonly AiDecisionLogger $logger,
    ) {}

    public function isSameProduct(int|string $tenantId, string $imageUrlA, string $imageUrlB): VisualMatchResult
    {
        $result = $this->llm->vision(
            'You compare two product photos. Return JSON: '
            .'{"same_product": bool, "confidence": int 0-100, "rationale": string}.',
            'Are these the same product? Consider model, colour, and variant.',
            [$imageUrlA, $imageUrlB],
            ['feature' => 'visual_match', 'model' => config('price-intelligence.ai.llm.vision_model')],
        );

        $json = $result->json ?? [];
        $match = new VisualMatchResult(
            sameProduct: (bool) ($json['same_product'] ?? false),
            confidence: (int) ($json['confidence'] ?? 0),
            rationale: is_string($json['rationale'] ?? null) ? $json['rationale'] : '',
            model: $result->model,
        );

        $this->logger->record(
            tenantId: $tenantId,
            feature: 'visual_match',
            output: $json,
            model: $result->model,
            confidence: $match->confidence,
        );

        return $match;
    }
}
