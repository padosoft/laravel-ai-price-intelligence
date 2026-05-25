<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Llm;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Services\Ai\Llm\FakeLlmProvider;
use Padosoft\PriceIntelligence\Services\Ai\Llm\LaravelAiLlmProvider;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class LlmBindingTest extends TestCase
{
    #[Test]
    public function fake_llm_is_bound_by_default(): void
    {
        $this->assertInstanceOf(FakeLlmProvider::class, app(LlmProviderInterface::class));
    }

    #[Test]
    public function laravel_ai_llm_is_bound_when_configured(): void
    {
        config()->set('price-intelligence.ai.llm.driver', 'laravel-ai');

        $this->assertInstanceOf(LaravelAiLlmProvider::class, app(LlmProviderInterface::class));
    }
}
