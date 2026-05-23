<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * EU AI Act decision log: one row per AI-generated output for auditability.
 *
 * @property int $id
 * @property int|string $tenant_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $feature
 * @property string|null $model
 * @property string|null $model_version
 * @property string|null $input_hash
 * @property array<string, mixed>|null $output
 * @property int|null $confidence
 * @property int|null $cost_micros
 * @property bool $human_reviewed
 */
final class AiDecisionLog extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'ai_decision_logs';

    protected $guarded = [];

    protected $casts = [
        'subject_id' => 'integer',
        'output' => 'array',
        'confidence' => 'integer',
        'cost_micros' => 'integer',
        'human_reviewed' => 'boolean',
    ];
}
