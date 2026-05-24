<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Contracts\PiiFilterInterface;
use Padosoft\PriceIntelligence\Enums\AdapterCode;
use Padosoft\PriceIntelligence\Exceptions\ReviewInsightDisabledException;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\CompetitorSource;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\ReviewInsight;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Ai\ReviewInsight\ReviewAggregator;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ReviewInsightTest extends TestCase
{
    use RefreshDatabase;

    private function competitor(string $host = 'shop.it'): CompetitorProduct
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);
        $source = CompetitorSource::create(['host' => $host, 'adapter_code' => AdapterCode::Generic->value, 'robots_policy' => 'respect']);
        $product = Product::create(['external_id' => 'SKU-1', 'name' => 'Phone']);
        $target = MonitoringTarget::create(['product_id' => $product->id, 'country' => 'IT', 'frequency_preset' => 'daily', 'status' => 'active']);

        return CompetitorProduct::create([
            'tenant_id' => $tenant->id, 'monitoring_target_id' => $target->id,
            'competitor_source_id' => $source->id, 'url' => "https://{$host}/p/1", 'match_status' => 'confirmed',
        ]);
    }

    /** Bind a strong fake PII filter so the happy path can run without the redactor package. */
    private function bindStrongPii(): void
    {
        $this->app->bind(PiiFilterInterface::class, fn () => new class implements PiiFilterInterface
        {
            public function redact(string $text): string
            {
                return (string) preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', '[REDACTED]', $text);
            }

            public function isStrong(): bool
            {
                return true;
            }
        });
    }

    #[Test]
    public function it_refuses_when_module_is_disabled(): void
    {
        config()->set('price-intelligence.review_insight.enabled', false);
        $this->bindStrongPii();
        $competitor = $this->competitor();

        $this->expectException(ReviewInsightDisabledException::class);
        app(ReviewAggregator::class)->aggregate($competitor, ['great'], '2026-W21');
    }

    #[Test]
    public function it_refuses_domain_not_opted_in(): void
    {
        config()->set('price-intelligence.review_insight.enabled', true);
        config()->set('price-intelligence.review_insight.allowed_domains', ['other.com']);
        $this->bindStrongPii();
        $competitor = $this->competitor('shop.it');

        $this->expectException(ReviewInsightDisabledException::class);
        app(ReviewAggregator::class)->aggregate($competitor, ['great'], '2026-W21');
    }

    #[Test]
    public function it_refuses_without_strong_pii_redaction(): void
    {
        config()->set('price-intelligence.review_insight.enabled', true);
        config()->set('price-intelligence.review_insight.allowed_domains', ['shop.it']);
        // Do NOT bind strong pii: default PiiFilter is weak (no redactor package in tests).
        $competitor = $this->competitor('shop.it');

        $this->expectException(ReviewInsightDisabledException::class);
        app(ReviewAggregator::class)->aggregate($competitor, ['great'], '2026-W21');
    }

    #[Test]
    public function happy_path_stores_only_anonymous_aggregate(): void
    {
        config()->set('price-intelligence.review_insight.enabled', true);
        config()->set('price-intelligence.review_insight.allowed_domains', ['shop.it']);
        $this->bindStrongPii();
        $competitor = $this->competitor('shop.it');

        $insight = app(ReviewAggregator::class)->aggregate($competitor, [
            'great quality, contact me at john@example.com',
            'love it, fast shipping',
            'broken on arrival',
        ], '2026-W21');

        $this->assertSame(3, $insight->sample_count);
        $this->assertTrue($insight->is_ai_generated);

        // GDPR: no raw text / email / author is persisted anywhere — the table has no
        // such columns and the stored payload must not contain the email.
        $json = json_encode(ReviewInsight::query()->first()->toArray());
        $this->assertStringNotContainsString('john@example.com', (string) $json);
    }
}
