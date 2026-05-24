<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Matching;

use Padosoft\PriceIntelligence\Contracts\MatchStepInterface;
use Padosoft\PriceIntelligence\Data\MatchOutcome;
use Padosoft\PriceIntelligence\Data\MatchScore;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\MatchMethod;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Models\Product;

/**
 * Runs the cascade of match steps in order, short-circuiting on a conclusive
 * score (>=95). The best score across applicable steps is mapped onto a status
 * using the configured confidence band [low, high]:
 *   confidence >= high  -> confirmed
 *   low <= confidence   -> suggested (admin review)
 *   confidence < low    -> rejected
 *
 * Expensive steps (embedding, visual, LLM) are placed last by the caller so the
 * cheap deterministic ones (GTIN, MPN) can short-circuit first.
 */
final class MatchingPipeline
{
    /**
     * @param  array<int, MatchStepInterface>  $steps
     * @param  array{0: int, 1: int}  $confidenceBand
     */
    public function __construct(
        private readonly array $steps,
        private readonly array $confidenceBand = [60, 85],
    ) {}

    public function match(Product $product, ProductSnapshot $candidate): MatchOutcome
    {
        $best = MatchScore::none(MatchMethod::NormalizedName);
        $trail = [];

        foreach ($this->steps as $step) {
            if (! $step->applicable($product, $candidate)) {
                continue;
            }

            $score = $step->score($product, $candidate);
            $trail[] = $score->toArray();

            if ($score->confidence > $best->confidence) {
                $best = $score;
            }

            if ($score->isConclusive()) {
                break;
            }
        }

        return new MatchOutcome(
            status: $this->decide($best->confidence),
            confidence: $best->confidence,
            method: $best->method,
            trail: $trail,
        );
    }

    private function decide(int $confidence): MatchStatus
    {
        [$low, $high] = $this->confidenceBand;

        if ($confidence >= $high) {
            return MatchStatus::Confirmed;
        }

        if ($confidence >= $low) {
            return MatchStatus::Suggested;
        }

        return MatchStatus::Rejected;
    }
}
