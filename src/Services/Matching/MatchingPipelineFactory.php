<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Matching;

use Padosoft\PriceIntelligence\Contracts\EmbeddingProviderInterface;
use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Services\Matching\Steps\EmbeddingSemanticMatcher;
use Padosoft\PriceIntelligence\Services\Matching\Steps\ExactGtinMatcher;
use Padosoft\PriceIntelligence\Services\Matching\Steps\LlmJudgeMatcher;
use Padosoft\PriceIntelligence\Services\Matching\Steps\MpnBrandMatcher;
use Padosoft\PriceIntelligence\Services\Matching\Steps\NormalizedNameMatcher;
use Padosoft\PriceIntelligence\Support\Config\Flag;

/**
 * Assembles the matching cascade in cost order: cheap deterministic steps first
 * (GTIN, MPN, name), then the embedding step, and finally the borderline-only LLM
 * judge (which the pipeline invokes only for uncertain candidates).
 */
final class MatchingPipelineFactory
{
    public function __construct(
        private readonly EmbeddingProviderInterface $embeddings,
        private readonly LlmProviderInterface $llm,
    ) {}

    public function make(): MatchingPipeline
    {
        $band = (array) config('price-intelligence.matching.confidence_band', [60, 85]);

        $steps = [
            new ExactGtinMatcher,
            new MpnBrandMatcher,
            new NormalizedNameMatcher,
        ];

        if (Flag::enabled('price-intelligence.matching.embeddings.enabled', true)) {
            $steps[] = new EmbeddingSemanticMatcher($this->embeddings);
        }

        if (Flag::enabled('price-intelligence.matching.llm.enabled', true)) {
            $steps[] = new LlmJudgeMatcher($this->llm);
        }

        return new MatchingPipeline($steps, [(int) ($band[0] ?? 60), (int) ($band[1] ?? 85)]);
    }
}
