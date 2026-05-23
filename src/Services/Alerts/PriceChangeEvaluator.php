<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Alerts;

use Padosoft\PriceIntelligence\Enums\AlertType;
use Padosoft\PriceIntelligence\Enums\Severity;

/**
 * Pure decision logic: given the previous and current competitor price (in base
 * cents) and optionally our own price, decide which alerts to raise and at what
 * severity. Returns a list of [type, severity, payload].
 */
final class PriceChangeEvaluator
{
    /**
     * @return array<int, array{type: AlertType, severity: Severity, payload: array<string, mixed>}>
     */
    public function evaluate(?int $previousCents, ?int $currentCents, ?int $ourCents, bool $available): array
    {
        $decisions = [];

        if (! $available) {
            $decisions[] = $this->decision(AlertType::StockOut, Severity::Medium, [
                'previous_cents' => $previousCents,
            ]);

            return $decisions;
        }

        if ($currentCents === null) {
            return $decisions;
        }

        if ($previousCents !== null && $previousCents !== $currentCents) {
            $deltaPct = $previousCents > 0
                ? (($currentCents - $previousCents) / $previousCents) * 100
                : 0.0;

            $type = $currentCents < $previousCents ? AlertType::PriceDropped : AlertType::PriceRaised;
            $severity = $this->severityForDelta(abs($deltaPct));

            $decisions[] = $this->decision($type, $severity, [
                'previous_cents' => $previousCents,
                'current_cents' => $currentCents,
                'delta_pct' => round($deltaPct, 2),
            ]);
        }

        // Undercut: competitor strictly cheaper than us.
        if ($ourCents !== null && $currentCents < $ourCents) {
            $gapPct = $ourCents > 0 ? (($ourCents - $currentCents) / $ourCents) * 100 : 0.0;

            $decisions[] = $this->decision(AlertType::UndercutDetected, $this->severityForDelta($gapPct), [
                'our_cents' => $ourCents,
                'competitor_cents' => $currentCents,
                'gap_pct' => round($gapPct, 2),
            ]);
        }

        return $decisions;
    }

    private function severityForDelta(float $absPct): Severity
    {
        return match (true) {
            $absPct >= 20 => Severity::Critical,
            $absPct >= 10 => Severity::High,
            $absPct >= 3 => Severity::Medium,
            default => Severity::Low,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: AlertType, severity: Severity, payload: array<string, mixed>}
     */
    private function decision(AlertType $type, Severity $severity, array $payload): array
    {
        return ['type' => $type, 'severity' => $severity, 'payload' => $payload];
    }
}
