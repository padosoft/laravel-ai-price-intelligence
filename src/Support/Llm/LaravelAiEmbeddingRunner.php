<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Llm;

use Laravel\Ai\Embeddings;

final class LaravelAiEmbeddingRunner implements EmbeddingRunner
{
    public function embed(string $text, string $provider, string $model, int $dimensions): array
    {
        $response = Embeddings::for([$text])
            ->dimensions($dimensions)
            ->generate($provider, $model);

        /** @var array<int, array<int, float>> $vectors */
        $vectors = $response->embeddings;

        return $vectors[0] ?? [];
    }
}
