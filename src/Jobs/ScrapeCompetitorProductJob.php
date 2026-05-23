<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Services\Scraping\ScrapeService;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;

final class ScrapeCompetitorProductJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $competitorProductId,
        public readonly int|string $tenantId,
        /** @var array<string, mixed> */
        public readonly array $options = [],
    ) {
        $this->onQueue((string) config('price-intelligence.queues.scrape', 'pi-scrape'));
    }

    public function handle(ScrapeService $scraper, TenantContext $tenant): void
    {
        $tenant->set($this->tenantId);

        $competitor = CompetitorProduct::query()->find($this->competitorProductId);

        if ($competitor === null || ! $competitor->match_status->isActive()) {
            return;
        }

        $scraper->scrapeAndStore($competitor, $this->options);
    }
}
