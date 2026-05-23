<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

/**
 * Produces a dense vector for a piece of text. Implementations: OpenAI, Voyage,
 * local; a deterministic Fake ships for tests.
 */
interface EmbeddingProviderInterface
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array;
}
