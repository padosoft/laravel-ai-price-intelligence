<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Padosoft\PriceIntelligence\Models\RuleDecision;

/**
 * Fired when the repricer suggests a new price. The package NEVER applies the
 * price — the host app listens to this and decides (e.g. MarginOS).
 */
final class RepricingSuggested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly RuleDecision $decision,
    ) {}
}
