<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Matching;

use Padosoft\PriceIntelligence\Contracts\EmbeddingProviderInterface;
use Padosoft\PriceIntelligence\Services\Matching\Steps\EmbeddingSemanticMatcher;
use Padosoft\PriceIntelligence\Services\Matching\Steps\ExactGtinMatcher;
use Padosoft\PriceIntelligence\Services\Matching\Steps\MpnBrandMatcher;
use Padosoft\PriceIntelligence\Services\Matching\Steps\NormalizedNameMatcher;

/**
 * Assembles the matching cascade in cost order: cheap deterministic steps first
 * (GTIN, MPN, name) then the embedding step. Visual/LLM steps are appended by
 * the AI layer when enabled.
 */
final class MatchingPipelineFactory
{
    public function __construct(
        private readonly EmbeddingProviderInterface $embeddings,
    ) {}

    public function make(): MatchingPipeline
    {
        $band = (array) config('price-intelligence.matching.confidence_band', [60, 85]);

        $steps = [
            new ExactGtinMatcher,
            new MpnBrandMatcher,
            new NormalizedNameMatcher,
        ];

        if ((bool) config('price-intelligence.matching.embeddings.enabled', true) !== false) {
            $steps[] = new EmbeddingSemanticMatcher($this->embeddings);
        }

        return new MatchingPipeline($steps, [(int) ($band[0] ?? 60), (int) ($band[1] ?? 85)]);
    }
}
