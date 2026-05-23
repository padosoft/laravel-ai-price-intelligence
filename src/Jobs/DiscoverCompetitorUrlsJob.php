<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Services\Discovery\UrlDiscoveryService;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;

final class DiscoverCompetitorUrlsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $monitoringTargetId,
        public readonly int|string $tenantId,
    ) {
        $this->onQueue((string) config('price-intelligence.queues.discovery', 'pi-discovery'));
    }

    public function handle(UrlDiscoveryService $discovery, TenantContext $tenant): void
    {
        $tenant->set($this->tenantId);

        $target = MonitoringTarget::query()->find($this->monitoringTargetId);

        if ($target === null || ! $target->isActive()) {
            return;
        }

        $discovery->discover($target);
    }
}
