<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Data\ContentGapResult;
use Padosoft\PriceIntelligence\Models\Product;

interface ContentGapAnalyzerInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $competitorSnapshots
     */
    public function analyze(Product $product, array $competitorSnapshots): ContentGapResult;
}
