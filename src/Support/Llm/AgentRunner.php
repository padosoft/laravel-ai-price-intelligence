<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Llm;

/**
 * Isolates the laravel/ai call so LaravelAiLlmProvider can be unit-tested without
 * a live SDK call. The real implementation is LaravelAiAgentRunner.
 */
interface AgentRunner
{
    /**
     * @param  array<int, string>  $imageUrls
     */
    public function run(
        string $instructions,
        string $prompt,
        string $provider,
        string $model,
        int $timeout,
        array $imageUrls = [],
    ): AgentRunResult;
}
