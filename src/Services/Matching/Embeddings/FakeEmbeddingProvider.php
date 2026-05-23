<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Matching\Embeddings;

use Padosoft\PriceIntelligence\Contracts\EmbeddingProviderInterface;
use Padosoft\PriceIntelligence\Support\Identifiers\SlugNormalizer;

/**
 * Deterministic bag-of-tokens embedding for tests and offline development.
 * Each token hashes to a fixed dimension; the vector counts token occurrences.
 * Cosine of two such vectors approximates token-set overlap — good enough to
 * exercise the semantic step without a network call.
 */
final class FakeEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(private readonly int $dimensions = 64)
    {
    }

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);

        foreach (SlugNormalizer::tokens($text) as $token) {
            $index = (int) (hexdec(substr(md5($token), 0, 8)) % $this->dimensions);
            $vector[$index] += 1.0;
        }

        return $vector;
    }
}
