<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

/**
 * Converts an amount in cents from one ISO-4217 currency to another.
 * Implementations: Fixed (config rates), fixer.io, openexchangerates.
 */
interface FxProviderInterface
{
    public function convert(int $cents, string $from, string $to): int;

    public function rate(string $from, string $to): float;
}
