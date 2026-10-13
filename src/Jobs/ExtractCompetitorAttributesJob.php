<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Padosoft\PriceIntelligence\Data\ExtractedAttribute;
use Padosoft\PriceIntelligence\Data\WantedAttribute;
use Padosoft\PriceIntelligence\Enums\AttributeExtractionStatus;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\ContentSnapshot;
use Padosoft\PriceIntelligence\Services\Ai\AiDecisionLogger;
use Padosoft\PriceIntelligence\Services\Ai\AttributeExtractor;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Throwable;

/**
 * LLM step of the category-attribute extraction, off the scrape queue: asks the model for the
 * attributes schema.org did not provide and closes the snapshot as done (or failed once the
 * retries are exhausted). The page text travels in the payload, so it is never stored.
 */
final class ExtractCompetitorAttributesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly int $contentSnapshotId,
        public readonly int|string $tenantId,
        public readonly string $pageText,
    ) {
        $this->onQueue((string) config('price-intelligence.queues.ai', 'pi-ai'));
    }

    public function handle(AttributeExtractor $extractor, TenantContext $tenant, AiDecisionLogger $logger): void
    {
        $tenant->set($this->tenantId);

        $content = ContentSnapshot::query()->find($this->contentSnapshotId);

        if ($content === null || $content->attributes_status !== AttributeExtractionStatus::Pending) {
            return;
        }

        $competitor = CompetitorProduct::query()->find($content->competitor_product_id);
        $attributes = array_map(
            static fn (mixed $item): ExtractedAttribute => ExtractedAttribute::fromArray(is_array($item) ? $item : []),
            is_array($content->attributes) ? $content->attributes : [],
        );
        $missing = array_values(array_filter(
            WantedAttribute::listFromOptions($competitor?->target?->options),
            static fn (WantedAttribute $attribute): bool => ! ($attributes[$attribute->key] ?? ExtractedAttribute::notFound())->found(),
        ));

        if ($missing !== []) {
            $hints = [];

            foreach ($missing as $attribute) {
                $raw = ($attributes[$attribute->key] ?? null)?->raw;

                if ($raw !== null) {
                    $hints[$attribute->key] = $raw;
                }
            }

            $run = $extractor->fromPageText($missing, $this->pageText, $content->title, $hints);
            $attributes = array_replace($attributes, $run['attributes']);

            $logger->record(
                tenantId: $content->tenant_id,
                feature: AttributeExtractor::FEATURE,
                output: $run['output'],
                model: $run['model'],
                subjectType: 'CompetitorProduct',
                subjectId: $content->competitor_product_id,
            );
        }

        $content->forceFill([
            'attributes' => array_map(static fn (ExtractedAttribute $attribute): array => $attribute->toArray(), $attributes),
            'attributes_status' => AttributeExtractionStatus::Done,
            'attributes_extracted_at' => now(),
        ])->save();
    }

    /**
     * Retries exhausted: keep the schema.org values and let clients stop waiting. Runs outside
     * handle(), where the tenant context may be unset or stale, so the tenant is re-asserted.
     */
    public function failed(?Throwable $exception): void
    {
        ContentSnapshot::query()
            ->withoutTenantScope()
            ->where('tenant_id', $this->tenantId)
            ->whereKey($this->contentSnapshotId)
            ->where('attributes_status', AttributeExtractionStatus::Pending->value)
            ->update([
                'attributes_status' => AttributeExtractionStatus::Failed->value,
                'attributes_extracted_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
