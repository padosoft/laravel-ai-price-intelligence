<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Padosoft\PriceIntelligence\Models\WebhookSubscription;
use Padosoft\PriceIntelligence\Services\Webhooks\WebhookDispatcher;

final class WebhookController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => WebhookSubscription::query()->orderByDesc('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2000'],
            'events' => ['nullable', 'array'],
            'secret' => ['nullable', 'string', 'max:191'],
            'active' => ['nullable', 'boolean'],
        ]);

        $subscription = new WebhookSubscription;
        $subscription->fill([
            'url' => $validated['url'],
            'events' => $validated['events'] ?? ['*'],
            'active' => $validated['active'] ?? true,
        ]);

        if (! empty($validated['secret'])) {
            $subscription->secret = $validated['secret'];
        }

        $subscription->save();

        return response()->json(['data' => $subscription], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $subscription = WebhookSubscription::query()->findOrFail($id);

        $validated = $request->validate([
            'url' => ['nullable', 'url', 'max:2000'],
            'events' => ['nullable', 'array'],
            'active' => ['nullable', 'boolean'],
            'secret' => ['nullable', 'string', 'max:191'],
        ]);

        $subscription->fill(array_filter([
            'url' => $validated['url'] ?? null,
            'events' => $validated['events'] ?? null,
            'active' => $validated['active'] ?? null,
        ], static fn ($v): bool => $v !== null));

        if (! empty($validated['secret'])) {
            $subscription->secret = $validated['secret'];
        }

        $subscription->save();

        return response()->json(['data' => $subscription]);
    }

    public function destroy(int $id): Response
    {
        WebhookSubscription::query()->findOrFail($id)->delete();

        return response()->noContent();
    }

    public function test(int $id, WebhookDispatcher $dispatcher): JsonResponse
    {
        $subscription = WebhookSubscription::query()->findOrFail($id);

        $delivered = $dispatcher->dispatchToSubscription($subscription, 'digest.daily', ['test' => true]);

        return response()->json(['data' => ['delivered' => $delivered]]);
    }
}
