<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;

/**
 * API-key management for the admin's System screen. The plaintext key is returned
 * exactly once, at creation; only its hash is ever stored.
 */
final class ApiKeyController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:200']]);

        // Never expose key_hash; surface only safe metadata. Cursor-paginated like the
        // other list endpoints.
        $keys = ApiKey::query()
            ->orderByDesc('id')
            ->cursorPaginate(
                (int) $request->integer('per_page', 50),
                ['id', 'tenant_id', 'name', 'scopes', 'last_used_at', 'expires_at', 'revoked_at', 'created_at'],
            );

        return response()->json($keys);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'max:64'],
        ]);

        $tenantId = $this->tenantContext->id();
        abort_if($tenantId === null, 401, 'No tenant in context');

        [$apiKey, $plaintext] = ApiKey::issue($tenantId, $validated['name'], $validated['scopes'] ?? ['*']);

        // The plaintext is shown ONCE here and never recoverable afterwards.
        return response()->json([
            'data' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'scopes' => $apiKey->scopes,
                'plaintext' => $plaintext,
            ],
        ], 201);
    }

    public function revoke(int $id): JsonResponse
    {
        $apiKey = ApiKey::query()->findOrFail($id);
        $apiKey->forceFill(['revoked_at' => now()])->save();

        return response()->json(['data' => ['id' => $apiKey->id, 'revoked_at' => $apiKey->revoked_at]]);
    }
}
