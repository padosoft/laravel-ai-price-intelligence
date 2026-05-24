<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Data\SentimentResult;

/**
 * Analyzes a set of (already PII-redacted) review texts into an anonymous
 * aggregate. Default driver is a deterministic lexicon-based analyzer; hosts can
 * rebind an LLM-backed implementation.
 */
interface ReviewSentimentInterface
{
    /**
     * @param  array<int, string>  $redactedTexts
     */
    public function analyze(array $redactedTexts): SentimentResult;
}
