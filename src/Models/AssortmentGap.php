<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property int|null $competitor_source_id
 * @property string $category_path
 * @property string|null $competitor_product_url
 * @property string|null $title
 * @property int $importance_score
 * @property string $status
 * @property bool $is_ai_generated
 */
final class AssortmentGap extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'assortment_gaps';

    protected $guarded = [];

    protected $casts = [
        'importance_score' => 'integer',
        'is_ai_generated' => 'boolean',
    ];
}
