<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Padosoft\PriceIntelligence\Enums\AlertType;
use Padosoft\PriceIntelligence\Enums\Severity;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property AlertType $type
 * @property Severity $severity
 * @property array<string, mixed>|null $payload
 * @property int|null $product_id
 * @property int|null $competitor_product_id
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 */
final class Alert extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'alerts';

    protected $guarded = [];

    protected $casts = [
        'type' => AlertType::class,
        'severity' => Severity::class,
        'payload' => 'array',
        'channel_status' => 'array',
        'acknowledged_at' => 'datetime',
    ];

    public function acknowledge(): void
    {
        $this->forceFill(['acknowledged_at' => now()])->save();
    }
}
