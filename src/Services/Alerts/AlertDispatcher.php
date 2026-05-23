<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Alerts;

use Padosoft\PriceIntelligence\Models\Alert;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Services\Webhooks\WebhookDispatcher;

/**
 * Persists alerts and fans them out to webhook subscriptions. Higher-level
 * Laravel Notification channels (mail/slack/teams) can subscribe to the Alert
 * created event in the host app.
 */
final class AlertDispatcher
{
    public function __construct(
        private readonly PriceChangeEvaluator $evaluator,
        private readonly WebhookDispatcher $webhooks,
    ) {
    }

    /**
     * Evaluate a competitor price change and raise any resulting alerts.
     *
     * @return array<int, Alert>
     */
    public function fromPriceChange(
        CompetitorProduct $competitor,
        ?int $previousCents,
        ?int $currentCents,
        ?int $ourCents,
        bool $available,
    ): array {
        $decisions = $this->evaluator->evaluate($previousCents, $currentCents, $ourCents, $available);
        $alerts = [];

        foreach ($decisions as $decision) {
            $alerts[] = $this->raise(
                $competitor->tenant_id,
                $decision['type']->value,
                $decision['severity']->value,
                array_merge($decision['payload'], [
                    'competitor_product_id' => $competitor->id,
                    'url' => $competitor->url,
                ]),
                $competitor->id,
            );
        }

        return $alerts;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function raise(int|string $tenantId, string $type, string $severity, array $payload, ?int $competitorProductId = null): Alert
    {
        $alert = Alert::query()->create([
            'tenant_id' => $tenantId,
            'type' => $type,
            'severity' => $severity,
            'payload' => $payload,
            'competitor_product_id' => $competitorProductId,
        ]);

        $delivered = $this->webhooks->dispatch($type, $tenantId, $payload);

        $alert->forceFill(['channel_status' => ['webhook_delivered' => $delivered]])->save();

        return $alert;
    }
}
