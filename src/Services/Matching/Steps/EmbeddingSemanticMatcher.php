<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Matching\Steps;

use Padosoft\PriceIntelligence\Contracts\EmbeddingProviderInterface;
use Padosoft\PriceIntelligence\Contracts\MatchStepInterface;
use Padosoft\PriceIntelligence\Data\MatchScore;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\MatchMethod;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Support\Math\Vector;

/**
 * Semantic similarity via embeddings of (brand+model+name) vs candidate title.
 * Maps cosine [0.5,1.0] onto confidence [50,90].
 */
final class EmbeddingSemanticMatcher implements MatchStepInterface
{
    public function __construct(
        private readonly EmbeddingProviderInterface $embeddings,
    ) {}

    public function applicable(Product $product, ProductSnapshot $candidate): bool
    {
        return $candidate->title !== null && $candidate->title !== '';
    }

    public function score(Product $product, ProductSnapshot $candidate): MatchScore
    {
        $left = trim(implode(' ', array_filter([$product->brand, $product->model, $product->name])));
        $right = (string) $candidate->title;

        $cosine = Vector::cosine($this->embeddings->embed($left), $this->embeddings->embed($right));

        if ($cosine < 0.5) {
            return MatchScore::none(MatchMethod::Embedding, ['cosine' => round($cosine, 3)]);
        }

        $confidence = (int) round(50 + (($cosine - 0.5) / 0.5) * 40);
        $confidence = max(50, min(90, $confidence));

        return new MatchScore(
            confidence: $confidence,
            method: MatchMethod::Embedding,
            evidence: ['cosine' => round($cosine, 3)],
        );
    }
}
