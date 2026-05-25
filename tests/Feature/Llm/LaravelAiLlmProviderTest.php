<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Llm;

use Padosoft\PriceIntelligence\Services\Ai\Llm\LaravelAiLlmProvider;
use Padosoft\PriceIntelligence\Support\Llm\AgentRunner;
use Padosoft\PriceIntelligence\Support\Llm\AgentRunResult;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class LaravelAiLlmProviderTest extends TestCase
{
    private function runnerReturning(string $text): AgentRunner
    {
        return new class($text) implements AgentRunner
        {
            /** @var array<string, mixed> */
            public array $lastCall = [];

            public function __construct(private readonly string $text) {}

            public function run(string $instructions, string $prompt, string $provider, string $model, int $timeout, array $imageUrls = []): AgentRunResult
            {
                $this->lastCall = compact('instructions', 'prompt', 'provider', 'model', 'timeout', 'imageUrls');

                return new AgentRunResult(text: $this->text, model: $model, promptTokens: 11, completionTokens: 7);
            }
        };
    }

    #[Test]
    public function complete_passes_config_defaults_and_maps_result(): void
    {
        config()->set('price-intelligence.ai.llm.provider', 'anthropic');
        config()->set('price-intelligence.ai.llm.model', 'claude-haiku-4-5');
        config()->set('price-intelligence.ai.llm.timeout', 90);

        $runner = $this->runnerReturning('hello from model');
        $provider = new LaravelAiLlmProvider($runner);

        $result = $provider->complete('be terse', 'say hi', ['feature' => 'narrative']);

        $this->assertFalse($provider->isFake());
        $this->assertSame('hello from model', $result->text);
        $this->assertSame('claude-haiku-4-5', $result->model);
        $this->assertSame(18, $result->totalTokens());
        $this->assertSame('anthropic', $runner->lastCall['provider']);
        $this->assertSame(90, $runner->lastCall['timeout']);
    }

    #[Test]
    public function per_call_options_override_config(): void
    {
        config()->set('price-intelligence.ai.llm.provider', 'openai');
        config()->set('price-intelligence.ai.llm.model', 'gpt-4o-mini');

        $runner = $this->runnerReturning('ok');
        $provider = new LaravelAiLlmProvider($runner);

        $provider->complete('s', 'p', ['provider' => 'regolo', 'model' => 'maestrale']);

        $this->assertSame('regolo', $runner->lastCall['provider']);
        $this->assertSame('maestrale', $runner->lastCall['model']);
    }

    #[Test]
    public function completeJson_decodes_strict_json(): void
    {
        $runner = $this->runnerReturning('{"has_promo": true, "effective_discount_pct": 15}');
        $provider = new LaravelAiLlmProvider($runner);

        $result = $provider->completeJson('s', 'p', ['feature' => 'promo_detection']);

        $this->assertSame(['has_promo' => true, 'effective_discount_pct' => 15], $result->json);
    }

    #[Test]
    public function completeJson_strips_markdown_fence_before_decoding(): void
    {
        $runner = $this->runnerReturning("```json\n{\"confidence\": 72}\n```");
        $provider = new LaravelAiLlmProvider($runner);

        $result = $provider->completeJson('s', 'p', ['feature' => 'match_judge']);

        $this->assertSame(['confidence' => 72], $result->json);
    }

    #[Test]
    public function completeJson_throws_on_undecodable_output(): void
    {
        $runner = $this->runnerReturning('I cannot help with that.');
        $provider = new LaravelAiLlmProvider($runner);

        $this->expectException(\RuntimeException::class);
        $provider->completeJson('s', 'p', ['feature' => 'promo_detection']);
    }

    #[Test]
    public function vision_forwards_image_urls(): void
    {
        $runner = $this->runnerReturning('{"same_product": true, "confidence": 88}');
        $provider = new LaravelAiLlmProvider($runner);

        $result = $provider->vision('judge', 'compare', ['https://x/a.jpg', 'https://x/b.jpg'], ['feature' => 'visual_match']);

        $this->assertSame(['same_product' => true, 'confidence' => 88], $result->json);
    }
}
