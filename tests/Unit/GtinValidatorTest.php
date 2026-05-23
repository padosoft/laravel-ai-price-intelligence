<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Padosoft\PriceIntelligence\Support\Identifiers\GtinValidator;

final class GtinValidatorTest extends TestCase
{
    #[Test]
    #[DataProvider('validGtins')]
    public function it_accepts_valid_gtins(string $gtin): void
    {
        $this->assertTrue(GtinValidator::isValid($gtin));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validGtins(): array
    {
        return [
            'EAN-13' => ['4006381333931'],
            'EAN-13 book' => ['9780201379624'],
            'UPC-A' => ['036000291452'],
            'GTIN-8' => ['96385074'],
            'EAN-13 with spaces' => ['4 006381 333931'],
        ];
    }

    #[Test]
    #[DataProvider('invalidGtins')]
    public function it_rejects_invalid_gtins(string $gtin): void
    {
        $this->assertFalse(GtinValidator::isValid($gtin));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidGtins(): array
    {
        return [
            'bad check digit' => ['4006381333932'],
            'too short' => ['12345'],
            'letters' => ['ABCDEFGHIJKLM'],
            'empty' => [''],
        ];
    }

    #[Test]
    public function upc_and_ean_of_same_product_compare_equal_in_gtin14(): void
    {
        // UPC-A 12 digits left-pads to the same GTIN-14 as its EAN-13 form.
        $upc = '036000291452';
        $ean = '0036000291452';

        $this->assertTrue(GtinValidator::equals($upc, $ean));
    }

    #[Test]
    public function it_computes_check_digit(): void
    {
        $this->assertSame(1, GtinValidator::checkDigit('400638133393'));
    }
}
