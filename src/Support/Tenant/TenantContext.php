<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Tenant;

/**
 * Holds the current tenant id for the request/job lifecycle. Models scope to it
 * automatically via the BelongsToTenant trait. Jobs restore it in handle().
 */
final class TenantContext
{
    private int|string|null $tenantId = null;

    public function id(): int|string|null
    {
        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    public function set(int|string|null $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function forget(): void
    {
        $this->tenantId = null;
    }

    /**
     * Run a callback under a specific tenant, restoring the previous context after.
     *
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public function runForTenant(int|string $tenantId, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->tenantId = $tenantId;

        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }
}
