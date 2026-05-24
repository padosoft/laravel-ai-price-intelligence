<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Models\Alert;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Models\WebhookSubscription;
use Padosoft\PriceIntelligence\Services\Scraping\ScrapeService;
use Padosoft\PriceIntelligence\Services\Webhooks\WebhookSigner;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class AlertingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_price_drop_raises_an_alert_and_delivers_a_signed_webhook(): void
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $product = Product::create([
            'external_id' => 'SKU-1', 'name' => 'Phone',
            'our_price_cents' => 9000, 'currency' => 'EUR',
        ]);
        $target = MonitoringTarget::create([
            'product_id' => $product->id, 'country' => 'IT',
            'frequency_preset' => 'daily', 'status' => 'active',
        ]);
        $competitor = CompetitorProduct::create([
            'tenant_id' => $tenant->id, 'monitoring_target_id' => $target->id,
            'url' => 'https://shop.it/p/1', 'match_status' => 'confirmed',
        ]);

        WebhookSubscription::create([
            'tenant_id' => $tenant->id,
            'url' => 'https://hook.test/in',
            'events' => ['*'],
            'secret' => 'topsecret',
            'active' => true,
        ]);

        // First scrape: 100€. Second: 80€ (a drop, and below our 90€ -> undercut).
        // Closure fake guarantees the shop price advances while the webhook is also stubbed.
        $prices = ['100,00', '80,00'];
        $i = 0;
        Http::fake(function ($request) use (&$i, $prices) {
            if (str_contains($request->url(), 'hook.test')) {
                return Http::response('', 200);
            }
            $price = $prices[min($i, count($prices) - 1)];
            $i++;

            return Http::response(
                '<script type="application/ld+json">{"@type":"Product","name":"Phone","offers":{"price":"'.$price.'","priceCurrency":"EUR"}}</script>',
                200,
            );
        });

        $service = app(ScrapeService::class);
        $service->scrapeAndStore($competitor);   // baseline at 100€ (pricier than our 90€ -> no alert)
        Alert::query()->delete();                // clear baseline alerts to isolate the drop
        $service->scrapeAndStore($competitor->fresh());   // drop to 80€

        $types = Alert::query()->get()->map(fn ($a) => $a->type->value)->all();
        $this->assertContains('price.dropped', $types);
        $this->assertContains('undercut.detected', $types);

        // The webhook was called with a valid signature.
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'hook.test')) {
                return false;
            }
            $sig = $request->header(WebhookSigner::HEADER)[0] ?? '';

            return WebhookSigner::verify($request->body(), 'topsecret', $sig);
        });
    }
}
