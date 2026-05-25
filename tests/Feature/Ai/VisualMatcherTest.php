<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Ai;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Contracts\VisualMatcherInterface;
use Padosoft\PriceIntelligence\Models\AiDecisionLog;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Ai\VisualMatcher;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class VisualMatcherTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_bound(): void
    {
        $this->assertInstanceOf(VisualMatcher::class, app(VisualMatcherInterface::class));
    }

    #[Test]
    public function identical_image_refs_are_same_product_and_logged(): void
    {
        config()->set('price-intelligence.ai_act.decision_log.enabled', true);
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $url = 'https://cdn.example/x.jpg';
        $result = app(VisualMatcherInterface::class)->isSameProduct($tenant->id, $url, $url);

        $this->assertTrue($result->sameProduct);
        $this->assertSame('fake', $result->model);
        $this->assertSame(1, AiDecisionLog::query()->where('feature', 'visual_match')->count());
    }
}
