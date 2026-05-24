<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Config\Flag;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;

/**
 * Identity endpoint consumed by the admin panel: the resolved tenant, the toggleable
 * feature flags (so the UI can hide off modules), and the caller's abilities.
 */
final class TenantController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function me(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->id();
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;

        return response()->json([
            'data' => [
                'tenant' => $tenant !== null
                    ? ['id' => $tenant->id, 'code' => $tenant->code, 'name' => $tenant->name]
                    : ['id' => $tenantId, 'code' => null, 'name' => null],
                'features' => $this->features(),
                'abilities' => $this->abilities($request),
            ],
        ]);
    }

    /**
     * Feature flags the admin UI uses to show/hide conditional sections. Single source
     * of truth = the core's config (never duplicated in the UI).
     *
     * @return array<string, bool>
     */
    private function features(): array
    {
        return [
            'review_insight' => Flag::enabled('price-intelligence.review_insight.enabled', false),
            'repricer' => Flag::enabled('price-intelligence.repricer.enabled', false),
            'ai_act' => Flag::enabled('price-intelligence.ai_act.enabled', true),
            'pii' => Flag::enabled('price-intelligence.pii.enabled', true),
            'visual_match' => Flag::enabled('price-intelligence.ai.visual_match.enabled', true),
            'content_gap' => Flag::enabled('price-intelligence.ai.content_gap.enabled', true),
            'forecast' => Flag::enabled('price-intelligence.ai.forecast.enabled', true),
            'anomaly' => Flag::enabled('price-intelligence.ai.anomaly.enabled', true),
            'narrative' => Flag::enabled('price-intelligence.ai.narrative.enabled', true),
            'promo_detection' => Flag::enabled('price-intelligence.ai.promo_detection.enabled', true),
            'assortment' => Flag::enabled('price-intelligence.ai.assortment.enabled', true),
        ];
    }

    /**
     * Abilities of the current caller: the API key's scopes for machine-to-machine
     * auth, or full access for a session (the host app gates the SPA itself).
     *
     * @return array<int, string>
     */
    private function abilities(Request $request): array
    {
        $plaintext = $request->header('X-Api-Key');

        if (is_string($plaintext) && $plaintext !== '') {
            $apiKey = ApiKey::query()->where('key_hash', ApiKey::hash($plaintext))->first();

            if ($apiKey === null) {
                return [];
            }

            return $apiKey->scopes ?? [];
        }

        return ['*'];
    }
}
