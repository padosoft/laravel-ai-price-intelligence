<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property array<string, mixed>|null $settings
 */
final class Tenant extends PriceIntelligenceModel
{
    protected static string $configKey = 'tenants';

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'tenant_id');
    }
}
