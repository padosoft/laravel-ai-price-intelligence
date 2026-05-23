<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BulkUpsertProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'products' => ['required', 'array', 'min:1', 'max:5000'],
            'products.*.external_id' => ['required', 'string', 'max:191'],
            'products.*.name' => ['required', 'string', 'max:1000'],
            'products.*.sku' => ['nullable', 'string', 'max:191'],
            'products.*.gtin' => ['nullable', 'string', 'max:32'],
            'products.*.mpn' => ['nullable', 'string', 'max:191'],
            'products.*.brand' => ['nullable', 'string', 'max:191'],
            'products.*.model' => ['nullable', 'string', 'max:191'],
            'products.*.attributes' => ['nullable', 'array'],
            'products.*.images' => ['nullable', 'array'],
            'products.*.categories' => ['nullable', 'array'],
            'products.*.our_price_cents' => ['nullable', 'integer', 'min:0'],
            'products.*.currency' => ['nullable', 'string', 'size:3'],
            'products.*.base_country' => ['nullable', 'string', 'size:2'],
        ];
    }
}
