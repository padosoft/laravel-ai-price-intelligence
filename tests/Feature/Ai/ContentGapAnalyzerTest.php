<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Ai;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Contracts\ContentGapAnalyzerInterface;
use Padosoft\PriceIntelligence\Models\AiDecisionLog;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Ai\ContentGapAnalyzer;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ContentGapAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_bound(): void
    {
        $this->assertInstanceOf(ContentGapAnalyzer::class, app(ContentGapAnalyzerInterface::class));
    }

    #[Test]
    public function it_returns_a_result_and_logs_the_decision(): void
    {
        config()->set('price-intelligence.ai_act.decision_log.enabled', true);
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'external_id' => 'sku-1',
            'name' => 'Widget',
            'brand' => 'Acme',
            'currency' => 'EUR',
            'base_country' => 'IT',
        ]);

        $result = app(ContentGapAnalyzerInterface::class)->analyze($product, [
            ['title' => 'Widget Pro', 'attributes' => ['color' => 'blue']],
        ]);

        $this->assertSame('fake', $result->model);
        $this->assertIsArray($result->missingAttributes);
        $this->assertSame(1, AiDecisionLog::query()->where('feature', 'content_gap')->count());
    }
}
