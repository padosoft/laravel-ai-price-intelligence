<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Matching\Embeddings;

use Padosoft\PriceIntelligence\Contracts\EmbeddingProviderInterface;
use Padosoft\PriceIntelligence\Support\Llm\EmbeddingRunner;

final class LaravelAiEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private readonly EmbeddingRunner $runner,
        private readonly string $provider,
        private readonly string $model,
        private readonly int $dimensions,
    ) {}

    public function embed(string $text): array
    {
        return $this->runner->embed($text, $this->provider, $this->model, $this->dimensions);
    }
}
