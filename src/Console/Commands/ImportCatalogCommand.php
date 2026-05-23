<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Console\Commands;

use Illuminate\Console\Command;
use Padosoft\PriceIntelligence\Services\Catalog\CatalogImporter;
use Padosoft\PriceIntelligence\Services\Catalog\CsvCatalogReader;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;

final class ImportCatalogCommand extends Command
{
    protected $signature = 'piprice:catalog:import {file : Path to a CSV file} {--tenant= : Tenant id to import under}';

    protected $description = 'Import a product catalog CSV into the price intelligence store';

    public function handle(CatalogImporter $importer, TenantContext $tenantContext): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $tenant = $this->option('tenant');

        if ($tenant !== null) {
            $tenantContext->set($tenant);
        }

        if (! $tenantContext->has()) {
            $this->error('No tenant context: pass --tenant=<id>.');

            return self::FAILURE;
        }

        $reader = new CsvCatalogReader();
        $result = $importer->importInTransaction($reader->read($file));

        $this->info(sprintf(
            'Imported: %d created, %d updated, %d skipped.',
            $result['created'],
            $result['updated'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
