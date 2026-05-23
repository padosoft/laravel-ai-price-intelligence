<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property int|string $tenant_id
 * @property string $url
 * @property array<int, string>|null $events
 * @property string|null $secret
 * @property bool $active
 */
final class WebhookSubscription extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'webhook_subscriptions';

    protected $guarded = [];

    protected $casts = [
        'events' => 'array',
        'active' => 'boolean',
        'last_at' => 'datetime',
    ];

    /**
     * Secret stored encrypted at rest, exposed transparently as $subscription->secret.
     */
    protected function secret(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->secret_encrypted !== null ? decrypt($this->secret_encrypted) : null,
            set: fn (?string $value): array => ['secret_encrypted' => $value !== null ? encrypt($value) : null],
        );
    }

    public function subscribesTo(string $event): bool
    {
        $events = $this->events ?? [];

        return $events === [] || in_array('*', $events, true) || in_array($event, $events, true);
    }
}
