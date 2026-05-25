<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

final class ApiProductResult
{
    /** @param array<int, string> $images */
    public function __construct(
        public readonly ?int $priceCents = null,
        public readonly ?string $currency = null,
        public readonly bool $available = true,
        public readonly ?string $title = null,
        public readonly ?string $brand = null,
        public readonly ?string $gtin = null,
        public readonly ?string $mpn = null,
        public readonly array $images = [],
        public readonly ?string $buyboxSeller = null,
        public readonly ?string $externalRef = null,
    ) {}

    public function toSnapshot(string $url): ProductSnapshot
    {
        return new ProductSnapshot(
            url: $url,
            priceCents: $this->priceCents,
            currency: $this->currency,
            available: $this->available,
            title: $this->title,
            images: $this->images,
            gtin: $this->gtin,
            mpn: $this->mpn,
            brand: $this->brand,
            buyboxSeller: $this->buyboxSeller,
        );
    }
}
