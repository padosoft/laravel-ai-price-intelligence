<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use Padosoft\PriceIntelligence\Support\Discovery\GeoSearchQueryFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GeoSearchQueryFactoryTest extends TestCase
{
    #[Test]
    public function it_carries_country_and_locale_in_metadata(): void
    {
        $query = GeoSearchQueryFactory::make(
            brand: 'Nike',
            model: 'Air Force 1',
            country: 'IT',
            locale: 'it-IT',
            site: 'amazon.it',
        );

        $this->assertSame('IT', GeoSearchQueryFactory::country($query));
        $this->assertSame('it-IT', GeoSearchQueryFactory::locale($query));
        $this->assertSame('amazon.it', $query->site);
        $this->assertStringContainsString('Nike', $query->toSearchString());
    }

    #[Test]
    public function it_omits_empty_geo_keys(): void
    {
        $query = GeoSearchQueryFactory::make(brand: 'Acme', model: 'X1');

        $this->assertNull(GeoSearchQueryFactory::country($query));
        $this->assertNull(GeoSearchQueryFactory::locale($query));
    }
}
