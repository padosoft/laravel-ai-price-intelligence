<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Catalog;

use Generator;
use League\Csv\Reader;
use Padosoft\PriceIntelligence\Data\ProductData;
use RuntimeException;

/**
 * Streams a CSV file into ProductData objects. Header row drives the column
 * mapping; list columns (images, categories) accept pipe-separated values.
 * Requires league/csv.
 */
final class CsvCatalogReader
{
    /**
     * @return Generator<int, ProductData>
     */
    public function read(string $path): Generator
    {
        if (! class_exists(Reader::class)) {
            throw new RuntimeException('league/csv is required for CSV import. Run: composer require league/csv');
        }

        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        foreach ($csv->getRecords() as $record) {
            /** @var array<string, mixed> $record */
            yield ProductData::fromArray($record);
        }
    }
}
