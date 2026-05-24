<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

final class SentimentResult
{
    /**
     * @param  float  $score  normalized sentiment in [-1, 1]
     * @param  array<int, array{theme: string, sentiment: string, mentions: int}>  $themes
     */
    public function __construct(
        public readonly float $score,
        public readonly array $themes,
        public readonly int $sampleCount,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sentiment_score' => $this->score,
            'themes' => $this->themes,
            'sample_count' => $this->sampleCount,
        ];
    }
}
