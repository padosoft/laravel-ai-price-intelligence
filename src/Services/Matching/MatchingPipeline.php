<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Matching;

use Padosoft\PriceIntelligence\Contracts\BorderlineOnlyStep;
use Padosoft\PriceIntelligence\Contracts\MatchStepInterface;
use Padosoft\PriceIntelligence\Data\MatchOutcome;
use Padosoft\PriceIntelligence\Data\MatchScore;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\MatchMethod;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Models\Product;
use RuntimeException;

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

            // Expensive borderline-only steps (e.g. the LLM judge) run only when the best
            // score so far is uncertain, so confident or hopeless candidates skip the call.
            if ($step instanceof BorderlineOnlyStep && ! $this->isUncertain($best->confidence)) {
                continue;
            }

            try {
                $score = $step->score($product, $candidate);
            } catch (RuntimeException $e) {
                // A flaky LLM or JSON-decode failure must not fail the whole cascade; report it
                // for observability and fall back to the deterministic steps' best score.
                report($e);

                continue;
            }

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

    private function isUncertain(int $confidence): bool
    {
        [, $high] = $this->confidenceBand;
        $floor = max(0, $high - 45); // default band [60,85] => run judge for best in [40, 85)

        return $confidence >= $floor && $confidence < $high;
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
