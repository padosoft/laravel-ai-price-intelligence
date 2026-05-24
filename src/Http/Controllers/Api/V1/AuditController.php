<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Models\FetchLog;

/**
 * Audit read endpoint: the per-request fetch log the admin's Audit screen consumes.
 * Tenant-scoped; filterable by source, status and time window.
 */
final class AuditController
{
    public function fetchLogs(Request $request): JsonResponse
    {
        $request->validate([
            'since' => ['nullable', 'date'],
            'status' => ['nullable', 'integer'],
            'competitor_source_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $logs = FetchLog::query()
            ->when($request->filled('competitor_source_id'), fn ($q) => $q->where('competitor_source_id', $request->integer('competitor_source_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->integer('status')))
            ->when($request->filled('since'), fn ($q) => $q->where('captured_at', '>=', $request->date('since')))
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 100));

        return response()->json($logs);
    }
}
