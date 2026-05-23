<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Padosoft\PriceIntelligence\Enums\PromoType;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property int $competitor_product_id
 * @property \Illuminate\Support\Carbon $captured_at
 * @property PromoType $promo_type
 * @property float|null $effective_discount_pct
 */
final class PromoObservation extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'promo_observations';

    protected $guarded = [];

    protected $casts = [
        'captured_at' => 'datetime',
        'promo_type' => PromoType::class,
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'effective_discount_pct' => 'float',
    ];
}
