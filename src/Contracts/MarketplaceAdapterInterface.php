<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\AdapterCode;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;

/**
 * A marketplace-specific fetcher (Amazon SP-API/Keepa, eBay API, Google
 * Shopping SERP, Idealo/Trovaprezzi scrape). Resolved by AdapterCode.
 */
interface MarketplaceAdapterInterface
{
    public function code(): AdapterCode;

    /**
     * @param  array<string, mixed>  $options
     */
    public function fetch(CompetitorProduct $competitorProduct, array $options = []): ProductSnapshot;
}
