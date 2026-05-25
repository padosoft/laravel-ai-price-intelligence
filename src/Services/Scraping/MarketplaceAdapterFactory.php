<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping;

use Padosoft\PriceIntelligence\Contracts\MarketplaceAdapterInterface;
use Padosoft\PriceIntelligence\Contracts\ProductScraperInterface;
use Padosoft\PriceIntelligence\Enums\AdapterCode;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\AmazonAdapter;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\AmazonSpApiClient;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\EbayBrowseClient;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\FarfetchClient;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\KeepaClient;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\Api\SerpShoppingClient;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\EbayAdapter;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\FarfetchAdapter;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\GenericAdapter;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\GoogleShoppingAdapter;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\IdealoAdapter;
use Padosoft\PriceIntelligence\Services\Scraping\Marketplaces\TrovaprezziAdapter;

/**
 * Resolves the right MarketplaceAdapter for an AdapterCode. All adapters share
 * the bound ProductScraperInterface by default; host apps can register a custom
 * adapter (e.g. a real Amazon SP-API one) via config('price-intelligence.adapters').
 */
final class MarketplaceAdapterFactory
{
    public function __construct(
        private readonly ProductScraperInterface $scraper,
    ) {}

    public function make(AdapterCode $code): MarketplaceAdapterInterface
    {
        $overrides = (array) config('price-intelligence.adapters', []);

        if (isset($overrides[$code->value])) {
            $custom = $overrides[$code->value];
            if (is_string($custom) && class_exists($custom)) {
                /** @var MarketplaceAdapterInterface $instance */
                $instance = app($custom);

                return $instance;
            }
            if ($custom instanceof MarketplaceAdapterInterface) {
                return $custom;
            }
        }

        return match ($code) {
            AdapterCode::Amazon => new AmazonAdapter($this->scraper, app(AmazonSpApiClient::class), app(KeepaClient::class)),
            AdapterCode::Ebay => new EbayAdapter($this->scraper, app(EbayBrowseClient::class)),
            AdapterCode::GoogleShopping => new GoogleShoppingAdapter($this->scraper, app(SerpShoppingClient::class)),
            AdapterCode::Farfetch => new FarfetchAdapter($this->scraper, app(FarfetchClient::class)),
            AdapterCode::Idealo => new IdealoAdapter($this->scraper),
            AdapterCode::Trovaprezzi => new TrovaprezziAdapter($this->scraper),
            AdapterCode::Generic => new GenericAdapter($this->scraper),
        };
    }
}
