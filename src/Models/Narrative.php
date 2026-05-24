<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Illuminate\Support\Carbon;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property string $period
 * @property string $summary_md
 * @property array<string, mixed>|null $highlights
 * @property bool $is_ai_generated
 * @property string|null $model_version
 * @property Carbon $generated_at
 */
final class Narrative extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'narratives';

    protected $guarded = [];

    protected $casts = [
        'highlights' => 'array',
        'is_ai_generated' => 'boolean',
        'generated_at' => 'datetime',
    ];
}
