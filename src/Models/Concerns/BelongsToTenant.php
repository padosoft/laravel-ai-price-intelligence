<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;

/**
 * Adds a global scope filtering rows to the current tenant and auto-fills
 * tenant_id on create. No-op when running in database-per-tenant mode where
 * isolation is handled at the connection level.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        if (self::tenancyMode() === 'database') {
            return;
        }

        static::addGlobalScope('pi_tenant', function (Builder $builder): void {
            $tenantId = self::currentTenantId();

            if ($tenantId !== null) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') === null) {
                $tenantId = self::currentTenantId();

                if ($tenantId !== null) {
                    $model->setAttribute('tenant_id', $tenantId);
                }
            }
        });
    }

    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('pi_tenant');
    }

    private static function currentTenantId(): int|string|null
    {
        if (! app()->bound(TenantContext::class)) {
            return null;
        }

        return app(TenantContext::class)->id();
    }

    private static function tenancyMode(): string
    {
        $mode = config('price-intelligence.tenancy.mode', 'single');

        return is_string($mode) ? $mode : 'single';
    }
}
