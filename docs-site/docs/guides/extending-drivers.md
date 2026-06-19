---
title: Extending Drivers
---

# Extending Drivers

Most behavior is behind interfaces and can be rebound in a Laravel service provider.

```php
use Padosoft\PriceIntelligence\Contracts\ForecastProviderInterface;

$this->app->bind(ForecastProviderInterface::class, fn () => new MyChronosHttpForecaster(
    config('services.forecast.url')
));
```

::: tabs
== tab "Scraping"
Implement `ProductScraperInterface` or `MarketplaceAdapterInterface`.

== tab "AI"
Implement `LlmProviderInterface`, `EmbeddingProviderInterface`, `ForecastProviderInterface`, or `AnomalyDetectorInterface`.

== tab "Compliance"
Implement `PiiFilterInterface` or `AiActBridgeInterface`.
:::
