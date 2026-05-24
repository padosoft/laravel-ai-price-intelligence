<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Illuminate\Support\Carbon;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property int|null $competitor_source_id
 * @property string $url
 * @property int|null $status
 * @property int|null $latency_ms
 * @property bool $robots_allowed
 * @property string|null $driver
 * @property Carbon $captured_at
 */
final class FetchLog extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'fetch_logs';

    protected $guarded = [];

    protected $casts = [
        'captured_at' => 'datetime',
        'status' => 'integer',
        'latency_ms' => 'integer',
        'proxy_used' => 'boolean',
        'robots_allowed' => 'boolean',
        'response_bytes' => 'integer',
    ];
}
