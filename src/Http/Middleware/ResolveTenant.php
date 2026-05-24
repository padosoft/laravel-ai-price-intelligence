<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant from an X-Api-Key header (machine-to-machine) or,
 * failing that, from the authenticated Sanctum user via a configurable resolver.
 * Stores the resolved id in TenantContext for the rest of the request.
 */
final class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $apiKey = $this->resolveApiKey($request);

        if ($apiKey !== null) {
            if (! $apiKey->isUsable()) {
                return $this->deny('API key revoked or expired', 401);
            }

            if ($requiredScope !== null && ! $apiKey->hasScope($requiredScope)) {
                return $this->deny('Missing required scope: '.$requiredScope, 403);
            }

            $apiKey->forceFill(['last_used_at' => now()])->save();
            $this->tenantContext->set($apiKey->tenant_id);

            return $next($request);
        }

        $user = $request->user();

        if ($user !== null) {
            $resolver = config('price-intelligence.api.tenant_resolver');

            // Accept a callable OR an invokable class-string resolved via the container
            // (class-strings keep `php artisan config:cache` working; closures do not).
            if (is_string($resolver) && class_exists($resolver)) {
                $resolver = app($resolver);
            }

            $tenantId = is_callable($resolver)
                ? $resolver($user)
                : ($user->tenant_id ?? null);

            if ($tenantId !== null) {
                $this->tenantContext->set($tenantId);

                return $next($request);
            }
        }

        return $this->deny('Unauthenticated: provide a valid X-Api-Key or session', 401);
    }

    private function resolveApiKey(Request $request): ?ApiKey
    {
        $plaintext = $request->header('X-Api-Key');

        if (! is_string($plaintext) || $plaintext === '') {
            return null;
        }

        // Authentication is inherently cross-tenant (we resolve the tenant FROM the key),
        // so bypass the tenant global scope for this hash lookup.
        return ApiKey::query()
            ->withoutGlobalScope('pi_tenant')
            ->where('key_hash', ApiKey::hash($plaintext))
            ->first();
    }

    private function deny(string $detail, int $status): Response
    {
        return response()->json([
            'type' => 'about:blank',
            'title' => $status === 403 ? 'Forbidden' : 'Unauthorized',
            'status' => $status,
            'detail' => $detail,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
