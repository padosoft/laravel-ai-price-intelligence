<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\RepricingRule;
use Padosoft\PriceIntelligence\Models\RuleDecision;

/**
 * Evaluates a repricing rule for a product against competitor prices and
 * produces an advisory suggestion. NEVER applies the price.
 */
interface RepricerEngineInterface
{
    /**
     * @param  array<int, int>  $competitorPricesCents
     */
    public function evaluate(Product $product, RepricingRule $rule, array $competitorPricesCents): ?RuleDecision;
}
