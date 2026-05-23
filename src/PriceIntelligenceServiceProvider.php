<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Padosoft\PriceIntelligence\Console\Commands\ImportCatalogCommand;
use Padosoft\PriceIntelligence\Console\Commands\RunDueTargetsCommand;
use Padosoft\PriceIntelligence\Contracts\EmbeddingProviderInterface;
use Padosoft\PriceIntelligence\Contracts\FxProviderInterface;
use Padosoft\PriceIntelligence\Contracts\ProductScraperInterface;
use Padosoft\PriceIntelligence\Services\Matching\Embeddings\FakeEmbeddingProvider;
use Padosoft\PriceIntelligence\Services\Pricing\FixedFxProvider;
use Padosoft\PriceIntelligence\Services\Scheduling\AdaptiveBackoff;
use Padosoft\PriceIntelligence\Services\Scraping\Drivers\GenericHttpScraper;
use Padosoft\PriceIntelligence\Services\Scraping\HtmlProductExtractor;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;

final class PriceIntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/price-intelligence.php', 'price-intelligence');

        $this->app->singleton(TenantContext::class, static fn (): TenantContext => new TenantContext());

        $this->app->bind(EmbeddingProviderInterface::class, static function (): EmbeddingProviderInterface {
            // Default offline-safe driver; host apps rebind to OpenAI/Voyage/etc.
            return new FakeEmbeddingProvider();
        });

        $this->app->bind(FxProviderInterface::class, static fn (): FxProviderInterface => new FixedFxProvider(
            (string) config('price-intelligence.fx.base', 'EUR'),
        ));

        $this->app->bind(AdaptiveBackoff::class, static fn (): AdaptiveBackoff => new AdaptiveBackoff(
            (bool) config('price-intelligence.resilience.adaptive_backoff.enabled', true),
            (float) config('price-intelligence.resilience.adaptive_backoff.max_factor', 4),
        ));

        $this->app->bind(ProductScraperInterface::class, static fn ($app): ProductScraperInterface => new GenericHttpScraper(
            $app->make(HtmlProductExtractor::class),
        ));

        $this->app->singleton(PriceIntelligenceManager::class, static fn ($app): PriceIntelligenceManager => new PriceIntelligenceManager(
            $app->make(TenantContext::class),
        ));
    }

    public function boot(): void
    {
        if ((bool) $this->app['config']->get('price-intelligence.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        $this->publishes([
            __DIR__ . '/../config/price-intelligence.php' => $this->configPath('price-intelligence.php'),
        ], 'price-intelligence-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => $this->databasePath('migrations'),
        ], 'price-intelligence-migrations');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportCatalogCommand::class,
                RunDueTargetsCommand::class,
            ]);

            $this->app->booted(function (): void {
                /** @var Schedule $schedule */
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('piprice:run-due')->everyMinute()->withoutOverlapping();
            });
        }
    }

    private function registerRoutes(): void
    {
        if (! (bool) $this->app['config']->get('price-intelligence.api.register_routes', true)) {
            return;
        }

        $routes = __DIR__ . '/../routes/api.php';

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
        return function_exists('config_path') ? config_path($file) : base_path('config/' . $file);
    }

    private function databasePath(string $folder): string
    {
        return function_exists('database_path') ? database_path($folder) : base_path('database/' . $folder);
    }
}
