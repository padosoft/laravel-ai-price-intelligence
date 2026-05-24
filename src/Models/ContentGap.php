<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Illuminate\Support\Carbon;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property int $product_id
 * @property int $seo_score_delta
 * @property array<int, string>|null $missing_attributes
 * @property array<int, string>|null $title_recommendations
 * @property array<int, string>|null $description_recommendations
 * @property int $image_count_gap
 * @property bool $is_ai_generated
 * @property Carbon $generated_at
 */
final class ContentGap extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'content_gaps';

    protected $guarded = [];

    protected $casts = [
        'seo_score_delta' => 'integer',
        'missing_attributes' => 'array',
        'title_recommendations' => 'array',
        'description_recommendations' => 'array',
        'image_count_gap' => 'integer',
        'is_ai_generated' => 'boolean',
        'generated_at' => 'datetime',
    ];
}
