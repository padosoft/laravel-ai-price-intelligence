<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Console\Commands;

use Illuminate\Console\Command;
use Padosoft\PriceIntelligence\Services\Scheduling\TargetScheduler;

final class RunDueTargetsCommand extends Command
{
    protected $signature = 'piprice:run-due {--limit=1000 : Max targets to process this run}';

    protected $description = 'Dispatch scrape jobs for all monitoring targets that are due';

    public function handle(TargetScheduler $scheduler): int
    {
        $count = $scheduler->dispatchDue((int) $this->option('limit'));

        $this->info("Dispatched {$count} scrape job(s).");

        return self::SUCCESS;
    }
}
