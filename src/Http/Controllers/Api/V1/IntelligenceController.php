<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Models\Anomaly;
use Padosoft\PriceIntelligence\Models\Forecast;

/**
 * Read endpoints for the AI/derived signals the admin's Intelligence screens consume.
 * All queries are tenant-scoped automatically via BelongsToTenant.
 */
final class IntelligenceController
{
    public function forecasts(Request $request): JsonResponse
    {
        $forecasts = Forecast::query()
            ->when($request->filled('competitor_product_id'), fn ($q) => $q->where('competitor_product_id', $request->integer('competitor_product_id')))
            ->when($request->filled('horizon'), fn ($q) => $q->where('horizon_days', $request->integer('horizon')))
            ->orderByDesc('generated_at')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($forecasts);
    }

    public function anomalies(Request $request): JsonResponse
    {
        $anomalies = Anomaly::query()
            ->when($request->filled('competitor_product_id'), fn ($q) => $q->where('competitor_product_id', $request->integer('competitor_product_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')->toString()))
            ->when($request->boolean('unacknowledged'), fn ($q) => $q->whereNull('acknowledged_at'))
            ->when($request->filled('since'), fn ($q) => $q->where('detected_at', '>=', $request->date('since')))
            ->orderByDesc('detected_at')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($anomalies);
    }
}
