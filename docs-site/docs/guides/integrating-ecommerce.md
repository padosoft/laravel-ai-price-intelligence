---
title: Integrating an Ecommerce
---

# Integrating an Ecommerce

The host application sends catalog data and consumes signals. Final price application stays outside this package.

```php
Route::post('/webhooks/price-intel', function (Request $request) {
    abort_unless(WebhookSigner::verify(
        $request->getContent(),
        config('services.pi.secret'),
        $request->header(WebhookSigner::HEADER, '')
    ), 401);

    match ($request->input('event')) {
        'price.dropped', 'undercut.detected' => MarginOS::reevaluate($request->input('data')),
        default => null,
    };
});
```

::: callout warning "Boundary"
The package emits observations, alerts, and advisory decisions. The host owns margins, inventory, catalog policy, and final price writes.
:::

## Common Flow

1. Issue an API key.
2. Bulk upsert products by `external_id`.
3. Create monitoring targets by country.
4. Let discovery find URLs or pass `given_urls`.
5. Review borderline matches.
6. Process signed webhook deliveries.
