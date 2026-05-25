<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Models\AiDecisionLog;

/**
 * Read side of the EU AI Act decision log (admin Compliance screen). Tenant-scoped,
 * cursor-paginated, filterable by feature and subject.
 */
final class AiDecisionController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'feature' => ['nullable', 'string', 'max:50'],
            'subject_type' => ['nullable', 'string', 'max:100'],
            'subject_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $logs = AiDecisionLog::query()
            ->when($request->filled('feature'), fn ($q) => $q->where('feature', $request->string('feature')->toString()))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->string('subject_type')->toString()))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<', $request->date('to')->addDay()->startOfDay()))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($logs);
    }
}
