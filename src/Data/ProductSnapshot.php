<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

use Padosoft\PriceIntelligence\Enums\PromoType;

/**
 * Normalized result of scraping a single competitor product page. Produced by
 * every scraper/adapter, consumed by PriceNormalizer and the matching pipeline.
 */
final class ProductSnapshot
{
    /**
     * @param  array<int, string>  $images
     * @param  array<int, string>  $breadcrumb
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $jsonld
     * @param  array<string, mixed>  $og
     */
    public function __construct(
        public readonly string $url,
        public readonly ?int $priceCents = null,
        public readonly ?string $currency = null,
        public readonly ?string $rawPriceText = null,
        public readonly ?int $shippingCents = null,
        public readonly bool $available = true,
        public readonly ?int $stockQty = null,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly array $images = [],
        public readonly array $breadcrumb = [],
        public readonly array $attributes = [],
        public readonly array $jsonld = [],
        public readonly array $og = [],
        public readonly ?string $gtin = null,
        public readonly ?string $mpn = null,
        public readonly ?string $brand = null,
        public readonly ?string $buyboxSeller = null,
        public readonly ?string $sellerRating = null,
        public readonly PromoType $promoType = PromoType::None,
        public readonly ?string $htmlHash = null,
        public readonly bool $reachable = true,
    ) {
    }

    public function withReachable(bool $reachable): self
    {
        return new self(
            url: $this->url,
            priceCents: $this->priceCents,
            currency: $this->currency,
            rawPriceText: $this->rawPriceText,
            shippingCents: $this->shippingCents,
            available: $this->available,
            stockQty: $this->stockQty,
            title: $this->title,
            description: $this->description,
            images: $this->images,
            breadcrumb: $this->breadcrumb,
            attributes: $this->attributes,
            jsonld: $this->jsonld,
            og: $this->og,
            gtin: $this->gtin,
            mpn: $this->mpn,
            brand: $this->brand,
            buyboxSeller: $this->buyboxSeller,
            sellerRating: $this->sellerRating,
            promoType: $this->promoType,
            htmlHash: $this->htmlHash,
            reachable: $reachable,
        );
    }

    public static function unreachable(string $url): self
    {
        return new self(url: $url, available: false, reachable: false);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'price_cents' => $this->priceCents,
            'currency' => $this->currency,
            'raw_price_text' => $this->rawPriceText,
            'shipping_cents' => $this->shippingCents,
            'available' => $this->available,
            'stock_qty' => $this->stockQty,
            'title' => $this->title,
            'description' => $this->description,
            'images' => $this->images,
            'breadcrumb' => $this->breadcrumb,
            'attributes' => $this->attributes,
            'jsonld' => $this->jsonld,
            'og' => $this->og,
            'gtin' => $this->gtin,
            'mpn' => $this->mpn,
            'brand' => $this->brand,
            'buybox_seller' => $this->buyboxSeller,
            'seller_rating' => $this->sellerRating,
            'promo_type' => $this->promoType->value,
            'html_hash' => $this->htmlHash,
            'reachable' => $this->reachable,
        ];
    }
}
