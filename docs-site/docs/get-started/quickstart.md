---
title: Quickstart
---

# Quickstart

::: steps
=== Step 1: Install
```bash
composer require padosoft/laravel-ai-price-intelligence
php artisan vendor:publish --tag=price-intelligence-config
php artisan migrate
```

=== Step 2: Issue an API key
```php
use Padosoft\PriceIntelligence\Models\ApiKey;

[$key, $plaintext] = ApiKey::issue($tenantId, 'ecommerce-sync', ['*']);
```

=== Step 3: Sync catalog
```php
Http::withHeaders(['X-Api-Key' => $plaintext])->post("$base/api/v1/catalog/products:bulk", [
    'products' => [[
        'external_id' => 'SKU-123',
        'gtin' => '8001234567890',
        'brand' => 'Acme',
        'model' => 'X1',
        'name' => 'Acme X1 64GB',
        'our_price_cents' => 19900,
        'currency' => 'EUR',
        'base_country' => 'IT',
    ]],
]);
```

=== Step 4: Create a target
```php
Http::withHeaders(['X-Api-Key' => $plaintext])->post("$base/api/v1/targets", [
    'product_external_id' => 'SKU-123',
    'country' => 'IT',
    'frequency' => 'daily',
]);
```

=== Step 5: Run scheduler and workers
```bash
php artisan schedule:work
php artisan queue:work --queue=pi-discovery,pi-scrape,pi-ai,pi-notifications
```
:::

::: callout warning "Production"
Always configure webhook secrets, queue supervision, robots policy, and per-domain rate limits before monitoring production targets.
:::
