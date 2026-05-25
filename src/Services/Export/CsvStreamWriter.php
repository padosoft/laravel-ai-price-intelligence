<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Export;

use League\Csv\Writer;
use RuntimeException;

/**
 * Builds a streaming CSV callback for response()->streamDownload(): writes a header then
 * each row from a (lazy) iterable straight to php://output, so 100k+ rows never materialize
 * in memory at once. Cell values are neutralized against CSV/formula injection.
 */
final class CsvStreamWriter
{
    /**
     * @param  array<int, string>  $header
     * @param  iterable<int, array<int, scalar|null>>  $rows
     */
    public function callback(array $header, iterable $rows): callable
    {
        return static function () use ($header, $rows): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new RuntimeException('Unable to open php://output for CSV streaming.');
            }

            $csv = Writer::createFromStream($stream);
            $csv->insertOne($header);
            foreach ($rows as $row) {
                $csv->insertOne(array_map([self::class, 'neutralize'], $row));
            }
        };
    }

    /**
     * Defang CSV/formula injection: a cell starting with = + - @ (or a leading tab/CR) is treated
     * as a formula by Excel/Sheets. Prefix such values with a single quote so they render as text.
     */
    private static function neutralize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
