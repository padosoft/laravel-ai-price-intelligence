<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\NarrativeWriterInterface;
use Padosoft\PriceIntelligence\Data\NarrativeResult;

/**
 * No-op writer bound when ai.narrative.enabled is false, so the feature toggle
 * is actually honored (callers receive an empty result instead of a live LLM call).
 */
final class NullNarrativeWriter implements NarrativeWriterInterface
{
    public function write(int|string $tenantId, string $period, array $context): NarrativeResult
    {
        return new NarrativeResult('', [], 'disabled');
    }
}
