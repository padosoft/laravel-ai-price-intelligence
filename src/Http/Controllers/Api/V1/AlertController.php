<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Models\Alert;

final class AlertController
{
    public function index(Request $request): JsonResponse
    {
        $alerts = Alert::query()
            ->when($request->boolean('unacknowledged'), fn ($q) => $q->whereNull('acknowledged_at'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($alerts);
    }

    public function acknowledge(int $id): JsonResponse
    {
        $alert = Alert::query()->findOrFail($id);
        $alert->acknowledge();

        return response()->json(['data' => $alert]);
    }
}
