<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Embeddings;

use Padosoft\PriceIntelligence\Contracts\EmbeddingProviderInterface;
use Padosoft\PriceIntelligence\Services\Matching\Embeddings\FakeEmbeddingProvider;
use Padosoft\PriceIntelligence\Services\Matching\Embeddings\LaravelAiEmbeddingProvider;
use Padosoft\PriceIntelligence\Support\Llm\EmbeddingRunner;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class EmbeddingDriverTest extends TestCase
{
    #[Test]
    public function fake_driver_is_bound_by_default(): void
    {
        $this->assertInstanceOf(FakeEmbeddingProvider::class, app(EmbeddingProviderInterface::class));
    }

    #[Test]
    public function laravel_ai_driver_is_bound_when_configured(): void
    {
        config()->set('price-intelligence.matching.embeddings.driver', 'laravel-ai');

        $this->assertInstanceOf(LaravelAiEmbeddingProvider::class, app(EmbeddingProviderInterface::class));
    }

    #[Test]
    public function laravel_ai_provider_forwards_config_to_runner(): void
    {
        $runner = new class implements EmbeddingRunner
        {
            /** @var array<string, mixed> */
            public array $call = [];

            public function embed(string $text, string $provider, string $model, int $dimensions): array
            {
                $this->call = compact('text', 'provider', 'model', 'dimensions');

                return array_fill(0, $dimensions, 0.5);
            }
        };

        $provider = new LaravelAiEmbeddingProvider($runner, 'regolo', 'bge-m3', 8);
        $vector = $provider->embed('blue shirt');

        $this->assertCount(8, $vector);
        $this->assertSame('regolo', $runner->call['provider']);
        $this->assertSame('bge-m3', $runner->call['model']);
        $this->assertSame(8, $runner->call['dimensions']);
    }
}
