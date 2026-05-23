<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Padosoft\PriceIntelligence\Enums\Frequency;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * A (product × country) tuple to monitor.
 *
 * @property int $id
 * @property int|string $tenant_id
 * @property int $product_id
 * @property string $country
 * @property string|null $locale
 * @property Frequency $frequency_preset
 * @property string|null $cron_custom
 * @property string $status
 * @property int $priority
 * @property array<string, mixed>|null $options
 * @property \Illuminate\Support\Carbon|null $last_check_at
 * @property \Illuminate\Support\Carbon|null $next_check_at
 * @property float $backoff_factor
 */
final class MonitoringTarget extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'monitoring_targets';

    protected $guarded = [];

    protected $casts = [
        'frequency_preset' => Frequency::class,
        'options' => 'array',
        'priority' => 'integer',
        'backoff_factor' => 'float',
        'last_check_at' => 'datetime',
        'next_check_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function competitorProducts(): HasMany
    {
        return $this->hasMany(CompetitorProduct::class, 'monitoring_target_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
