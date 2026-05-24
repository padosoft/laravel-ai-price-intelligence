<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use Padosoft\PriceIntelligence\Services\Ai\ReviewInsight\LexiconSentimentAnalyzer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LexiconSentimentAnalyzerTest extends TestCase
{
    #[Test]
    public function positive_reviews_yield_positive_score(): void
    {
        $result = (new LexiconSentimentAnalyzer)->analyze([
            'great quality and fast shipping',
            'excellent, i love it',
        ]);

        $this->assertGreaterThan(0, $result->score);
        $this->assertSame(2, $result->sampleCount);
        $this->assertNotEmpty($result->themes);
    }

    #[Test]
    public function negative_reviews_yield_negative_score(): void
    {
        $result = (new LexiconSentimentAnalyzer)->analyze([
            'broken on arrival, terrible',
            'slow and defective',
        ]);

        $this->assertLessThan(0, $result->score);
    }

    #[Test]
    public function neutral_or_empty_is_zero(): void
    {
        $this->assertSame(0.0, (new LexiconSentimentAnalyzer)->analyze([])->score);
        $this->assertSame(0.0, (new LexiconSentimentAnalyzer)->analyze(['the item arrived'])->score);
    }
}
