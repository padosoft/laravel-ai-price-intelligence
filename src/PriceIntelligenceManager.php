<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence;

use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;

/**
 * Public entry point bound to the PriceIntelligence facade. Higher-level
 * orchestration helpers are added here as phases land (discovery, scrape,
 * matching, etc.).
 */
final class PriceIntelligenceManager
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function tenant(): TenantContext
    {
        return $this->tenantContext;
    }

    public function forTenant(int|string $tenantId): self
    {
        $this->tenantContext->set($tenantId);

        return $this;
    }

    public function version(): string
    {
        return '0.1.0-dev';
    }
}
