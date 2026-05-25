<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Live;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Contracts\NarrativeWriterInterface;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Ai\Llm\LaravelAiLlmProvider;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Opt-in live LLM smoke suite. Lives in tests/Live (no phpunit testsuite references it) so the
 * default `phpunit` run never executes it; run it explicitly with real keys:
 *
 *   PI_LIVE_LLM=1 PI_LLM_PROVIDER=regolo PI_LLM_MODEL=... vendor/bin/phpunit tests/Live
 *
 * The host must also configure config/ai.php provider keys for the chosen provider.
 */
#[Group('live')]
final class LiveLlmSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (env('PI_LIVE_LLM') !== '1') {
            $this->markTestSkipped('Set PI_LIVE_LLM=1 and configure config/ai.php provider keys to run live LLM smoke tests.');
        }

        config()->set('price-intelligence.ai.llm.driver', 'laravel-ai');
        config()->set('price-intelligence.ai.llm.provider', env('PI_LLM_PROVIDER', 'openai'));
        config()->set('price-intelligence.ai.llm.model', env('PI_LLM_MODEL', 'gpt-4o-mini'));
    }

    #[Test]
    public function real_provider_is_bound_and_completes(): void
    {
        $provider = app(LlmProviderInterface::class);
        $this->assertInstanceOf(LaravelAiLlmProvider::class, $provider);

        $result = $provider->complete('Be terse.', 'Reply with the single word: ok', ['feature' => 'general']);
        $this->assertNotSame('', $result->text);
        $this->assertNotSame('fake', $result->model);
    }

    #[Test]
    public function real_narrative_round_trips(): void
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $result = app(NarrativeWriterInterface::class)->write($tenant->id, '2026-W21', ['top_movers' => []]);
        $this->assertNotSame('', $result->summaryMd);
    }
}
