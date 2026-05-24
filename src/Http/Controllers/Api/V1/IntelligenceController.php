<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Models\Anomaly;
use Padosoft\PriceIntelligence\Models\AssortmentGap;
use Padosoft\PriceIntelligence\Models\ContentGap;
use Padosoft\PriceIntelligence\Models\Forecast;
use Padosoft\PriceIntelligence\Models\Narrative;
use Padosoft\PriceIntelligence\Models\ReviewInsight;
use Padosoft\PriceIntelligence\Support\Config\Flag;

/**
 * Read endpoints for the AI/derived signals the admin's Intelligence screens consume.
 * All queries are tenant-scoped automatically via BelongsToTenant.
 */
final class IntelligenceController
{
    public function forecasts(Request $request): JsonResponse
    {
        $request->validate([
            'competitor_product_id' => ['nullable', 'integer'],
            'horizon' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $forecasts = Forecast::query()
            ->when($request->filled('competitor_product_id'), fn ($q) => $q->where('competitor_product_id', $request->integer('competitor_product_id')))
            ->when($request->filled('horizon'), fn ($q) => $q->where('horizon_days', $request->integer('horizon')))
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($forecasts);
    }

    public function anomalies(Request $request): JsonResponse
    {
        $request->validate([
            'since' => ['nullable', 'date'],
            'competitor_product_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:50'],
            'severity' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $anomalies = Anomaly::query()
            ->when($request->filled('competitor_product_id'), fn ($q) => $q->where('competitor_product_id', $request->integer('competitor_product_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')->toString()))
            ->when($request->boolean('unacknowledged'), fn ($q) => $q->whereNull('acknowledged_at'))
            ->when($request->filled('since'), fn ($q) => $q->where('detected_at', '>=', $request->date('since')))
            ->orderByDesc('detected_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($anomalies);
    }

    /**
     * Aggregated, anonymous review-sentiment insights (GDPR-safe module). Returns an
     * empty set when the review_insight feature is disabled in core config.
     */
    public function reviews(Request $request): JsonResponse
    {
        // GDPR-safe module is opt-in: when disabled, expose nothing (matches the contract
        // and avoids surfacing insights for a feature the tenant turned off).
        if (! Flag::enabled('price-intelligence.review_insight.enabled', false)) {
            return response()->json(['data' => [], 'meta' => ['enabled' => false]]);
        }

        $request->validate([
            'competitor_product_id' => ['nullable', 'integer'],
            'period' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $reviews = ReviewInsight::query()
            ->when($request->filled('competitor_product_id'), fn ($q) => $q->where('competitor_product_id', $request->integer('competitor_product_id')))
            ->when($request->filled('period'), fn ($q) => $q->where('period', $request->string('period')->toString()))
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($reviews);
    }

    public function narratives(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $narratives = Narrative::query()
            ->when($request->filled('period'), fn ($q) => $q->where('period', $request->string('period')->toString()))
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 20));

        return response()->json($narratives);
    }

    public function assortmentGaps(Request $request): JsonResponse
    {
        $request->validate([
            'competitor_source_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $gaps = AssortmentGap::query()
            ->when($request->filled('competitor_source_id'), fn ($q) => $q->where('competitor_source_id', $request->integer('competitor_source_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('importance_score')
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($gaps);
    }

    public function contentGaps(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $gaps = ContentGap::query()
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($gaps);
    }
}
