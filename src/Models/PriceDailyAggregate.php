<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property int $competitor_product_id
 * @property string $day ISO date (Y-m-d). Intentionally NOT date-cast at the Eloquent layer: kept as a plain Y-m-d string so updateOrCreate WHERE lookups bind the same value that is stored (a date cast persists 'Y-m-d 00:00:00' and breaks the idempotent match).
 * @property int|null $min_price_cents
 * @property int|null $max_price_cents
 * @property int|null $avg_price_cents
 * @property int $samples
 * @property string $currency
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
