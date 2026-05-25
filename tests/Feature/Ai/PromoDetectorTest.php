<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Ai;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Contracts\PromoDetectorInterface;
use Padosoft\PriceIntelligence\Models\AiDecisionLog;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Ai\PromoDetector;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PromoDetectorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_bound(): void
    {
        $this->assertInstanceOf(PromoDetector::class, app(PromoDetectorInterface::class));
    }

    #[Test]
    public function it_returns_a_promo_result_and_logs(): void
    {
        config()->set('price-intelligence.ai_act.decision_log.enabled', true);
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $result = app(PromoDetectorInterface::class)->detect($tenant->id, 'Save 20% this week only', 10000);

        $this->assertSame('fake', $result->model);
        $this->assertIsBool($result->hasPromo);
        $this->assertSame(1, AiDecisionLog::query()->where('feature', 'promo_detection')->count());
    }
}
