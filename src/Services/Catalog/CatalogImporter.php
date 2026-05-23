<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Catalog;

use Illuminate\Support\Facades\DB;
use Padosoft\PriceIntelligence\Data\ProductData;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Support\Identifiers\GtinValidator;

/**
 * Idempotent catalog upsert keyed by (tenant_id, external_id). Validates GTINs
 * and normalizes them to canonical form; invalid GTINs are dropped (kept null)
 * rather than failing the whole row.
 */
final class CatalogImporter
{
    /**
     * @param  iterable<ProductData>  $products
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(iterable $products): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($products as $data) {
            if ($data->externalId === '' || $data->name === '') {
                $skipped++;

                continue;
            }

            $attributes = $data->toAttributes();
            $attributes['gtin'] = $this->normalizeGtin($data->gtin);

            $existing = Product::query()
                ->where('external_id', $data->externalId)
                ->first();

            if ($existing !== null) {
                $existing->fill($attributes)->save();
                $updated++;

                continue;
            }

            Product::create($attributes);
            $created++;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @param  iterable<ProductData>  $products
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importInTransaction(iterable $products): array
    {
        return DB::transaction(fn (): array => $this->import($products));
    }

    private function normalizeGtin(?string $gtin): ?string
    {
        if ($gtin === null) {
            return null;
        }

        return GtinValidator::isValid($gtin) ? GtinValidator::normalize($gtin) : null;
    }
}
