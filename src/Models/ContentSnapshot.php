<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Illuminate\Support\Carbon;
use Padosoft\PriceIntelligence\Enums\AttributeExtractionStatus;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property int $competitor_product_id
 * @property Carbon $captured_at
 * @property string|null $title
 * @property array<string, mixed>|null $attributes
 * @property string|null $html_hash
 * @property AttributeExtractionStatus|null $attributes_status
 * @property string|null $attributes_signature
 * @property Carbon|null $attributes_extracted_at
 */
final class ContentSnapshot extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'content_snapshots';

    protected $guarded = [];

    protected $casts = [
        'captured_at' => 'datetime',
        'attributes' => 'array',
        'og' => 'array',
        'jsonld' => 'array',
        'images' => 'array',
        'dom_diff_score' => 'float',
        'attributes_status' => AttributeExtractionStatus::class,
        'attributes_extracted_at' => 'datetime',
    ];
}
