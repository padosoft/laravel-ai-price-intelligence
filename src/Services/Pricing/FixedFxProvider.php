<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Pricing;

use Padosoft\PriceIntelligence\Contracts\FxProviderInterface;

/**
 * Offline FX provider using a static rate table (rates expressed against the
 * base currency). Default driver; host apps rebind to a live provider.
 */
final class FixedFxProvider implements FxProviderInterface
{
    /**
     * Rates are expressed against the base currency (the entry equal to 1.0).
     *
     * @param  array<string, float>  $ratesAgainstBase  e.g. ['EUR'=>1.0,'USD'=>1.08,'GBP'=>0.85]
     */
    public function __construct(
        private readonly array $ratesAgainstBase = ['EUR' => 1.0, 'USD' => 1.08, 'GBP' => 0.85],
    ) {}

    public function convert(int $cents, string $from, string $to): int
    {
        if (strtoupper($from) === strtoupper($to)) {
            return $cents;
        }

        return (int) round($cents * $this->rate($from, $to));
    }

    public function rate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        $rFrom = $this->ratesAgainstBase[$from] ?? null;
        $rTo = $this->ratesAgainstBase[$to] ?? null;

        if ($rFrom === null || $rTo === null || $rFrom == 0.0) {
            return 1.0;
        }

        // amount_base = amount_from / rFrom ; amount_to = amount_base * rTo
        return $rTo / $rFrom;
    }
}
