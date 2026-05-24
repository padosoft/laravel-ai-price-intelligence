<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Padosoft\PriceIntelligence\Enums\RuleStrategy;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property string $name
 * @property array<string, mixed>|null $target_filter
 * @property RuleStrategy $strategy
 * @property array<string, mixed>|null $parameters
 * @property int $priority
 * @property string $status
 */
final class RepricingRule extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'repricing_rules';

    protected $guarded = [];

    protected $casts = [
        'target_filter' => 'array',
        'strategy' => RuleStrategy::class,
        'parameters' => 'array',
        'priority' => 'integer',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
