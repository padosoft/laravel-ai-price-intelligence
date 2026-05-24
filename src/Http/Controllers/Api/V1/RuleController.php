<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Padosoft\PriceIntelligence\Enums\RuleStrategy;
use Padosoft\PriceIntelligence\Models\RepricingRule;
use Padosoft\PriceIntelligence\Models\RuleDecision;

/**
 * No-code repricing rules (advisory only — the engine never applies prices). Backs the
 * admin's Repricer screen: rule CRUD + the decision log.
 */
final class RuleController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => RepricingRule::query()->orderBy('priority')->orderByDesc('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateRule($request, creating: true);

        $rule = RepricingRule::query()->create([
            'name' => $validated['name'],
            'target_filter' => $validated['target_filter'] ?? null,
            'strategy' => $validated['strategy'],
            'parameters' => $validated['parameters'] ?? [],
            'priority' => $validated['priority'] ?? 100,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json(['data' => $rule], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rule = RepricingRule::query()->findOrFail($id);
        $validated = $this->validateRule($request, creating: false);

        $rule->fill(array_filter([
            'name' => $validated['name'] ?? null,
            'target_filter' => $validated['target_filter'] ?? null,
            'strategy' => $validated['strategy'] ?? null,
            'parameters' => $validated['parameters'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'status' => $validated['status'] ?? null,
        ], static fn ($v): bool => $v !== null));
        $rule->save();

        return response()->json(['data' => $rule]);
    }

    public function destroy(int $id): Response
    {
        RepricingRule::query()->findOrFail($id)->delete();

        return response()->noContent();
    }

    public function decisions(Request $request): JsonResponse
    {
        $decisions = RuleDecision::query()
            ->when($request->filled('repricing_rule_id'), fn ($q) => $q->where('repricing_rule_id', $request->integer('repricing_rule_id')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($decisions);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRule(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:191'],
            'strategy' => [$required, Rule::enum(RuleStrategy::class)],
            'target_filter' => ['nullable', 'array'],
            'parameters' => ['nullable', 'array'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(['active', 'paused'])],
        ]);
    }
}
