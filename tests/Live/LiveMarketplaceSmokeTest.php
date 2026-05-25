<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Live;

use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\AmazonSpApiClient;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\EbayBrowseClient;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\KeepaClient;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Opt-in live marketplace smoke suite. Not referenced by any phpunit <testsuite>, so the
 * default run never executes it. Provide real credentials and run explicitly:
 *
 *   PI_LIVE_MARKETPLACE=1 vendor/bin/phpunit tests/Live
 *
 * Each test skips individually when its provider's credentials are absent.
 */
#[Group('live')]
final class LiveMarketplaceSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (env('PI_LIVE_MARKETPLACE') !== '1') {
            $this->markTestSkipped('Set PI_LIVE_MARKETPLACE=1 (+ provider credentials) to run live marketplace smoke tests.');
        }
    }

    #[Test]
    public function keepa_returns_a_product(): void
    {
        if (config('price-intelligence.marketplaces.amazon.keepa.key') === null) {
            $this->markTestSkipped('No Keepa key configured.');
        }

        $result = app(KeepaClient::class)->fetchByAsin((string) env('PI_LIVE_ASIN', 'B07PFFMP9P'));
        $this->assertNotNull($result);
    }

    #[Test]
    public function sp_api_returns_offers(): void
    {
        if (empty(config('price-intelligence.marketplaces.amazon.sp_api.refresh_token'))) {
            $this->markTestSkipped('No SP-API credentials configured.');
        }

        $result = app(AmazonSpApiClient::class)->fetchOffers((string) env('PI_LIVE_ASIN', 'B07PFFMP9P'));
        $this->assertNotNull($result);
    }

    #[Test]
    public function ebay_browse_returns_an_item(): void
    {
        if (empty(config('price-intelligence.marketplaces.ebay.client_id'))) {
            $this->markTestSkipped('No eBay credentials configured.');
        }

        $result = app(EbayBrowseClient::class)->fetchByLegacyId((string) env('PI_LIVE_EBAY_ID', '123456789012'));
        $this->assertNotNull($result);
    }
}
