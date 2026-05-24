<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

use Padosoft\PriceIntelligence\Enums\MatchMethod;

/**
 * Result of a single matcher step: a 0-100 confidence, the method that produced
 * it, and human-readable evidence for the admin review UI.
 */
final class MatchScore
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public readonly int $confidence,
        public readonly MatchMethod $method,
        public readonly array $evidence = [],
    ) {}

    public static function none(MatchMethod $method, array $evidence = []): self
    {
        return new self(0, $method, $evidence);
    }

    public function isConclusive(): bool
    {
        return $this->confidence >= 95;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'confidence' => $this->confidence,
            'method' => $this->method->value,
            'evidence' => $this->evidence,
        ];
    }
}
