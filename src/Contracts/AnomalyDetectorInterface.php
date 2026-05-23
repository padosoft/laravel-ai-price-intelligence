<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

/**
 * Detects anomalies in a competitor price series. Returns a list of decisions:
 * [type, severity, evidence]. Pure/statistical by default; an LLM judge can be
 * layered on borderline cases by a custom implementation.
 */
interface AnomalyDetectorInterface
{
    /**
     * @param  array<int, int>  $priceSeriesCents  chronological, oldest first
     * @return array<int, array{type: string, severity: string, evidence: array<string, mixed>}>
     */
    public function detect(array $priceSeriesCents, int $currentCents): array;
}
