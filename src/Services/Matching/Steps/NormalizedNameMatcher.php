<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Matching\Steps;

use Padosoft\PriceIntelligence\Contracts\MatchStepInterface;
use Padosoft\PriceIntelligence\Data\MatchScore;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\MatchMethod;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Support\Identifiers\SlugNormalizer;

/**
 * Scores by token-set similarity of (brand + model + name) vs the candidate
 * title, mapped onto a 0/70-90 band. A brand mismatch caps the score low.
 */
final class NormalizedNameMatcher implements MatchStepInterface
{
    public function applicable(Product $product, ProductSnapshot $candidate): bool
    {
        return $candidate->title !== null && $candidate->title !== '';
    }

    public function score(Product $product, ProductSnapshot $candidate): MatchScore
    {
        $left = trim(implode(' ', array_filter([$product->brand, $product->model, $product->name])));
        $right = (string) $candidate->title;

        $similarity = SlugNormalizer::tokenSimilarity($left, $right);

        // Brand sanity: if both brands present and disjoint, penalize hard.
        if ($product->brand !== null && $candidate->brand !== null) {
            $brandEq = SlugNormalizer::normalize($product->brand) === SlugNormalizer::normalize((string) $candidate->brand);
            if (! $brandEq) {
                $similarity *= 0.5;
            }
        }

        if ($similarity < 0.4) {
            return MatchScore::none(MatchMethod::NormalizedName, ['similarity' => round($similarity, 3)]);
        }

        // Map similarity [0.4,1.0] -> confidence [70,90].
        $confidence = (int) round(70 + (($similarity - 0.4) / 0.6) * 20);
        $confidence = max(70, min(90, $confidence));

        return new MatchScore(
            confidence: $confidence,
            method: MatchMethod::NormalizedName,
            evidence: ['similarity' => round($similarity, 3), 'left' => $left, 'right' => $right],
        );
    }
}
