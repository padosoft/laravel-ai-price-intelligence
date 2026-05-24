<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Padosoft\LaravelAiSearchProviders\LaravelAiSearchProvidersServiceProvider;
use Padosoft\PriceIntelligence\PriceIntelligenceServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelAiSearchProvidersServiceProvider::class,
            PriceIntelligenceServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }
}
