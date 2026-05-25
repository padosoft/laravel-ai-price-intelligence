<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\ContentGapAnalyzerInterface;
use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Data\ContentGapResult;
use Padosoft\PriceIntelligence\Models\Product;

final class ContentGapAnalyzer implements ContentGapAnalyzerInterface
{
    public function __construct(
        private readonly LlmProviderInterface $llm,
        private readonly AiDecisionLogger $logger,
    ) {}

    public function analyze(Product $product, array $competitorSnapshots): ContentGapResult
    {
        $payload = [
            'our_product' => [
                'name' => $product->name,
                'brand' => $product->brand,
                'attributes' => $product->attributes,
            ],
            'competitors' => $competitorSnapshots,
        ];

        $result = $this->llm->completeJson(
            'You are an ecommerce SEO/merchandising analyst. Compare our product to competitors. '
            .'Return JSON: {"seo_score_delta": int, "missing_attributes": string[], '
            .'"title_recommendations": string[], "description_recommendations": string[], "image_count_gap": int}.',
            (string) json_encode($payload),
            ['feature' => 'content_gap'],
        );

        $json = $result->json ?? [];
        $gap = new ContentGapResult(
            seoScoreDelta: (int) ($json['seo_score_delta'] ?? 0),
            missingAttributes: $this->strings($json['missing_attributes'] ?? []),
            titleRecommendations: $this->strings($json['title_recommendations'] ?? []),
            descriptionRecommendations: $this->strings($json['description_recommendations'] ?? []),
            imageCountGap: (int) ($json['image_count_gap'] ?? 0),
            model: $result->model,
        );

        $this->logger->record(
            tenantId: $product->tenant_id,
            feature: 'content_gap',
            output: $json,
            model: $result->model,
            subjectType: 'Product',
            subjectId: (int) $product->id,
        );

        return $gap;
    }

    /**
     * @return array<int, string>
     */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn ($v): string => (string) $v, $value));
    }
}
