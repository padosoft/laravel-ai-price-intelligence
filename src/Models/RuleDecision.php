<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * Log of a repricing suggestion. The engine NEVER applies prices — applied stays
 * false unless the host writes it back after acting on the suggestion.
 *
 * @property int $id
 * @property int|string $tenant_id
 * @property int $repricing_rule_id
 * @property int $product_id
 * @property int|null $current_price_cents
 * @property int|null $suggested_price_cents
 * @property bool $applied
 * @property string|null $reason
 * @property array<string, mixed>|null $evidence
 */
final class RuleDecision extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'rule_decisions';

    protected $guarded = [];

    protected $casts = [
        'current_price_cents' => 'integer',
        'suggested_price_cents' => 'integer',
        'applied' => 'boolean',
        'evidence' => 'array',
    ];
}
