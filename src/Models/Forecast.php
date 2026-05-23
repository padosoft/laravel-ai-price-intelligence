<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property int $competitor_product_id
 * @property int $horizon_days
 * @property int $forecast_price_cents
 * @property int|null $ci_low_cents
 * @property int|null $ci_high_cents
 * @property string $model_version
 * @property bool $is_ai_generated
 * @property \Illuminate\Support\Carbon $generated_at
 */
final class Forecast extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'forecasts';

    protected $guarded = [];

    protected $casts = [
        'horizon_days' => 'integer',
        'forecast_price_cents' => 'integer',
        'ci_low_cents' => 'integer',
        'ci_high_cents' => 'integer',
        'is_ai_generated' => 'boolean',
        'generated_at' => 'datetime',
    ];
}
