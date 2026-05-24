<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use Padosoft\PriceIntelligence\Support\Identifiers\SlugNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SlugNormalizerTest extends TestCase
{
    #[Test]
    public function it_normalizes_accents_and_punctuation(): void
    {
        $this->assertSame('caffe latte 250 ml', SlugNormalizer::normalize('Caffè Latté, 250 mL!'));
    }

    #[Test]
    public function it_tokenizes_and_drops_stopwords(): void
    {
        $this->assertSame(['scarpe', 'corsa', 'nike'], SlugNormalizer::tokens('Le scarpe da corsa Nike'));
    }

    #[Test]
    public function identical_names_score_one(): void
    {
        $this->assertSame(1.0, SlugNormalizer::tokenSimilarity('Nike Air Force 1', 'nike air force 1'));
    }

    #[Test]
    public function disjoint_names_score_zero(): void
    {
        $this->assertSame(0.0, SlugNormalizer::tokenSimilarity('Apple iPhone', 'Samsung Galaxy'));
    }

    #[Test]
    public function partial_overlap_scores_between(): void
    {
        $score = SlugNormalizer::tokenSimilarity('Nike Air Force 1 White', 'Nike Air Force 1 Black');
        $this->assertGreaterThan(0.4, $score);
        $this->assertLessThan(1.0, $score);
    }
}
