<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai\ReviewInsight;

use Padosoft\PriceIntelligence\Contracts\PiiFilterInterface;
use Padosoft\PriceIntelligence\Contracts\ReviewSentimentInterface;
use Padosoft\PriceIntelligence\Data\SentimentResult;
use Padosoft\PriceIntelligence\Exceptions\ReviewInsightDisabledException;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\ReviewInsight;
use Padosoft\PriceIntelligence\Support\Config\Flag;

/**
 * GDPR-safe review pipeline. Hard guarantees:
 *  - off unless review_insight.enabled is true;
 *  - per-domain opt-in (review_insight.allowed_domains);
 *  - requires strong PII redaction (laravel-pii-redactor) — refuses otherwise;
 *  - every review text is PII-redacted BEFORE analysis;
 *  - ONLY an anonymous aggregate is persisted — never raw text or author.
 */
final class ReviewAggregator
{
    public function __construct(
        private readonly PiiFilterInterface $pii,
        private readonly ReviewSentimentInterface $analyzer,
    ) {
    }

    /**
     * @param  array<int, string>  $rawReviewTexts
     */
    public function aggregate(CompetitorProduct $competitor, array $rawReviewTexts, string $period): ReviewInsight
    {
        $this->guard($competitor);

        // Redact PII from every review BEFORE any analysis or persistence.
        $redacted = array_map(fn (string $t): string => $this->pii->redact($t), $rawReviewTexts);

        $result = $this->analyzer->analyze($redacted);

        return $this->persist($competitor, $period, $result);
    }

    private function guard(CompetitorProduct $competitor): void
    {
        if (! Flag::enabled('price-intelligence.review_insight.enabled', false)) {
            throw ReviewInsightDisabledException::moduleOff();
        }

        // Domain opt-in is the most specific gate — check it before PII strength so a
        // non-opted-in domain reports the precise reason.
        $host = $competitor->source?->host;
        $allowed = (array) config('price-intelligence.review_insight.allowed_domains', []);

        if ($host === null || ! in_array($host, $allowed, true)) {
            throw ReviewInsightDisabledException::domainNotAllowed($host);
        }

        if (! $this->pii->isStrong()) {
            throw ReviewInsightDisabledException::weakPii();
        }
    }

    private function persist(CompetitorProduct $competitor, string $period, SentimentResult $result): ReviewInsight
    {
        // Match the full unique index (tenant_id, competitor_product_id, period) so the
        // upsert can never collide across tenants.
        return ReviewInsight::query()->updateOrCreate(
            [
                'tenant_id' => $competitor->tenant_id,
                'competitor_product_id' => $competitor->id,
                'period' => $period,
            ],
            [
                'sentiment_score' => $result->score,
                'themes' => $result->themes,
                'sample_count' => $result->sampleCount,
                'is_ai_generated' => true,
                'generated_at' => now(),
            ],
        );
    }
}
