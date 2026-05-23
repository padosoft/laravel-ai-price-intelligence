<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Matching\Steps;

use Padosoft\PriceIntelligence\Contracts\MatchStepInterface;
use Padosoft\PriceIntelligence\Data\MatchScore;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\MatchMethod;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Support\Identifiers\GtinValidator;

final class ExactGtinMatcher implements MatchStepInterface
{
    public function applicable(Product $product, ProductSnapshot $candidate): bool
    {
        return $product->gtin !== null && $product->gtin !== ''
            && $candidate->gtin !== null && $candidate->gtin !== '';
    }

    public function score(Product $product, ProductSnapshot $candidate): MatchScore
    {
        $equal = GtinValidator::equals((string) $product->gtin, (string) $candidate->gtin);

        return new MatchScore(
            confidence: $equal ? 100 : 0,
            method: MatchMethod::Gtin,
            evidence: [
                'product_gtin' => $product->gtin,
                'candidate_gtin' => $candidate->gtin,
                'match' => $equal,
            ],
        );
    }
}
