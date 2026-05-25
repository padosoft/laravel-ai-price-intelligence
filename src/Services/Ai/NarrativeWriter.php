<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Contracts\NarrativeWriterInterface;
use Padosoft\PriceIntelligence\Data\NarrativeResult;

final class NarrativeWriter implements NarrativeWriterInterface
{
    public function __construct(
        private readonly LlmProviderInterface $llm,
        private readonly AiDecisionLogger $logger,
    ) {}

    public function write(int|string $tenantId, string $period, array $context): NarrativeResult
    {
        $prompt = "Period: {$period}\nSignals (JSON):\n".(string) json_encode($context);

        $result = $this->llm->completeJson(
            'You are a retail price-intelligence analyst. Summarise the week for a merchandiser. '
            .'Return JSON: {"summary_md": string (markdown), "highlights": array of short strings}.',
            $prompt,
            ['feature' => 'narrative'],
        );

        $json = $result->json ?? [];
        $summaryMd = is_string($json['summary_md'] ?? null) ? $json['summary_md'] : '';
        /** @var array<int, mixed> $highlights */
        $highlights = is_array($json['highlights'] ?? null) ? $json['highlights'] : [];

        $this->logger->record(
            tenantId: $tenantId,
            feature: 'narrative',
            output: ['period' => $period, 'summary_md' => $summaryMd, 'highlights' => $highlights],
            model: $result->model,
        );

        return new NarrativeResult($summaryMd, $highlights, $result->model);
    }
}
