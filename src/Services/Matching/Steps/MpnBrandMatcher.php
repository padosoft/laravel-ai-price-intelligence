<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Matching\Steps;

use Padosoft\PriceIntelligence\Contracts\MatchStepInterface;
use Padosoft\PriceIntelligence\Data\MatchScore;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\MatchMethod;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Support\Identifiers\MpnNormalizer;
use Padosoft\PriceIntelligence\Support\Identifiers\SlugNormalizer;

final class MpnBrandMatcher implements MatchStepInterface
{
    public function applicable(Product $product, ProductSnapshot $candidate): bool
    {
        return $product->mpn !== null && $product->mpn !== ''
            && $candidate->mpn !== null && $candidate->mpn !== '';
    }

    public function score(Product $product, ProductSnapshot $candidate): MatchScore
    {
        $mpnEqual = MpnNormalizer::equals((string) $product->mpn, (string) $candidate->mpn);

        if (! $mpnEqual) {
            return MatchScore::none(MatchMethod::MpnBrand, ['mpn_match' => false]);
        }

        // MPN matches. If brand also matches (or candidate brand unknown), high confidence.
        $brandOk = $this->brandCompatible($product, $candidate);

        return new MatchScore(
            confidence: $brandOk ? 95 : 80,
            method: MatchMethod::MpnBrand,
            evidence: [
                'mpn_match' => true,
                'brand_match' => $brandOk,
                'product_mpn' => $product->mpn,
            ],
        );
    }

    private function brandCompatible(Product $product, ProductSnapshot $candidate): bool
    {
        if ($product->brand === null || $candidate->brand === null) {
            return true;
        }

        return SlugNormalizer::normalize($product->brand) === SlugNormalizer::normalize((string) $candidate->brand);
    }
}
