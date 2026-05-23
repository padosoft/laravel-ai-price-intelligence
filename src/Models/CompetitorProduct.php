<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padosoft\PriceIntelligence\Enums\MatchMethod;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * A matched competitor URL for a monitoring target.
 *
 * @property int $id
 * @property int|string $tenant_id
 * @property int $monitoring_target_id
 * @property int|null $competitor_source_id
 * @property string $url
 * @property string|null $external_ref
 * @property MatchStatus $match_status
 * @property int|null $match_confidence
 * @property MatchMethod|null $match_method
 * @property int|null $validated_by
 * @property \Illuminate\Support\Carbon|null $validated_at
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $dead_since
 */
final class CompetitorProduct extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'competitor_products';

    protected $guarded = [];

    protected $casts = [
        'match_status' => MatchStatus::class,
        'match_method' => MatchMethod::class,
        'match_confidence' => 'integer',
        'validated_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'dead_since' => 'datetime',
    ];

    public function target(): BelongsTo
    {
        return $this->belongsTo(MonitoringTarget::class, 'monitoring_target_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CompetitorSource::class, 'competitor_source_id');
    }
}
