<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Contracts\AiActBridgeInterface;
use Padosoft\PriceIntelligence\Models\FetchLog;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Compliance\DomainRateLimiter;
use Padosoft\PriceIntelligence\Services\Compliance\NullAiActBridge;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function rate_limiter_blocks_after_the_limit(): void
    {
        $limiter = app(DomainRateLimiter::class);

        $this->assertTrue($limiter->attempt('shop.it', 2));
        $this->assertTrue($limiter->attempt('shop.it', 2));
        $this->assertFalse($limiter->attempt('shop.it', 2)); // 3rd in the window
        // A different host has its own bucket.
        $this->assertTrue($limiter->attempt('other.it', 2));
    }

    #[Test]
    public function ai_act_bridge_defaults_to_null_object(): void
    {
        $bridge = app(AiActBridgeInterface::class);

        $this->assertInstanceOf(NullAiActBridge::class, $bridge);
        $this->assertFalse($bridge->isActive());
        // No-ops must not throw.
        $bridge->recordDisclosure('forecast', ['x' => 1]);
        $bridge->recordHumanReview('match', ['y' => 2]);
    }

    #[Test]
    public function prune_command_deletes_logs_older_than_retention(): void
    {
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        FetchLog::query()->create(['tenant_id' => $tenant->id, 'url' => 'https://a/1', 'captured_at' => now()->subDays(100)]);
        FetchLog::query()->create(['tenant_id' => $tenant->id, 'url' => 'https://a/2', 'captured_at' => now()->subDays(5)]);

        $this->artisan('piprice:audit:prune', ['--days' => 90])->assertSuccessful();

        app(TenantContext::class)->set($tenant->id);
        $this->assertSame(1, FetchLog::query()->count());
    }
}
