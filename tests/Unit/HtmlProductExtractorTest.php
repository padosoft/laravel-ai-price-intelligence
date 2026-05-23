<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Padosoft\PriceIntelligence\Services\Scraping\HtmlProductExtractor;

final class HtmlProductExtractorTest extends TestCase
{
    private function extractor(): HtmlProductExtractor
    {
        return new HtmlProductExtractor();
    }

    #[Test]
    public function it_extracts_product_from_jsonld(): void
    {
        $html = <<<'HTML'
        <html><head>
        <script type="application/ld+json">
        {"@context":"https://schema.org","@type":"Product","name":"Acme X1 64GB",
         "brand":{"@type":"Brand","name":"Acme"},"gtin13":"4006381333931","mpn":"AC-X1-64",
         "image":["https://cdn/x1.jpg"],
         "offers":{"@type":"Offer","price":"199.00","priceCurrency":"EUR","availability":"https://schema.org/InStock"}}
        </script></head><body></body></html>
        HTML;

        $s = $this->extractor()->extract($html, 'https://shop.it/p/x1');

        $this->assertSame('Acme X1 64GB', $s->title);
        $this->assertSame('Acme', $s->brand);
        $this->assertSame('4006381333931', $s->gtin);
        $this->assertSame('AC-X1-64', $s->mpn);
        $this->assertSame(19900, $s->priceCents);
        $this->assertSame('EUR', $s->currency);
        $this->assertTrue($s->available);
        $this->assertContains('https://cdn/x1.jpg', $s->images);
    }

    #[Test]
    public function it_detects_out_of_stock(): void
    {
        $html = <<<'HTML'
        <script type="application/ld+json">
        {"@type":"Product","name":"Gone","offers":{"price":"10.00","priceCurrency":"EUR","availability":"https://schema.org/OutOfStock"}}
        </script>
        HTML;

        $s = $this->extractor()->extract($html, 'https://shop.it/p/gone');

        $this->assertFalse($s->available);
    }

    #[Test]
    public function it_falls_back_to_opengraph(): void
    {
        $html = <<<'HTML'
        <html><head>
        <meta property="og:title" content="OG Phone">
        <meta property="og:image" content="https://cdn/og.jpg">
        <meta property="product:price:amount" content="49,90">
        <meta property="product:price:currency" content="EUR">
        </head></html>
        HTML;

        $s = $this->extractor()->extract($html, 'https://shop.it/p/og');

        $this->assertSame('OG Phone', $s->title);
        $this->assertSame(4990, $s->priceCents);
        $this->assertSame('EUR', $s->currency);
        $this->assertContains('https://cdn/og.jpg', $s->images);
    }

    #[Test]
    public function it_handles_graph_arrays(): void
    {
        $html = <<<'HTML'
        <script type="application/ld+json">
        {"@context":"https://schema.org","@graph":[
          {"@type":"BreadcrumbList"},
          {"@type":"Product","name":"Graphed","offers":{"price":"5.00","priceCurrency":"USD"}}
        ]}
        </script>
        HTML;

        $s = $this->extractor()->extract($html, 'https://shop.com/p/g');

        $this->assertSame('Graphed', $s->title);
        $this->assertSame(500, $s->priceCents);
    }
}
