<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Padosoft\PriceIntelligence\Enums\AdapterCode;

/**
 * A known competitor domain with its scraping policy. Global (not tenant-scoped):
 * policies like robots and rate-limit are per-host across all tenants.
 *
 * @property int $id
 * @property string $host
 * @property string|null $display_name
 * @property string|null $country
 * @property AdapterCode $adapter_code
 * @property string $robots_policy
 * @property int|null $rate_limit_rpm
 * @property array<string, mixed>|null $options
 */
final class CompetitorSource extends PriceIntelligenceModel
{
    protected static string $configKey = 'competitor_sources';

    protected $guarded = [];

    protected $casts = [
        'adapter_code' => AdapterCode::class,
        'rate_limit_rpm' => 'integer',
        'options' => 'array',
    ];

    public function respectsRobots(): bool
    {
        return $this->robots_policy !== 'ignore';
    }
}
