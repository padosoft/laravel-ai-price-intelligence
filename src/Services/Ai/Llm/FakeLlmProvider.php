<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai\Llm;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Data\LlmResult;

/**
 * Offline, deterministic LLM driver. Produces stable feature-shaped output so the
 * package works with zero configuration and CI never makes a live call.
 */
final class FakeLlmProvider implements LlmProviderInterface
{
    public function complete(string $instructions, string $prompt, array $options = []): LlmResult
    {
        $feature = (string) ($options['feature'] ?? 'general');

        return new LlmResult(
            text: "[fake:{$feature}] ".$this->digest($instructions.$prompt),
            model: 'fake',
        );
    }

    public function completeJson(string $instructions, string $prompt, array $options = []): LlmResult
    {
        $feature = (string) ($options['feature'] ?? 'general');
        $json = $this->payloadFor($feature, $instructions.$prompt);

        return new LlmResult(
            text: (string) json_encode($json),
            model: 'fake',
            json: $json,
        );
    }

    public function vision(string $instructions, string $prompt, array $imageUrls, array $options = []): LlmResult
    {
        $same = count($imageUrls) >= 2 && $imageUrls[0] === $imageUrls[1];
        $json = [
            'same_product' => $same,
            'confidence' => $same ? 95 : 20,
            'rationale' => $same ? 'identical image reference' : 'image references differ',
        ];

        return new LlmResult(text: (string) json_encode($json), model: 'fake', json: $json);
    }

    public function isFake(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(string $feature, string $seed): array
    {
        return match ($feature) {
            'narrative' => [
                'summary_md' => '## Weekly summary'.PHP_EOL.'No live model configured; deterministic placeholder summary.',
                'highlights' => [],
            ],
            'content_gap' => [
                'seo_score_delta' => 0,
                'missing_attributes' => [],
                'title_recommendations' => [],
                'description_recommendations' => [],
                'image_count_gap' => 0,
            ],
            'promo_detection' => [
                'has_promo' => false,
                'promo_type' => null,
                'valid_from' => null,
                'valid_to' => null,
                'conditions' => null,
                'effective_discount_pct' => null,
            ],
            'match_judge' => [
                'same_product' => false,
                'confidence' => 0,
                'rationale' => 'deterministic fake judge: no decision',
            ],
            default => ['text' => $this->digest($seed)],
        };
    }

    private function digest(string $seed): string
    {
        return substr(sha1($seed), 0, 12);
    }
}
