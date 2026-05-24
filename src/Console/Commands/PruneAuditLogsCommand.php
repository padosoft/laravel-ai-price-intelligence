<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Console\Commands;

use Illuminate\Console\Command;
use Padosoft\PriceIntelligence\Models\FetchLog;

final class PruneAuditLogsCommand extends Command
{
    protected $signature = 'piprice:audit:prune {--days= : Override the configured retention in days}';

    protected $description = 'Delete fetch audit logs older than the configured retention window';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('price-intelligence.compliance.audit.retention_days', 90);

        if ($days <= 0) {
            $this->warn('Retention is not positive; nothing pruned.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $deleted = FetchLog::query()->withoutTenantScope()->where('captured_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} fetch log(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
