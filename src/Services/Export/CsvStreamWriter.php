<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Export;

use League\Csv\Writer;

/**
 * Builds a streaming CSV callback for response()->streamDownload(): writes a header then
 * each row from a (lazy) iterable straight to php://output, so 100k+ rows never materialize
 * in memory at once.
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
            $csv = Writer::createFromStream(fopen('php://output', 'w'));
            $csv->insertOne($header);
            foreach ($rows as $row) {
                $csv->insertOne($row);
            }
        };
    }
}
