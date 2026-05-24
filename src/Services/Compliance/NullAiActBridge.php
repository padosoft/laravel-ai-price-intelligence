<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Compliance;

use Padosoft\PriceIntelligence\Contracts\AiActBridgeInterface;

/**
 * No-op EU AI Act bridge, bound when padosoft/laravel-ai-act-compliance is not
 * installed. Native disclosure (is_ai_generated flags + ai_decision_logs) still
 * works without it.
 */
final class NullAiActBridge implements AiActBridgeInterface
{
    public function isActive(): bool
    {
        return false;
    }

    public function recordDisclosure(string $feature, array $context = []): void
    {
        // no-op
    }

    public function recordHumanReview(string $subject, array $context = []): void
    {
        // no-op
    }
}
