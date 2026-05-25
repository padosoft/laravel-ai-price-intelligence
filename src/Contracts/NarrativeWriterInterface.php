<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Data\NarrativeResult;

interface NarrativeWriterInterface
{
    /**
     * @param  array<string, mixed>  $context  aggregated weekly signals (top movers, promos, anomalies)
     */
    public function write(int|string $tenantId, string $period, array $context): NarrativeResult;
}
