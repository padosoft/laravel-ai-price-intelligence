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
use Padosoft\PriceIntelligence\Services\Pricing\Repricer\StrategyCalculator;

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

        // Apply only the keys the client actually sent (PATCH semantics), preserving an
        // explicit null so target_filter/parameters can be cleared.
        $updatable = ['name', 'target_filter', 'strategy', 'parameters', 'priority', 'status'];
        foreach ($updatable as $field) {
            if (array_key_exists($field, $validated)) {
                $rule->setAttribute($field, $validated[$field]);
            }
        }
        $rule->save();

        return response()->json(['data' => $rule]);
    }

    public function destroy(int $id): Response
    {
        RepricingRule::query()->findOrFail($id)->delete();

        return response()->noContent();
    }

    /**
     * Dry-run preview: compute the rule's suggested prices for caller-provided samples
     * WITHOUT persisting or firing events, and regardless of the repricer.enabled flag
     * (it's a what-if preview). Custom strategies aren't simulated (host-resolved).
     */
    public function simulate(Request $request, int $id, StrategyCalculator $calculator): JsonResponse
    {
        $rule = RepricingRule::query()->findOrFail($id);

        $validated = $request->validate([
            'samples' => ['required', 'array', 'min:1', 'max:500'],
            'samples.*.product_id' => ['nullable', 'integer'],
            'samples.*.current_price_cents' => ['nullable', 'integer', 'min:0'],
            'samples.*.competitor_prices_cents' => ['required', 'array'],
            'samples.*.competitor_prices_cents.*' => ['integer', 'min:0'],
        ]);

        $params = (array) ($rule->parameters ?? []);
        $custom = $rule->strategy === RuleStrategy::Custom;

        $decisions = array_map(function (array $sample) use ($rule, $calculator, $params, $custom): array {
            $current = isset($sample['current_price_cents']) ? (int) $sample['current_price_cents'] : null;
            /** @var array<int, int> $prices */
            $prices = array_map('intval', $sample['competitor_prices_cents']);
            $suggested = $custom ? null : $calculator->suggest($rule->strategy, $prices, $current, $params);

            return [
                'product_id' => $sample['product_id'] ?? null,
                'current_price_cents' => $current,
                'suggested_price_cents' => $suggested,
                'changed' => $suggested !== null && $suggested !== $current,
            ];
        }, $validated['samples']);

        return response()->json(['data' => [
            'rule_id' => $rule->id,
            'strategy' => $rule->strategy->value,
            'custom_not_simulated' => $custom,
            'decisions' => $decisions,
        ]]);
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
