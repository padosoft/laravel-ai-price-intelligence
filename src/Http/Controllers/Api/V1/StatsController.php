<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Models\Alert;
use Padosoft\PriceIntelligence\Models\Anomaly;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\Product;

/**
 * Aggregate KPIs for the admin Dashboard. All counts are tenant-scoped via
 * BelongsToTenant on the underlying models.
 */
final class StatsController
{
    public function dashboard(): JsonResponse
    {
        $since24h = now()->subDay();

        return response()->json(['data' => [
            'products' => Product::query()->count(),
            'targets_active' => MonitoringTarget::query()->where('status', 'active')->count(),
            'competitors_monitored' => CompetitorProduct::query()->where('match_status', MatchStatus::Confirmed)->count(),
            'matches_pending' => CompetitorProduct::query()->where('match_status', MatchStatus::Suggested)->count(),
            'alerts_24h' => Alert::query()->where('created_at', '>=', $since24h)->count(),
            'alerts_unacknowledged' => Alert::query()->whereNull('acknowledged_at')->count(),
            'anomalies_24h' => Anomaly::query()->where('detected_at', '>=', $since24h)->count(),
        ]]);
    }
}
