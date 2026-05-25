<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Illuminate\Support\Carbon;
use Padosoft\PriceIntelligence\Enums\Severity;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property int $competitor_product_id
 * @property string $type
 * @property Severity $severity
 * @property array<string, mixed>|null $evidence
 * @property bool $is_ai_generated
 * @property Carbon $detected_at
 * @property Carbon|null $acknowledged_at
 */
final class Anomaly extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'anomalies';

    protected $guarded = [];

    protected $casts = [
        'severity' => Severity::class,
        'evidence' => 'array',
        'is_ai_generated' => 'boolean',
        'detected_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /** Mark this anomaly as reviewed (admin "acknowledge"); idempotent on the timestamp. */
    public function acknowledge(): void
    {
        $this->forceFill(['acknowledged_at' => now()])->save();
    }
}
