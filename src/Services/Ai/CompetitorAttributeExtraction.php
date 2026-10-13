<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Contracts\PiiFilterInterface;
use Padosoft\PriceIntelligence\Data\ExtractedAttribute;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Data\WantedAttribute;
use Padosoft\PriceIntelligence\Enums\AttributeExtractionStatus;
use Padosoft\PriceIntelligence\Jobs\ExtractCompetitorAttributesJob;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\ContentSnapshot;
use Padosoft\PriceIntelligence\Support\Config\Flag;

/**
 * Fills a fresh content snapshot with the category attributes its target asks for
 * (options.wanted_attributes): schema.org values right away, the rest through
 * ExtractCompetitorAttributesJob when the LLM step is enabled. attributes_status tells API
 * clients whether to wait.
 */
final class CompetitorAttributeExtraction
{
    public function __construct(
        private readonly AttributeExtractor $extractor,
        private readonly LlmProviderInterface $llm,
        private readonly PiiFilterInterface $pii,
    ) {}

    public function start(CompetitorProduct $competitor, ProductSnapshot $snapshot, ContentSnapshot $content): void
    {
        $wanted = WantedAttribute::listFromOptions($competitor->target?->options);

        if ($wanted === []) {
            $content->forceFill(['attributes_status' => AttributeExtractionStatus::Skipped])->save();

            return;
        }

        // The offline fake driver has nothing to say about a real page: treat it as "LLM off".
        $llmEnabled = Flag::enabled('price-intelligence.ai.attribute_extraction.enabled', false) && ! $this->llm->isFake();
        $signature = $this->signature($wanted, $llmEnabled);

        if ($this->reusePrevious($competitor, $content, $signature)) {
            return;
        }

        $results = $this->extractor->fromJsonLd($wanted, $snapshot->jsonld);
        $missing = array_filter($wanted, static fn (WantedAttribute $attribute): bool => ! $results[$attribute->key]->found());
        $pageText = $this->promptText($snapshot->pageText);
        $runLlm = $llmEnabled && $missing !== [] && $pageText !== null;

        $content->forceFill([
            'attributes' => array_map(static fn (ExtractedAttribute $attribute): array => $attribute->toArray(), $results),
            'attributes_signature' => $signature,
            'attributes_status' => $runLlm ? AttributeExtractionStatus::Pending : AttributeExtractionStatus::Done,
            'attributes_extracted_at' => $runLlm ? null : now(),
        ])->save();

        if ($runLlm) {
            ExtractCompetitorAttributesJob::dispatch((int) $content->id, $competitor->tenant_id, $pageText);
        }
    }

    /**
     * Daily scrapes of an unchanged page must not pay the LLM again: copy the last completed
     * extraction made for the same page (html hash) and the same request (signature).
     */
    private function reusePrevious(CompetitorProduct $competitor, ContentSnapshot $content, string $signature): bool
    {
        if ($content->html_hash === null) {
            return false;
        }

        $previous = ContentSnapshot::query()
            ->where('competitor_product_id', $competitor->id)
            ->whereKeyNot($content->id)
            ->where('html_hash', $content->html_hash)
            ->where('attributes_signature', $signature)
            ->where('attributes_status', AttributeExtractionStatus::Done->value)
            ->orderByDesc('id')
            ->first();

        if ($previous === null) {
            return false;
        }

        $content->forceFill([
            'attributes' => $previous->attributes,
            'attributes_signature' => $signature,
            'attributes_status' => AttributeExtractionStatus::Done,
            'attributes_extracted_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Identifies what was asked: the wanted list and whether the LLM step took part, so turning
     * the LLM on later does not reuse a schema.org-only extraction.
     *
     * @param  array<int, WantedAttribute>  $wanted
     */
    private function signature(array $wanted, bool $llmEnabled): string
    {
        return hash('sha256', (string) json_encode([
            'wanted' => array_map(static fn (WantedAttribute $attribute): array => $attribute->toArray(), $wanted),
            'llm' => $llmEnabled,
        ]));
    }

    /**
     * Page text sent to the LLM: personal data redacted, then cut to the prompt budget.
     */
    private function promptText(?string $pageText): ?string
    {
        if ($pageText === null || $pageText === '') {
            return null;
        }

        $text = Flag::enabled('price-intelligence.pii.enabled', true) ? $this->pii->redact($pageText) : $pageText;
        $max = max(1000, (int) config('price-intelligence.ai.attribute_extraction.max_text_chars', 12000));

        return mb_substr($text, 0, $max);
    }
}
