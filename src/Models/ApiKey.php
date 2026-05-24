<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Padosoft\PriceIntelligence\Models\Concerns\BelongsToTenant;

/**
 * Machine-to-machine API key. The plaintext token is shown once at creation;
 * only its SHA-256 hash is stored.
 *
 * Tenant-scoped: management queries (list/revoke) only see the current tenant's keys.
 * The pi_tenant global scope is a no-op while no tenant is set, so the ResolveTenant
 * middleware can still look a key up by hash before the tenant is resolved.
 *
 * @property int $id
 * @property int|string $tenant_id
 * @property string $name
 * @property string $key_hash
 * @property array<int, string>|null $scopes
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 */
final class ApiKey extends PriceIntelligenceModel
{
    use BelongsToTenant;

    protected static string $configKey = 'api_keys';

    protected $guarded = [];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /**
     * @param  array<int, string>  $scopes
     * @return array{0: self, 1: string} the model and the one-time plaintext token
     */
    public static function issue(int|string $tenantId, string $name, array $scopes = ['*']): array
    {
        $plaintext = 'pi_'.Str::random(48);

        $key = new self;
        $key->forceFill([
            'tenant_id' => $tenantId,
            'name' => $name,
            'key_hash' => self::hash($plaintext),
            'scopes' => $scopes,
        ])->save();

        return [$key, $plaintext];
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];

        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }
}
