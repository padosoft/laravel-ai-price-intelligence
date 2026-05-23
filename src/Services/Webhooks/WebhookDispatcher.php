<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Webhooks;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Models\WebhookSubscription;
use Throwable;

/**
 * Delivers a signed event payload to all active subscriptions of the current
 * tenant that are subscribed to the event. Failures are swallowed per-endpoint
 * (logged on the subscription) so one bad endpoint never blocks the others.
 */
final class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function dispatch(string $event, int|string $tenantId, array $data, bool $isAiGenerated = false): int
    {
        $payload = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'event' => $event,
            'tenant_id' => $tenantId,
            'occurred_at' => now()->toIso8601String(),
            'data' => $data,
            'is_ai_generated' => $isAiGenerated,
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}';
        $delivered = 0;

        // Explicitly scope by tenant rather than relying on the ambient TenantContext
        // global scope, so delivery is always correct regardless of caller context.
        $subscriptions = WebhookSubscription::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('active', true)
            ->get();

        foreach ($subscriptions as $subscription) {
            if (! $subscription->subscribesTo($event)) {
                continue;
            }

            $headers = ['Content-Type' => 'application/json'];

            if ($subscription->secret !== null) {
                $headers[WebhookSigner::HEADER] = WebhookSigner::sign($body, $subscription->secret);
            }

            try {
                $response = Http::withHeaders($headers)
                    ->timeout(10)
                    ->withBody($body, 'application/json')
                    ->post($subscription->url);

                $subscription->forceFill(['last_status' => $response->status(), 'last_at' => now()])->save();

                if ($response->successful()) {
                    $delivered++;
                }
            } catch (Throwable) {
                $subscription->forceFill(['last_status' => 0, 'last_at' => now()])->save();
            }
        }

        return $delivered;
    }
}
