<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Padosoft\PriceIntelligence\Data\WantedAttribute;
use Padosoft\PriceIntelligence\Enums\Frequency;
use Padosoft\PriceIntelligence\Enums\MatchStatus;
use Padosoft\PriceIntelligence\Jobs\ScrapeCompetitorProductJob;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\Product;

final class TargetController
{
    private const MAX_WANTED_ATTRIBUTES = 50;

    private const MAX_ALLOWED_VALUES = 1000;

    private const MAX_ALLOWED_VALUES_TOTAL = 5000;

    public function index(Request $request): JsonResponse
    {
        $targets = MonitoringTarget::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('id')
            ->cursorPaginate((int) $request->integer('per_page', 50));

        return response()->json($targets);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required_without:product_external_id', 'integer'],
            'product_external_id' => ['required_without:product_id', 'string'],
            'country' => ['required', 'string', 'size:2'],
            'locale' => ['nullable', 'string', 'max:10'],
            'frequency' => ['nullable', 'string'],
            'cron_custom' => ['nullable', 'string'],
            'given_urls' => ['nullable', 'array'],
            'given_domains' => ['nullable', 'array'],
            'priority' => ['nullable', 'integer'],
            // Category attributes to read from the competitor pages (latest_content.attributes).
            'wanted_attributes' => ['nullable', 'array', 'max:'.self::MAX_WANTED_ATTRIBUTES],
            'wanted_attributes.*' => ['array'],
            'wanted_attributes.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,63}$/', 'distinct'],
            'wanted_attributes.*.label' => ['required', 'string', 'max:191'],
            'wanted_attributes.*.allowed_values' => ['nullable', 'array', 'max:'.self::MAX_ALLOWED_VALUES],
            'wanted_attributes.*.allowed_values.*' => ['required', 'string', 'max:191'],
            'wanted_attributes.*.multiple' => ['nullable', 'boolean'],
            'wanted_attributes.*.jsonld_property' => ['nullable', 'string', 'max:100'],
        ]);

        $wantedAttributes = $this->wantedAttributes($validated['wanted_attributes'] ?? []);

        $product = isset($validated['product_id'])
            ? Product::query()->findOrFail($validated['product_id'])
            : Product::query()->where('external_id', $validated['product_external_id'])->firstOrFail();

        $frequency = Frequency::tryFrom($validated['frequency'] ?? 'daily') ?? Frequency::Daily;

        $target = MonitoringTarget::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'country' => strtoupper($validated['country']),
            ],
            [
                'locale' => $validated['locale'] ?? null,
                'frequency_preset' => $frequency,
                'cron_custom' => $validated['cron_custom'] ?? null,
                'status' => 'active',
                'priority' => $validated['priority'] ?? 100,
                'options' => array_filter([
                    'given_urls' => $validated['given_urls'] ?? null,
                    'given_domains' => $validated['given_domains'] ?? null,
                    // Like given_urls: every POST sends the full list, omitting it removes the list.
                    'wanted_attributes' => $wantedAttributes,
                ]),
                'next_check_at' => now(),
            ],
        );

        return response()->json(['data' => $target], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $target = MonitoringTarget::query()->findOrFail($id);

        $validated = $request->validate([
            'status' => ['nullable', 'in:active,paused,stopped'],
            'frequency' => ['nullable', 'string'],
            'priority' => ['nullable', 'integer'],
        ]);

        if (isset($validated['status'])) {
            $target->status = $validated['status'];
        }

        if (isset($validated['frequency']) && ($f = Frequency::tryFrom($validated['frequency'])) !== null) {
            $target->frequency_preset = $f;
        }

        if (isset($validated['priority'])) {
            $target->priority = $validated['priority'];
        }

        $target->save();

        return response()->json(['data' => $target]);
    }

    /**
     * Checks the rules the validator cannot express (a total cap, case-insensitive uniqueness of
     * the values inside each attribute) and normalizes the list stored on the target.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>|null
     */
    private function wantedAttributes(array $items): ?array
    {
        $total = 0;
        $errors = [];

        foreach ($items as $index => $item) {
            $values = is_array($item['allowed_values'] ?? null) ? $item['allowed_values'] : [];
            $total += count($values);
            $folded = array_map(static fn (mixed $value): string => mb_strtolower(trim((string) $value)), $values);

            if (count(array_unique($folded)) !== count($folded)) {
                $errors["wanted_attributes.{$index}.allowed_values"] = 'The allowed values must be distinct (case-insensitive).';
            }
        }

        if ($total > self::MAX_ALLOWED_VALUES_TOTAL) {
            $errors['wanted_attributes'] = 'At most '.self::MAX_ALLOWED_VALUES_TOTAL.' allowed values in total.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $wanted = array_map(static fn (array $item): array => WantedAttribute::fromArray($item)->toArray(), array_values($items));

        return $wanted === [] ? null : $wanted;
    }

    /**
     * Force an immediate scrape of every confirmed competitor product on the target.
     * Work is queued (one job per competitor product); returns the number dispatched.
     */
    public function scrapeNow(int $id): JsonResponse
    {
        $target = MonitoringTarget::query()->findOrFail($id);

        $competitorIds = CompetitorProduct::query()
            ->where('monitoring_target_id', $target->id)
            ->where('match_status', MatchStatus::Confirmed)
            ->pluck('id');

        foreach ($competitorIds as $competitorId) {
            ScrapeCompetitorProductJob::dispatch((int) $competitorId, $target->tenant_id);
        }

        return response()->json(['data' => ['queued' => $competitorIds->count()]], 202);
    }
}
