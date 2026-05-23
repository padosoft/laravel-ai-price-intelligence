<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Models\AiDecisionLog;
use Padosoft\PriceIntelligence\Support\Config\Flag;

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
        ?string $modelVersion = null,
    ): ?AiDecisionLog {
        // Respect both the global EU AI Act switch and the decision-log sub-toggle.
        // ai_act.enabled is 'auto'|bool: 'auto' means on; otherwise interpret as boolean so
        // 'false', '0', 0 and false all disable robustly — not just a strict `false`.
        $aiAct = config('price-intelligence.ai_act.enabled', 'auto');
        $aiActEnabled = $aiAct === 'auto' ? true : filter_var($aiAct, FILTER_VALIDATE_BOOLEAN);

        if (! $aiActEnabled) {
            return null;
        }

        if (! Flag::enabled('price-intelligence.ai_act.decision_log.enabled', true)) {
            return null;
        }

        return AiDecisionLog::query()->create([
            'tenant_id' => $tenantId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'feature' => $feature,
            'model' => $model,
            'model_version' => $modelVersion,
            'output' => $output,
            'confidence' => $confidence,
            'human_reviewed' => false,
        ]);
    }
}
