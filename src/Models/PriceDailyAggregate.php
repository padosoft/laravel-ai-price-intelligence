<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property int $competitor_product_id
 * @property string $day ISO date (Y-m-d); stored as a plain string so updateOrCreate lookups match exactly
 * @property int|null $min_price_cents
 * @property int|null $max_price_cents
 * @property int|null $avg_price_cents
 * @property int $samples
 * @property string|null $currency
 */
final class PriceDailyAggregate extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'price_daily_aggregates';

    protected $guarded = [];

    protected $casts = [
        'min_price_cents' => 'integer',
        'max_price_cents' => 'integer',
        'avg_price_cents' => 'integer',
        'samples' => 'integer',
    ];
}
