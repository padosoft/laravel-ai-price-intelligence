<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * Host catalog product synced into the package.
 *
 * @property int $id
 * @property int|string $tenant_id
 * @property string $external_id
 * @property string|null $sku
 * @property string|null $gtin
 * @property string|null $mpn
 * @property string|null $brand
 * @property string|null $model
 * @property string $name
 * @property array<string, mixed>|null $attributes
 * @property array<int, string>|null $images
 * @property array<int, string>|null $categories
 * @property int|null $our_price_cents
 * @property string|null $currency
 * @property string|null $base_country
 */
final class Product extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'products';

    protected $guarded = [];

    protected $casts = [
        'attributes' => 'array',
        'images' => 'array',
        'categories' => 'array',
        'our_price_cents' => 'integer',
    ];

    public function targets(): HasMany
    {
        return $this->hasMany(MonitoringTarget::class, 'product_id');
    }
}
