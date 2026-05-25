<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Ai;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Contracts\NarrativeWriterInterface;
use Padosoft\PriceIntelligence\Models\AiDecisionLog;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Ai\NarrativeWriter;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class NarrativeWriterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_bound(): void
    {
        $this->assertInstanceOf(NarrativeWriter::class, app(NarrativeWriterInterface::class));
    }

    #[Test]
    public function it_writes_a_narrative_and_logs_the_decision(): void
    {
        config()->set('price-intelligence.ai_act.decision_log.enabled', true);
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $result = app(NarrativeWriterInterface::class)->write($tenant->id, '2026-W21', [
            'top_movers' => [['name' => 'Widget', 'delta_pct' => -12]],
        ]);

        $this->assertNotSame('', $result->summaryMd);
        $this->assertSame('fake', $result->model);
        $this->assertSame(1, AiDecisionLog::query()->where('feature', 'narrative')->count());
    }
}
