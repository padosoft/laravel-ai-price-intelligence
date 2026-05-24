<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Padosoft\PriceIntelligence\Jobs\DiscoverCompetitorUrlsJob;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\MatchProposal;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Services\Matching\MatchPersister;

final class MatchController
{
    public function __construct(
        private readonly MatchPersister $persister,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status', 'pending')->toString();

        $proposals = MatchProposal::query()
            ->where('status', $status)
            ->orderByDesc('confidence')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($proposals);
    }

    public function approve(int $id): JsonResponse
    {
        $proposal = MatchProposal::query()->findOrFail($id);
        $competitor = $this->persister->approve($proposal, auth()->id());

        return response()->json(['data' => $competitor], 200);
    }

    public function reject(int $id): Response
    {
        $proposal = MatchProposal::query()->findOrFail($id);
        $this->persister->reject($proposal, auth()->id());

        return response()->noContent();
    }

    /**
     * Manually attach a competitor URL to a target (skips AI search).
     */
    public function storeCompetitorProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monitoring_target_id' => ['required', 'integer'],
            'url' => ['required', 'url', 'max:2000'],
            'external_ref' => ['nullable', 'string', 'max:191'],
        ]);

        $target = MonitoringTarget::query()->findOrFail($validated['monitoring_target_id']);

        $competitor = CompetitorProduct::query()->updateOrCreate(
            ['monitoring_target_id' => $target->id, 'url' => $validated['url']],
            [
                'tenant_id' => $target->tenant_id,
                'external_ref' => $validated['external_ref'] ?? null,
                'match_status' => 'confirmed',
                'match_confidence' => 100,
                'match_method' => 'manual',
                'validated_by' => auth()->id(),
                'validated_at' => now(),
            ],
        );

        return response()->json(['data' => $competitor], 201);
    }

    public function discoverNow(int $targetId): JsonResponse
    {
        $target = MonitoringTarget::query()->findOrFail($targetId);

        DiscoverCompetitorUrlsJob::dispatch($target->id, $target->tenant_id);

        return response()->json(['data' => ['queued' => true]], 202);
    }
}
