<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

/**
 * Inbound catalog product payload (from bulk JSON, CSV, webhook or command).
 */
final class ProductData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $images
     * @param  array<int, string>  $categories
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $name,
        public readonly ?string $sku = null,
        public readonly ?string $gtin = null,
        public readonly ?string $mpn = null,
        public readonly ?string $brand = null,
        public readonly ?string $model = null,
        public readonly array $attributes = [],
        public readonly array $images = [],
        public readonly array $categories = [],
        public readonly ?int $ourPriceCents = null,
        public readonly ?string $currency = null,
        public readonly ?string $baseCountry = null,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            externalId: (string) ($row['external_id'] ?? $row['externalId'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            sku: self::str($row['sku'] ?? null),
            gtin: self::str($row['gtin'] ?? $row['ean'] ?? null),
            mpn: self::str($row['mpn'] ?? null),
            brand: self::str($row['brand'] ?? null),
            model: self::str($row['model'] ?? null),
            attributes: is_array($row['attributes'] ?? null) ? $row['attributes'] : [],
            images: self::list($row['images'] ?? null),
            categories: self::list($row['categories'] ?? null),
            ourPriceCents: isset($row['our_price_cents']) ? (int) $row['our_price_cents'] : null,
            currency: self::str($row['currency'] ?? null),
            baseCountry: self::str($row['base_country'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'external_id' => $this->externalId,
            'name' => $this->name,
            'sku' => $this->sku,
            'gtin' => $this->gtin,
            'mpn' => $this->mpn,
            'brand' => $this->brand,
            'model' => $this->model,
            'attributes' => $this->attributes,
            'images' => $this->images,
            'categories' => $this->categories,
            'our_price_cents' => $this->ourPriceCents,
            'currency' => $this->currency,
            'base_country' => $this->baseCountry,
        ];
    }

    private static function str(mixed $value): ?string
    {
        if (! is_string($value)) {
            return is_int($value) ? (string) $value : null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<int, string>
     */
    private static function list(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn ($v): string => is_string($v) ? trim($v) : (string) $v,
                $value,
            ), static fn (string $v): bool => $v !== ''));
        }

        if (is_string($value) && $value !== '') {
            return array_values(array_filter(array_map('trim', explode('|', $value))));
        }

        return [];
    }
}
