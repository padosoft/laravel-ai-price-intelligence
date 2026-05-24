<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Pricing;

use Padosoft\PriceIntelligence\Contracts\FxProviderInterface;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;

/**
 * Normalizes a snapshot's price to the configured base currency (default EUR)
 * for cross-country comparison, preserving the original price/currency too.
 */
final class PriceNormalizer
{
    public function __construct(
        private readonly FxProviderInterface $fx,
    ) {}

    /**
     * @return array{price_cents: ?int, currency: ?string, price_base_cents: ?int, available: bool}
     */
    public function normalize(ProductSnapshot $snapshot): array
    {
        $base = (string) config('price-intelligence.fx.base', 'EUR');
        $price = $snapshot->priceCents;
        $currency = $snapshot->currency;

        $baseCents = null;

        if ($price !== null && $currency !== null) {
            $baseCents = $this->fx->convert($price, $currency, $base);
        } elseif ($price !== null && $currency === null) {
            // Assume already in base currency when unspecified.
            $baseCents = $price;
        }

        return [
            'price_cents' => $price,
            'currency' => $currency,
            'price_base_cents' => $baseCents,
            'available' => $snapshot->available,
        ];
    }
}
