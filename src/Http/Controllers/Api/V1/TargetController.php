<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Enums\Frequency;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\Product;

final class TargetController
{
    public function index(Request $request): JsonResponse
    {
        $targets = MonitoringTarget::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($targets);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required_without:product_external_id', 'integer'],
            'product_external_id' => ['required_without:product_id', 'string'],
            'country' => ['required', 'string', 'size:2'],
            'locale' => ['nullable', 'string', 'max:10'],
            'frequency' => ['nullable', 'string'],
            'cron_custom' => ['nullable', 'string'],
            'given_urls' => ['nullable', 'array'],
            'given_domains' => ['nullable', 'array'],
            'priority' => ['nullable', 'integer'],
        ]);

        $product = isset($validated['product_id'])
            ? Product::query()->findOrFail($validated['product_id'])
            : Product::query()->where('external_id', $validated['product_external_id'])->firstOrFail();

        $frequency = Frequency::tryFrom($validated['frequency'] ?? 'daily') ?? Frequency::Daily;

        $target = MonitoringTarget::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'country' => strtoupper($validated['country']),
            ],
            [
                'locale' => $validated['locale'] ?? null,
                'frequency_preset' => $frequency,
                'cron_custom' => $validated['cron_custom'] ?? null,
                'status' => 'active',
                'priority' => $validated['priority'] ?? 100,
                'options' => array_filter([
                    'given_urls' => $validated['given_urls'] ?? null,
                    'given_domains' => $validated['given_domains'] ?? null,
                ]),
                'next_check_at' => now(),
            ],
        );

        return response()->json(['data' => $target], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $target = MonitoringTarget::query()->findOrFail($id);

        $validated = $request->validate([
            'status' => ['nullable', 'in:active,paused,stopped'],
            'frequency' => ['nullable', 'string'],
            'priority' => ['nullable', 'integer'],
        ]);

        if (isset($validated['status'])) {
            $target->status = $validated['status'];
        }

        if (isset($validated['frequency']) && ($f = Frequency::tryFrom($validated['frequency'])) !== null) {
            $target->frequency_preset = $f;
        }

        if (isset($validated['priority'])) {
            $target->priority = $validated['priority'];
        }

        $target->save();

        return response()->json(['data' => $target]);
    }
}
