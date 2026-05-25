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

    /**
     * Mark this anomaly as reviewed (admin "acknowledge"). Idempotent and race-safe: a single
     * atomic `WHERE acknowledged_at IS NULL` UPDATE sets the timestamp only if not already set,
     * so concurrent requests can't overwrite the original review time. The update is scoped to
     * this instance's own tenant (bypassing the ambient TenantContext global scope) so it stays
     * deterministic even if called from a multi-tenant job context. `updated_at` is set
     * explicitly to the same instant so it matches `acknowledged_at` exactly. The instance is
     * refreshed to reflect the stored state (whether this call or a concurrent one set it).
     */
    public function acknowledge(): void
    {
        $now = now();

        self::query()
            ->withoutTenantScope()
            ->whereKey($this->getKey())
            ->where('tenant_id', $this->tenant_id)
            ->whereNull('acknowledged_at')
            ->update(['acknowledged_at' => $now, 'updated_at' => $now]);

        $this->refresh();
    }
}
