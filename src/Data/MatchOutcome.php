<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

use Padosoft\PriceIntelligence\Enums\MatchMethod;
use Padosoft\PriceIntelligence\Enums\MatchStatus;

/**
 * Final verdict of the matching cascade for a (product, candidate) pair.
 */
final class MatchOutcome
{
    /**
     * @param  array<int, array<string, mixed>>  $trail  per-step evidence
     */
    public function __construct(
        public readonly MatchStatus $status,
        public readonly int $confidence,
        public readonly MatchMethod $method,
        public readonly array $trail = [],
    ) {
    }

    public function isConfirmed(): bool
    {
        return $this->status === MatchStatus::Confirmed;
    }

    public function isSuggested(): bool
    {
        return $this->status === MatchStatus::Suggested;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'confidence' => $this->confidence,
            'method' => $this->method->value,
            'trail' => $this->trail,
        ];
    }
}
