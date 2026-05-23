<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Webhooks;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Padosoft\PriceIntelligence\Models\WebhookSubscription;
use Throwable;

/**
 * Delivers a signed event payload to all active subscriptions of a tenant that
 * are subscribed to the event. Failures are swallowed per-endpoint (recorded on
 * the subscription) so one bad endpoint never blocks the others.
 */
final class WebhookDispatcher
{
    /**
     * Fan out an event to every matching active subscription of the tenant.
     *
     * @param  array<string, mixed>  $data
     * @return int number of successful deliveries
     */
    public function dispatch(string $event, int|string $tenantId, array $data, bool $isAiGenerated = false): int
    {
        $body = $this->buildBody($event, $tenantId, $data, $isAiGenerated);

        // Explicitly scope by tenant rather than relying on the ambient TenantContext
        // global scope, so delivery is always correct regardless of caller context.
        $subscriptions = WebhookSubscription::query()
            ->withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('active', true)
            ->get();

        $delivered = 0;

        foreach ($subscriptions as $subscription) {
            if ($subscription->subscribesTo($event) && $this->deliver($subscription, $body)) {
                $delivered++;
            }
        }

        return $delivered;
    }

    /**
     * Send an event to exactly one subscription (used by the "test" endpoint).
     *
     * @param  array<string, mixed>  $data
     */
    public function dispatchToSubscription(WebhookSubscription $subscription, string $event, array $data, bool $isAiGenerated = false): bool
    {
        $body = $this->buildBody($event, $subscription->tenant_id, $data, $isAiGenerated);

        return $this->deliver($subscription, $body);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildBody(string $event, int|string $tenantId, array $data, bool $isAiGenerated): string
    {
        $payload = [
            'id' => (string) Str::uuid(),
            'event' => $event,
            'tenant_id' => $tenantId,
            'occurred_at' => now()->toIso8601String(),
            'data' => $data,
            'is_ai_generated' => $isAiGenerated,
        ];

        return json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function deliver(WebhookSubscription $subscription, string $body): bool
    {
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

            return $response->successful();
        } catch (Throwable) {
            $subscription->forceFill(['last_status' => 0, 'last_at' => now()])->save();

            return false;
        }
    }
}
