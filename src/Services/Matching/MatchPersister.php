<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Matching;

use Padosoft\PriceIntelligence\Data\MatchOutcome;
use Padosoft\PriceIntelligence\Enums\MatchMethod;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\MatchProposal;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Services\Discovery\CompetitorSourceResolver;

/**
 * Translates a MatchOutcome into persistence:
 *  - confirmed -> CompetitorProduct (status=confirmed)
 *  - suggested -> MatchProposal (status=pending) for admin review
 *  - rejected  -> nothing persisted (caller may log)
 * Idempotent on (target, url): re-running won't create duplicates.
 */
final class MatchPersister
{
    public function __construct(
        private readonly CompetitorSourceResolver $sources,
    ) {}

    public function persist(MonitoringTarget $target, string $url, MatchOutcome $outcome): CompetitorProduct|MatchProposal|null
    {
        $source = $this->sources->resolveForUrl($url);

        if ($outcome->status === MatchStatus::Confirmed) {
            return CompetitorProduct::query()->updateOrCreate(
                ['monitoring_target_id' => $target->id, 'url' => $url],
                [
                    'tenant_id' => $target->tenant_id,
                    'competitor_source_id' => $source->id,
                    'match_status' => MatchStatus::Confirmed->value,
                    'match_confidence' => $outcome->confidence,
                    'match_method' => $outcome->method->value,
                ],
            );
        }

        if ($outcome->status === MatchStatus::Suggested) {
            return MatchProposal::query()->updateOrCreate(
                ['monitoring_target_id' => $target->id, 'candidate_url' => $url],
                [
                    'tenant_id' => $target->tenant_id,
                    'competitor_source_id' => $source->id,
                    'evidence' => $outcome->trail,
                    'confidence' => $outcome->confidence,
                    'source' => 'ai',
                    'status' => 'pending',
                ],
            );
        }

        return null;
    }

    /**
     * Approve a proposal: promote it to a confirmed CompetitorProduct.
     */
    public function approve(MatchProposal $proposal, ?int $reviewerId = null): CompetitorProduct
    {
        $competitor = CompetitorProduct::query()->updateOrCreate(
            ['monitoring_target_id' => $proposal->monitoring_target_id, 'url' => $proposal->candidate_url],
            [
                'tenant_id' => $proposal->tenant_id,
                'competitor_source_id' => $proposal->competitor_source_id,
                'match_status' => MatchStatus::Confirmed->value,
                'match_confidence' => $proposal->confidence,
                'match_method' => MatchMethod::Manual->value,
                'validated_by' => $reviewerId,
                'validated_at' => now(),
            ],
        );

        $proposal->forceFill(['status' => 'approved', 'reviewer_id' => $reviewerId, 'reviewed_at' => now()])->save();

        return $competitor;
    }

    public function reject(MatchProposal $proposal, ?int $reviewerId = null): void
    {
        $proposal->forceFill(['status' => 'rejected', 'reviewer_id' => $reviewerId, 'reviewed_at' => now()])->save();
    }
}
