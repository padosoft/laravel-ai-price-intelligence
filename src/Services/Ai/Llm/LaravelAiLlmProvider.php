<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai\Llm;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Data\LlmResult;
use Padosoft\PriceIntelligence\Support\Llm\AgentRunner;
use Padosoft\PriceIntelligence\Support\Llm\AgentRunResult;
use RuntimeException;

final class LaravelAiLlmProvider implements LlmProviderInterface
{
    public function __construct(private readonly AgentRunner $runner) {}

    public function complete(string $instructions, string $prompt, array $options = []): LlmResult
    {
        return $this->toResult($this->dispatch($instructions, $prompt, $options));
    }

    public function completeJson(string $instructions, string $prompt, array $options = []): LlmResult
    {
        $jsonInstructions = trim($instructions."\n\nRespond ONLY with a single valid JSON object. No prose, no markdown.");
        $run = $this->dispatch($jsonInstructions, $prompt, $options);

        return new LlmResult(
            text: $run->text,
            model: $run->model,
            promptTokens: $run->promptTokens,
            completionTokens: $run->completionTokens,
            json: $this->decode($run->text),
        );
    }

    public function vision(string $instructions, string $prompt, array $imageUrls, array $options = []): LlmResult
    {
        $run = $this->runner->run(
            $instructions,
            $prompt,
            $this->provider($options),
            $this->model($options),
            $this->timeout($options),
            $imageUrls,
        );

        return new LlmResult(
            text: $run->text,
            model: $run->model,
            promptTokens: $run->promptTokens,
            completionTokens: $run->completionTokens,
            json: $this->decode($run->text),
        );
    }

    public function isFake(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function dispatch(string $instructions, string $prompt, array $options): AgentRunResult
    {
        return $this->runner->run(
            $instructions,
            $prompt,
            $this->provider($options),
            $this->model($options),
            $this->timeout($options),
        );
    }

    private function toResult(AgentRunResult $run): LlmResult
    {
        return new LlmResult(
            text: $run->text,
            model: $run->model,
            promptTokens: $run->promptTokens,
            completionTokens: $run->completionTokens,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function provider(array $options): string
    {
        return (string) ($options['provider'] ?? config('price-intelligence.ai.llm.provider', 'openai'));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function model(array $options): string
    {
        return (string) ($options['model'] ?? config('price-intelligence.ai.llm.model', 'gpt-4o-mini'));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function timeout(array $options): int
    {
        return (int) ($options['timeout'] ?? config('price-intelligence.ai.llm.timeout', 120));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $text): array
    {
        $clean = trim($text);

        // Strip a ```json ... ``` (or plain ```) fence if the model wrapped its output.
        if (str_starts_with($clean, '```')) {
            $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $clean) ?? $clean;
            $clean = trim($clean);
        }

        $decoded = json_decode($clean, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('LLM did not return decodable JSON: '.substr($text, 0, 200));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
