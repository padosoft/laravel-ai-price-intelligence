<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property int $competitor_product_id
 * @property \Illuminate\Support\Carbon $captured_at
 * @property int|null $price_cents
 * @property string|null $currency
 * @property int|null $price_base_cents
 * @property int|null $shipping_cents
 * @property bool $available
 */
final class PriceObservation extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'price_observations';

    protected $guarded = [];

    protected $casts = [
        'captured_at' => 'datetime',
        'price_cents' => 'integer',
        'price_base_cents' => 'integer',
        'shipping_cents' => 'integer',
        'available' => 'boolean',
    ];
}
