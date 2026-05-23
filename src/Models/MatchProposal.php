<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * A candidate match awaiting admin review (confidence in the [low, high) band).
 *
 * @property int $id
 * @property int|string $tenant_id
 * @property int $monitoring_target_id
 * @property int|null $competitor_source_id
 * @property string $candidate_url
 * @property array<string, mixed>|null $evidence
 * @property int $confidence
 * @property string $source
 * @property string $status
 * @property int|null $reviewer_id
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 */
final class MatchProposal extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'match_proposals';

    protected $guarded = [];

    protected $casts = [
        'evidence' => 'array',
        'confidence' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function target(): BelongsTo
    {
        return $this->belongsTo(MonitoringTarget::class, 'monitoring_target_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
