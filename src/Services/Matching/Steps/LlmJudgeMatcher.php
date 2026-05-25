<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Matching\Steps;

use Padosoft\PriceIntelligence\Contracts\BorderlineOnlyStep;
use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Contracts\MatchStepInterface;
use Padosoft\PriceIntelligence\Data\MatchScore;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\MatchMethod;
use Padosoft\PriceIntelligence\Models\Product;

final class LlmJudgeMatcher implements BorderlineOnlyStep, MatchStepInterface
{
    public function __construct(private readonly LlmProviderInterface $llm) {}

    public function applicable(Product $product, ProductSnapshot $candidate): bool
    {
        return $candidate->title !== null && $candidate->title !== '';
    }

    public function score(Product $product, ProductSnapshot $candidate): MatchScore
    {
        $left = trim(implode(' ', array_filter([$product->brand, $product->model, $product->name])));

        $result = $this->llm->completeJson(
            'You judge whether two product descriptions refer to the same exact product (same model/variant). '
            .'Return JSON: {"same_product": bool, "confidence": int 0-100, "rationale": string}.',
            "A: {$left}\nB: ".(string) $candidate->title,
            ['feature' => 'match_judge'],
        );

        $json = $result->json ?? [];
        $confidence = (int) ($json['confidence'] ?? 0);
        $confidence = max(0, min(100, $confidence));

        return new MatchScore(
            confidence: $confidence,
            method: MatchMethod::Llm,
            evidence: [
                'same_product' => (bool) ($json['same_product'] ?? false),
                'rationale' => is_string($json['rationale'] ?? null) ? $json['rationale'] : '',
                'model' => $result->model,
            ],
        );
    }
}
