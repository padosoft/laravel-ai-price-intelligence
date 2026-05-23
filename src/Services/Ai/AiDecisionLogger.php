<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Models\AiDecisionLog;

/**
 * Records every AI-generated decision for EU AI Act auditability. No-op when
 * decision logging is disabled in config.
 */
final class AiDecisionLogger
{
    /**
     * @param  array<string, mixed>  $output
     */
    public function record(
        int|string $tenantId,
        string $feature,
        array $output,
        ?string $model = null,
        ?int $confidence = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): ?AiDecisionLog {
        if (! (bool) config('price-intelligence.ai_act.decision_log.enabled', true)) {
            return null;
        }

        return AiDecisionLog::query()->create([
            'tenant_id' => $tenantId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'feature' => $feature,
            'model' => $model,
            'output' => $output,
            'confidence' => $confidence,
            'human_reviewed' => false,
        ]);
    }
}
