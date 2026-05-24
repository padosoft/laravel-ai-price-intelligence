<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai\ReviewInsight;

use Padosoft\PriceIntelligence\Contracts\ReviewSentimentInterface;
use Padosoft\PriceIntelligence\Data\SentimentResult;
use Padosoft\PriceIntelligence\Support\Identifiers\SlugNormalizer;

/**
 * Deterministic, zero-dependency sentiment analyzer using a small positive/
 * negative lexicon. Offline-safe default; hosts rebind an LLM driver for nuance.
 * Operates only on already-redacted aggregate text.
 */
final class LexiconSentimentAnalyzer implements ReviewSentimentInterface
{
    /** @var array<int, string> */
    private const POSITIVE = ['good', 'great', 'excellent', 'love', 'perfect', 'fast', 'quality', 'recommend', 'ottimo', 'perfetto', 'veloce'];

    /** @var array<int, string> */
    private const NEGATIVE = ['bad', 'poor', 'broken', 'slow', 'cheap', 'defective', 'late', 'terrible', 'pessimo', 'lento', 'rotto', 'difettoso'];

    /**
     * @param  array<int, string>  $redactedTexts
     */
    public function analyze(array $redactedTexts): SentimentResult
    {
        $pos = 0;
        $neg = 0;
        $themeCounts = [];

        foreach ($redactedTexts as $text) {
            foreach (SlugNormalizer::tokens($text, dropStopwords: false) as $token) {
                if (in_array($token, self::POSITIVE, true)) {
                    $pos++;
                    $themeCounts[$token] = ($themeCounts[$token] ?? ['p' => 0, 'n' => 0]);
                    $themeCounts[$token]['p']++;
                } elseif (in_array($token, self::NEGATIVE, true)) {
                    $neg++;
                    $themeCounts[$token] = ($themeCounts[$token] ?? ['p' => 0, 'n' => 0]);
                    $themeCounts[$token]['n']++;
                }
            }
        }

        $total = $pos + $neg;
        $score = $total === 0 ? 0.0 : round(($pos - $neg) / $total, 3);

        $themes = [];
        foreach ($themeCounts as $theme => $counts) {
            $themes[] = [
                'theme' => $theme,
                'sentiment' => $counts['p'] >= $counts['n'] ? 'positive' : 'negative',
                'mentions' => $counts['p'] + $counts['n'],
            ];
        }

        return new SentimentResult($score, $themes, count($redactedTexts));
    }
}
