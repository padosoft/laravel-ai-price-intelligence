<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Padosoft\PriceIntelligence\Models\Product
 */
final class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'sku' => $this->sku,
            'gtin' => $this->gtin,
            'mpn' => $this->mpn,
            'brand' => $this->brand,
            'model' => $this->model,
            'name' => $this->name,
            'attributes' => $this->attributes,
            'images' => $this->images,
            'categories' => $this->categories,
            'our_price_cents' => $this->our_price_cents,
            'currency' => $this->currency,
            'base_country' => $this->base_country,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
