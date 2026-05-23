<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Padosoft\PriceIntelligence\Tests\TestCase;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\MatchMethod;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Services\Matching\Embeddings\FakeEmbeddingProvider;
use Padosoft\PriceIntelligence\Services\Matching\MatchingPipeline;
use Padosoft\PriceIntelligence\Services\Matching\Steps\EmbeddingSemanticMatcher;
use Padosoft\PriceIntelligence\Services\Matching\Steps\ExactGtinMatcher;
use Padosoft\PriceIntelligence\Services\Matching\Steps\MpnBrandMatcher;
use Padosoft\PriceIntelligence\Services\Matching\Steps\NormalizedNameMatcher;

final class MatchingPipelineTest extends TestCase
{
    private function pipeline(): MatchingPipeline
    {
        return new MatchingPipeline([
            new ExactGtinMatcher(),
            new MpnBrandMatcher(),
            new NormalizedNameMatcher(),
            new EmbeddingSemanticMatcher(new FakeEmbeddingProvider()),
        ], [60, 85]);
    }

    private function product(array $attrs): Product
    {
        return new Product($attrs);
    }

    #[Test]
    public function exact_gtin_confirms_and_short_circuits(): void
    {
        $product = $this->product(['name' => 'Phone', 'gtin' => '4006381333931']);
        $candidate = new ProductSnapshot(url: 'https://x.test/p', title: 'Totally Different', gtin: '4006381333931');

        $outcome = $this->pipeline()->match($product, $candidate);

        $this->assertTrue($outcome->isConfirmed());
        $this->assertSame(100, $outcome->confidence);
        $this->assertSame(MatchMethod::Gtin, $outcome->method);
        // Short-circuit: only the GTIN step ran.
        $this->assertCount(1, $outcome->trail);
    }

    #[Test]
    public function mpn_plus_brand_confirms(): void
    {
        $product = $this->product(['name' => 'Acme X1', 'mpn' => 'AC-X1-64', 'brand' => 'Acme']);
        $candidate = new ProductSnapshot(url: 'https://x.test/p', title: 'Acme X1 64GB', mpn: 'ACX164', brand: 'ACME');

        $outcome = $this->pipeline()->match($product, $candidate);

        $this->assertTrue($outcome->isConfirmed());
        $this->assertGreaterThanOrEqual(95, $outcome->confidence);
    }

    #[Test]
    public function close_name_without_ids_is_suggested(): void
    {
        // Similar but not identical model line: lands in the review band, not auto-confirm.
        $product = $this->product(['name' => 'Nike Air Zoom Pegasus 40']);
        $candidate = new ProductSnapshot(url: 'https://x.test/p', title: 'Nike Air Zoom Pegasus Trail');

        $outcome = $this->pipeline()->match($product, $candidate);

        $this->assertSame('suggested', $outcome->status->value);
        $this->assertGreaterThanOrEqual(60, $outcome->confidence);
        $this->assertLessThan(85, $outcome->confidence);
    }

    #[Test]
    public function unrelated_product_is_rejected(): void
    {
        $product = $this->product(['name' => 'Apple iPhone 15', 'brand' => 'Apple']);
        $candidate = new ProductSnapshot(url: 'https://x.test/p', title: 'Samsung Galaxy Washing Machine', brand: 'Samsung');

        $outcome = $this->pipeline()->match($product, $candidate);

        $this->assertSame('rejected', $outcome->status->value);
    }

    #[Test]
    public function different_gtin_falls_through_to_name(): void
    {
        // GTIN present on both but different -> GTIN step scores 0, name step rescues.
        $product = $this->product(['name' => 'Nike Air Force 1 White', 'brand' => 'Nike', 'gtin' => '4006381333931']);
        $candidate = new ProductSnapshot(url: 'https://x.test/p', title: 'Nike Air Force 1 White Sneakers', gtin: '9780201379624', brand: 'Nike');

        $outcome = $this->pipeline()->match($product, $candidate);

        $this->assertNotSame(MatchMethod::Gtin, $outcome->method);
        $this->assertGreaterThanOrEqual(60, $outcome->confidence);
    }
}
