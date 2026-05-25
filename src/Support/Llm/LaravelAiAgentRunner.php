<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Llm;

use function Laravel\Ai\agent;

use Laravel\Ai\Files;

/**
 * Real laravel/ai-backed runner. The provider string maps to a config/ai.php provider
 * key (e.g. 'openai', 'anthropic', 'regolo'); model is the provider's model id.
 */
final class LaravelAiAgentRunner implements AgentRunner
{
    public function run(
        string $instructions,
        string $prompt,
        string $provider,
        string $model,
        int $timeout,
        array $imageUrls = [],
    ): AgentRunResult {
        $attachments = array_map(
            static fn (string $url) => Files\Image::fromUrl($url),
            $imageUrls,
        );

        $response = agent(instructions: $instructions)
            ->prompt(
                $prompt,
                attachments: $attachments,
                provider: $provider,
                model: $model,
                timeout: $timeout,
            );

        return new AgentRunResult(
            text: (string) $response,
            model: $model,
            promptTokens: $response->usage->promptTokens,
            completionTokens: $response->usage->completionTokens,
        );
    }
}
