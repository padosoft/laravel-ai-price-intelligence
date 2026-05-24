<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * Anonymous, aggregated competitor review sentiment. Never holds raw review text
 * or author info (GDPR-safe by construction — see ReviewAggregator).
 *
 * @property int $id
 * @property int|string $tenant_id
 * @property int $competitor_product_id
 * @property string $period
 * @property float $sentiment_score
 * @property array<int, array<string, mixed>>|null $themes
 * @property int $sample_count
 * @property bool $is_ai_generated
 * @property \Illuminate\Support\Carbon $generated_at
 */
final class ReviewInsight extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'review_insights';

    protected $guarded = [];

    protected $casts = [
        'sentiment_score' => 'float',
        'themes' => 'array',
        'sample_count' => 'integer',
        'is_ai_generated' => 'boolean',
        'generated_at' => 'datetime',
    ];
}
