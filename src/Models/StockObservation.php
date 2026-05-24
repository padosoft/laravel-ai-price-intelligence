<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Illuminate\Support\Carbon;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property int $competitor_product_id
 * @property Carbon $captured_at
 * @property bool $available
 * @property int|null $qty_estimate
 * @property bool|null $buybox_winner
 */
final class StockObservation extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'stock_observations';

    protected $guarded = [];

    protected $casts = [
        'captured_at' => 'datetime',
        'available' => 'boolean',
        'qty_estimate' => 'integer',
        'buybox_winner' => 'boolean',
    ];
}
