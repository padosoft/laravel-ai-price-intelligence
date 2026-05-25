<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Padosoft\PriceIntelligence\Console\Commands\ImportCatalogCommand;
use Padosoft\PriceIntelligence\Console\Commands\MaterializeDailyAggregatesCommand;
use Padosoft\PriceIntelligence\Console\Commands\PruneAuditLogsCommand;
use Padosoft\PriceIntelligence\Console\Commands\RunDueTargetsCommand;
use Padosoft\PriceIntelligence\Contracts\AiActBridgeInterface;
use Padosoft\PriceIntelligence\Contracts\AnomalyDetectorInterface;
use Padosoft\PriceIntelligence\Contracts\ContentGapAnalyzerInterface;
use Padosoft\PriceIntelligence\Contracts\EmbeddingProviderInterface;
use Padosoft\PriceIntelligence\Contracts\ForecastProviderInterface;
use Padosoft\PriceIntelligence\Contracts\FxProviderInterface;
use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Contracts\NarrativeWriterInterface;
use Padosoft\PriceIntelligence\Contracts\PiiFilterInterface;
use Padosoft\PriceIntelligence\Contracts\ProductScraperInterface;
use Padosoft\PriceIntelligence\Contracts\PromoDetectorInterface;
use Padosoft\PriceIntelligence\Contracts\RepricerEngineInterface;
use Padosoft\PriceIntelligence\Contracts\ReviewSentimentInterface;
use Padosoft\PriceIntelligence\Contracts\VisualMatcherInterface;
use Padosoft\PriceIntelligence\Services\Ai\AiDecisionLogger;
use Padosoft\PriceIntelligence\Services\Ai\ContentGapAnalyzer;
use Padosoft\PriceIntelligence\Services\Ai\Llm\FakeLlmProvider;
use Padosoft\PriceIntelligence\Services\Ai\Llm\LaravelAiLlmProvider;
use Padosoft\PriceIntelligence\Services\Ai\NarrativeWriter;
use Padosoft\PriceIntelligence\Services\Ai\NullAnomalyDetector;
use Padosoft\PriceIntelligence\Services\Ai\NullContentGapAnalyzer;
use Padosoft\PriceIntelligence\Services\Ai\NullForecaster;
use Padosoft\PriceIntelligence\Services\Ai\NullNarrativeWriter;
use Padosoft\PriceIntelligence\Services\Ai\NullPromoDetector;
use Padosoft\PriceIntelligence\Services\Ai\NullVisualMatcher;
use Padosoft\PriceIntelligence\Services\Ai\PromoDetector;
use Padosoft\PriceIntelligence\Services\Ai\ReviewInsight\LexiconSentimentAnalyzer;
use Padosoft\PriceIntelligence\Services\Ai\StatisticalAnomalyDetector;
use Padosoft\PriceIntelligence\Services\Ai\StatisticalForecaster;
use Padosoft\PriceIntelligence\Services\Ai\VisualMatcher;
use Padosoft\PriceIntelligence\Services\Compliance\DomainRateLimiter;
use Padosoft\PriceIntelligence\Services\Compliance\NullAiActBridge;
use Padosoft\PriceIntelligence\Services\Compliance\PiiFilter;
use Padosoft\PriceIntelligence\Services\Matching\Embeddings\FakeEmbeddingProvider;
use Padosoft\PriceIntelligence\Services\Matching\Embeddings\LaravelAiEmbeddingProvider;
use Padosoft\PriceIntelligence\Services\Pricing\FixedFxProvider;
use Padosoft\PriceIntelligence\Services\Pricing\Repricer\RepricerEngine;
use Padosoft\PriceIntelligence\Services\Pricing\Repricer\StrategyCalculator;
use Padosoft\PriceIntelligence\Services\Scheduling\AdaptiveBackoff;
use Padosoft\PriceIntelligence\Services\Scraping\Drivers\GenericHttpScraper;
use Padosoft\PriceIntelligence\Services\Scraping\HtmlProductExtractor;
use Padosoft\PriceIntelligence\Support\Config\Flag;
use Padosoft\PriceIntelligence\Support\Llm\AgentRunner;
use Padosoft\PriceIntelligence\Support\Llm\EmbeddingRunner;
use Padosoft\PriceIntelligence\Support\Llm\LaravelAiAgentRunner;
use Padosoft\PriceIntelligence\Support\Llm\LaravelAiEmbeddingRunner;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;

final class PriceIntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/price-intelligence.php', 'price-intelligence');

        $this->app->singleton(TenantContext::class, static fn (): TenantContext => new TenantContext);

        $this->app->bind(AgentRunner::class, static fn (): AgentRunner => new LaravelAiAgentRunner);
        $this->app->bind(EmbeddingRunner::class, static fn (): EmbeddingRunner => new LaravelAiEmbeddingRunner);

        $this->app->bind(LlmProviderInterface::class, static function ($app): LlmProviderInterface {
            return config('price-intelligence.ai.llm.driver', 'fake') === 'laravel-ai'
                ? new LaravelAiLlmProvider($app->make(AgentRunner::class))
                : new FakeLlmProvider;
        });

        $this->app->bind(NarrativeWriterInterface::class, static fn ($app): NarrativeWriterInterface => Flag::enabled('price-intelligence.ai.narrative.enabled', true)
            ? new NarrativeWriter(
                $app->make(LlmProviderInterface::class),
                $app->make(AiDecisionLogger::class),
            )
            : new NullNarrativeWriter);

        $this->app->bind(ContentGapAnalyzerInterface::class, static fn ($app): ContentGapAnalyzerInterface => Flag::enabled('price-intelligence.ai.content_gap.enabled', true)
            ? new ContentGapAnalyzer(
                $app->make(LlmProviderInterface::class),
                $app->make(AiDecisionLogger::class),
            )
            : new NullContentGapAnalyzer);

        $this->app->bind(PromoDetectorInterface::class, static fn ($app): PromoDetectorInterface => Flag::enabled('price-intelligence.ai.promo_detection.enabled', true)
            ? new PromoDetector(
                $app->make(LlmProviderInterface::class),
                $app->make(AiDecisionLogger::class),
            )
            : new NullPromoDetector);

        $this->app->bind(VisualMatcherInterface::class, static fn ($app): VisualMatcherInterface => Flag::enabled('price-intelligence.ai.visual_match.enabled', true)
            ? new VisualMatcher(
                $app->make(LlmProviderInterface::class),
                $app->make(AiDecisionLogger::class),
            )
            : new NullVisualMatcher);

        $this->app->bind(EmbeddingProviderInterface::class, static function ($app): EmbeddingProviderInterface {
            // Default offline-safe driver; switch to laravel/ai via config or rebind in the host.
            if (config('price-intelligence.matching.embeddings.driver', 'fake') === 'laravel-ai') {
                return new LaravelAiEmbeddingProvider(
                    $app->make(EmbeddingRunner::class),
                    (string) config('price-intelligence.matching.embeddings.provider', 'openai'),
                    (string) config('price-intelligence.matching.embeddings.model', 'text-embedding-3-small'),
                    (int) config('price-intelligence.matching.embeddings.dimensions', 1536),
                );
            }

            return new FakeEmbeddingProvider;
        });

        $this->app->bind(FxProviderInterface::class, static fn (): FxProviderInterface => new FixedFxProvider);

        $this->app->bind(AdaptiveBackoff::class, static fn (): AdaptiveBackoff => new AdaptiveBackoff(
            (bool) config('price-intelligence.resilience.adaptive_backoff.enabled', true),
            (float) config('price-intelligence.resilience.adaptive_backoff.max_factor', 4),
        ));

        $this->app->bind(ProductScraperInterface::class, static fn ($app): ProductScraperInterface => new GenericHttpScraper(
            $app->make(HtmlProductExtractor::class),
        ));

        // Honor the feature toggles: bind a no-op driver when disabled so the
        // advertised config flags actually take effect.
        $this->app->bind(ForecastProviderInterface::class, static fn (): ForecastProviderInterface => Flag::enabled('price-intelligence.ai.forecast.enabled', true)
            ? new StatisticalForecaster((int) config('price-intelligence.ai.forecast.min_observations', 14))
            : new NullForecaster);

        $this->app->bind(AnomalyDetectorInterface::class, static fn (): AnomalyDetectorInterface => Flag::enabled('price-intelligence.ai.anomaly.enabled', true)
            ? new StatisticalAnomalyDetector
            : new NullAnomalyDetector);

        $this->app->bind(PiiFilterInterface::class, static fn (): PiiFilterInterface => new PiiFilter);

        $this->app->bind(RepricerEngineInterface::class, static fn ($app): RepricerEngineInterface => new RepricerEngine(
            $app->make(StrategyCalculator::class),
        ));

        $this->app->bind(ReviewSentimentInterface::class, static fn (): ReviewSentimentInterface => new LexiconSentimentAnalyzer);

        $this->app->singleton(DomainRateLimiter::class, static fn ($app): DomainRateLimiter => new DomainRateLimiter(
            $app->make('cache.store'),
        ));

        // EU AI Act bridge: null-object unless a real bridge is bound (e.g. by
        // padosoft/laravel-ai-act-compliance or the host). Never hard-fails.
        $this->app->bindIf(AiActBridgeInterface::class, static fn (): AiActBridgeInterface => new NullAiActBridge);

        $this->app->singleton(PriceIntelligenceManager::class, static fn ($app): PriceIntelligenceManager => new PriceIntelligenceManager(
            $app->make(TenantContext::class),
        ));
    }

    public function boot(): void
    {
        if ((bool) $this->app['config']->get('price-intelligence.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->publishes([
            __DIR__.'/../config/price-intelligence.php' => $this->configPath('price-intelligence.php'),
        ], 'price-intelligence-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => $this->databasePath('migrations'),
        ], 'price-intelligence-migrations');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportCatalogCommand::class,
                RunDueTargetsCommand::class,
                PruneAuditLogsCommand::class,
                MaterializeDailyAggregatesCommand::class,
            ]);

            $this->app->booted(function (): void {
                /** @var Schedule $schedule */
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('piprice:run-due')->everyMinute()->withoutOverlapping();

                if (Flag::enabled('price-intelligence.storage.aggregates.enabled', true)) {
                    $schedule->command('piprice:aggregates:daily')->dailyAt('02:30')->withoutOverlapping();
                }
            });
        }
    }

    private function registerRoutes(): void
    {
        if (! (bool) $this->app['config']->get('price-intelligence.api.register_routes', true)) {
            return;
        }

        $routes = __DIR__.'/../routes/api.php';

        if (! is_file($routes)) {
            return;
        }

        $this->app['router']
            ->prefix((string) $this->app['config']->get('price-intelligence.api.prefix', 'api/v1'))
            ->middleware((array) $this->app['config']->get('price-intelligence.api.middleware', ['api']))
            ->group($routes);
    }

    private function configPath(string $file): string
    {
        return function_exists('config_path') ? config_path($file) : base_path('config/'.$file);
    }

    private function databasePath(string $folder): string
    {
        return function_exists('database_path') ? database_path($folder) : base_path('database/'.$folder);
    }
}
