<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Data\LlmResult;
use Padosoft\PriceIntelligence\Enums\AttributeExtractionStatus;
use Padosoft\PriceIntelligence\Enums\Frequency;
use Padosoft\PriceIntelligence\Jobs\ExtractCompetitorAttributesJob;
use Padosoft\PriceIntelligence\Models\AiDecisionLog;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\ContentSnapshot;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Ai\AiDecisionLogger;
use Padosoft\PriceIntelligence\Services\Ai\AttributeExtractor;
use Padosoft\PriceIntelligence\Services\Scraping\ScrapeService;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class AttributeExtractionTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE = <<<'HTML'
    <html><head><script type="application/ld+json">{"@type":"Product","name":"Wool shorts","material":"Wool","offers":{"price":"390,00","priceCurrency":"EUR"}}</script></head>
    <body><h1>Wool gabardine shorts</h1><p>Made in Romania</p><p>Recommended for adults</p></body></html>
    HTML;

    private const WANTED = [
        ['key' => 'a1', 'label' => 'Materiale', 'allowed_values' => ['Lana', 'Wool'], 'multiple' => false, 'jsonld_property' => 'material'],
        ['key' => 'a2', 'label' => 'Fascia Età', 'allowed_values' => ['Adulto', 'Bambino'], 'multiple' => false, 'jsonld_property' => null],
    ];

    private int $llmCalls = 0;

    private Tenant $tenant;

    #[Test]
    public function a_target_without_wanted_attributes_is_skipped(): void
    {
        Queue::fake();
        $competitor = $this->competitor([]);

        $this->scrape($competitor);

        $content = ContentSnapshot::query()->sole();
        $this->assertSame(AttributeExtractionStatus::Skipped, $content->attributes_status);
        $this->assertSame([], $content->attributes);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function with_the_llm_step_off_schema_org_values_are_final_at_once(): void
    {
        Queue::fake();
        config()->set('price-intelligence.ai.attribute_extraction.enabled', false);
        $competitor = $this->competitor(self::WANTED);

        $this->scrape($competitor);

        $content = ContentSnapshot::query()->sole();
        $this->assertSame(AttributeExtractionStatus::Done, $content->attributes_status);
        $this->assertNotNull($content->attributes_extracted_at);
        $this->assertSame('Wool', $content->attributes['a1']['value']);
        $this->assertSame('jsonld', $content->attributes['a1']['source']);
        $this->assertNull($content->attributes['a2']['value']);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_fake_llm_driver_never_queues_the_llm_step(): void
    {
        Queue::fake();
        config()->set('price-intelligence.ai.attribute_extraction.enabled', true);
        $competitor = $this->competitor(self::WANTED);

        $this->scrape($competitor);

        $this->assertSame(AttributeExtractionStatus::Done, ContentSnapshot::query()->sole()->attributes_status);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_llm_step_runs_on_the_ai_queue_and_completes_the_snapshot(): void
    {
        Queue::fake();
        $this->enableLlm(['attributes' => [
            'a2' => ['value' => 'Adulto', 'raw' => 'Recommended for adults', 'evidence' => 'Recommended for adults', 'confidence' => 0.8],
        ]]);
        $competitor = $this->competitor(self::WANTED);

        $this->scrape($competitor);

        $content = ContentSnapshot::query()->sole();
        $this->assertSame(AttributeExtractionStatus::Pending, $content->attributes_status);
        $this->assertNull($content->attributes_extracted_at);

        /** @var ExtractCompetitorAttributesJob|null $job */
        $job = null;
        Queue::assertPushedOn('pi-ai', ExtractCompetitorAttributesJob::class, function (ExtractCompetitorAttributesJob $pushed) use (&$job): bool {
            $job = $pushed;

            return str_contains($pushed->pageText, 'Recommended for adults') && ! str_contains($pushed->pageText, 'application/ld+json');
        });

        $job->handle(app(AttributeExtractor::class), app(TenantContext::class), app(AiDecisionLogger::class));

        $content->refresh();
        $this->assertSame(AttributeExtractionStatus::Done, $content->attributes_status);
        $this->assertNotNull($content->attributes_extracted_at);
        $this->assertSame('Wool', $content->attributes['a1']['value']);
        $this->assertSame('Adulto', $content->attributes['a2']['value']);
        $this->assertSame('llm', $content->attributes['a2']['source']);
        $this->assertSame(1, $this->llmCalls);
        $this->assertSame('attribute_extraction', AiDecisionLog::query()->sole()->feature);
    }

    #[Test]
    public function an_unchanged_page_reuses_the_last_extraction_without_calling_the_llm(): void
    {
        Queue::fake();
        $this->enableLlm(['attributes' => [
            'a2' => ['value' => 'Adulto', 'raw' => 'adults', 'evidence' => 'Recommended for adults', 'confidence' => 0.8],
        ]]);
        $competitor = $this->competitor(self::WANTED);

        $this->scrape($competitor);
        Queue::pushedJobs()[ExtractCompetitorAttributesJob::class][0]['job']
            ->handle(app(AttributeExtractor::class), app(TenantContext::class), app(AiDecisionLogger::class));
        $this->scrape($competitor);

        $latest = ContentSnapshot::query()->orderByDesc('id')->first();
        $this->assertSame(AttributeExtractionStatus::Done, $latest?->attributes_status);
        $this->assertSame('Adulto', $latest->attributes['a2']['value']);
        $this->assertSame(1, $this->llmCalls);
        Queue::assertPushed(ExtractCompetitorAttributesJob::class, 1);
    }

    #[Test]
    public function a_failed_llm_step_keeps_schema_org_values_and_stops_the_wait(): void
    {
        Queue::fake();
        $this->enableLlm([]);
        $competitor = $this->competitor(self::WANTED);
        $this->scrape($competitor);
        // Another tenant left in the worker's context must not hide the row from failed().
        app(TenantContext::class)->set(Tenant::create(['code' => 't2', 'name' => 't2'])->id);

        Queue::pushedJobs()[ExtractCompetitorAttributesJob::class][0]['job']->failed(new RuntimeException('LLM down'));

        $content = ContentSnapshot::query()->withoutTenantScope()->sole();
        $this->assertSame(AttributeExtractionStatus::Failed, $content->attributes_status);
        $this->assertNotNull($content->attributes_extracted_at);
        $this->assertSame('Wool', $content->attributes['a1']['value']);
    }

    #[Test]
    public function the_api_exposes_the_status_next_to_captured_at(): void
    {
        Queue::fake();
        config()->set('price-intelligence.ai.attribute_extraction.enabled', false);
        $competitor = $this->competitor(self::WANTED);
        $this->scrape($competitor);
        [, $plaintext] = ApiKey::issue($this->tenant->id, 'test', ['*']);

        $this->withHeader('X-Api-Key', $plaintext)
            ->getJson("/api/v1/competitor-products/{$competitor->id}")
            ->assertOk()
            ->assertJsonPath('data.latest_content.attributes_status', 'done')
            ->assertJsonPath('data.latest_content.attributes.a1.value', 'Wool')
            ->assertJsonPath('data.latest_content.attributes.a2.value', null)
            ->assertJsonStructure(['data' => ['latest_content' => ['captured_at', 'attributes_extracted_at']]]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $wanted
     */
    private function competitor(array $wanted): CompetitorProduct
    {
        Http::fake(['shop.it/*' => Http::response(self::PAGE, 200)]);

        $this->tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($this->tenant->id);

        $product = Product::create(['external_id' => 'SKU-1', 'name' => 'Wool shorts']);
        $target = MonitoringTarget::create([
            'product_id' => $product->id, 'country' => 'IT',
            'frequency_preset' => Frequency::Daily, 'status' => 'active',
            'options' => $wanted === [] ? [] : ['wanted_attributes' => $wanted],
        ]);

        return CompetitorProduct::create([
            'tenant_id' => $this->tenant->id,
            'monitoring_target_id' => $target->id,
            'url' => 'https://shop.it/p/x1',
            'match_status' => 'confirmed',
        ]);
    }

    private function scrape(CompetitorProduct $competitor): void
    {
        app(TenantContext::class)->set($this->tenant->id);
        app(ScrapeService::class)->scrapeAndStore($competitor, ['locale' => 'en']);
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    private function enableLlm(array $answer): void
    {
        config()->set('price-intelligence.ai.attribute_extraction.enabled', true);
        $test = $this;

        $this->app->bind(LlmProviderInterface::class, static fn (): LlmProviderInterface => new class($answer, $test) implements LlmProviderInterface
        {
            /**
             * @param  array<string, mixed>  $answer
             */
            public function __construct(private readonly array $answer, private readonly AttributeExtractionTest $test) {}

            public function complete(string $instructions, string $prompt, array $options = []): LlmResult
            {
                return new LlmResult('', 'stub');
            }

            public function completeJson(string $instructions, string $prompt, array $options = []): LlmResult
            {
                $this->test->countLlmCall();

                return new LlmResult((string) json_encode($this->answer), 'stub-model', json: $this->answer);
            }

            public function vision(string $instructions, string $prompt, array $imageUrls, array $options = []): LlmResult
            {
                return new LlmResult('', 'stub');
            }

            public function isFake(): bool
            {
                return false;
            }
        });
    }

    public function countLlmCall(): void
    {
        $this->llmCalls++;
    }
}
