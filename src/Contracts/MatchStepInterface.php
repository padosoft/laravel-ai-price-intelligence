<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Data\MatchScore;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Models\Product;

/**
 * One stage of the matching cascade. Returns a MatchScore for the (host product,
 * candidate snapshot) pair. May return confidence 0 to defer to later steps.
 */
interface MatchStepInterface
{
    public function score(Product $product, ProductSnapshot $candidate): MatchScore;

    /**
     * Whether this step should run given the data available (e.g. GTIN step
     * only runs when both sides have a GTIN). Cheap pre-check.
     */
    public function applicable(Product $product, ProductSnapshot $candidate): bool;
}
