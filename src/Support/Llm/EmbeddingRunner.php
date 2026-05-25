<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Llm;

interface EmbeddingRunner
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text, string $provider, string $model, int $dimensions): array;
}
