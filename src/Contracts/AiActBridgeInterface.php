<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

/**
 * Optional bridge to padosoft/laravel-ai-act-compliance. A null-object
 * implementation is bound when that package is absent, so callers never need to
 * check for its presence.
 */
interface AiActBridgeInterface
{
    public function isActive(): bool;

    /**
     * Record an AI-generated output as an EU AI Act disclosure / governance event.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordDisclosure(string $feature, array $context = []): void;

    /**
     * Record that a human reviewed/overrode an AI decision (Art. human oversight).
     *
     * @param  array<string, mixed>  $context
     */
    public function recordHumanReview(string $subject, array $context = []): void;
}
