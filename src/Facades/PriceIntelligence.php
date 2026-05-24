<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Facades;

use Illuminate\Support\Facades\Facade;
use Padosoft\PriceIntelligence\PriceIntelligenceManager;

/**
 * @method static \Padosoft\PriceIntelligence\Support\Tenant\TenantContext tenant()
 * @method static \Padosoft\PriceIntelligence\PriceIntelligenceManager forTenant(int|string $tenantId)
 * @method static string version()
 *
 * @see PriceIntelligenceManager
 */
final class PriceIntelligence extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PriceIntelligenceManager::class;
    }
}
