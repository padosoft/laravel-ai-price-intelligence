# Extending

Everything is pluggable via Interface + Driver. Rebind any contract in a service provider.

## Contracts

| Interface | Default | Purpose |
|---|---|---|
| `ProductScraperInterface` | `GenericHttpScraper` | fetch + extract a `ProductSnapshot` from a URL |
| `MarketplaceAdapterInterface` | per-`AdapterCode` | marketplace-specific fetch (Amazon/eBay/…) |
| `MatchStepInterface` (cascade steps) | ExactGtin / MpnBrand / NormalizedName / EmbeddingSemantic matchers | matching cascade steps |
| `EmbeddingProviderInterface` | `FakeEmbeddingProvider` | text embeddings for semantic matching |
| `FxProviderInterface` | `FixedFxProvider` | currency conversion |
| `ForecastProviderInterface` | `StatisticalForecaster` | price forecasting |
| `AnomalyDetectorInterface` | `StatisticalAnomalyDetector` | anomaly detection |
| `ReviewSentimentInterface` | `LexiconSentimentAnalyzer` | review sentiment |
| `PiiFilterInterface` | `PiiFilter` | PII redaction |
| `RepricerEngineInterface` | `RepricerEngine` | repricing suggestions |
| `AiActBridgeInterface` | `NullAiActBridge` | EU AI Act governance bridge |

## Example: a custom forecaster

```php
use Padosoft\PriceIntelligence\Contracts\ForecastProviderInterface;

$this->app->bind(ForecastProviderInterface::class, fn () => new MyChronosHttpForecaster(
    config('services.forecast.url')
));
```

## Example: a custom marketplace adapter

```php
// config/price-intelligence.php
'adapters' => [
    'amazon' => \App\PriceIntel\AmazonSpApiAdapter::class, // implements MarketplaceAdapterInterface
],
```

## Example: a custom repricer strategy

Register a callable in the container (config-cache safe):

```php
$this->app->bind('price-intelligence.repricer.custom.beat_buybox', fn () =>
    function ($product, array $prices, ?int $current, array $params): int {
        // $prices is already cleaned (positive, sorted ascending); undercut the cheapest by 1 cent.
        return max(1, ($prices[0] ?? $current ?? 0) - 1);
    }
);
```

Then a `RepricingRule` with `strategy = custom`, `parameters = ['callable' => 'beat_buybox']`.
Custom outputs are still passed through the margin floor / max-change / charm guards.

## Disabling features

Set the relevant `*.enabled` config flag to `false`. Disabled AI features bind null-object drivers,
so callers receive empty/null results without conditional checks.
