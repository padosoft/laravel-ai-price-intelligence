<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Padosoft\PriceIntelligence\Support\Pricing\PriceParser;

final class PriceParserTest extends TestCase
{
    #[Test]
    #[DataProvider('prices')]
    public function it_parses_prices_to_cents(string $input, int $expectedCents, ?string $currency): void
    {
        $parsed = PriceParser::parse($input);

        $this->assertNotNull($parsed);
        $this->assertSame($expectedCents, $parsed['cents']);
        $this->assertSame($currency, $parsed['currency']);
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: ?string}>
     */
    public static function prices(): array
    {
        return [
            'eu simple' => ['€ 19,99', 1999, 'EUR'],
            'eu thousands' => ['1.299,00 €', 129900, 'EUR'],
            'us thousands' => ['$1,299.00', 129900, 'USD'],
            'gbp' => ['£85.50', 8550, 'GBP'],
            'plain integer' => ['200', 20000, null],
            'eu no symbol' => ['49,90', 4990, null],
        ];
    }

    #[Test]
    public function it_returns_null_for_non_price(): void
    {
        $this->assertNull(PriceParser::parse('out of stock'));
    }
}
