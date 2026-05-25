<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Models\AiDecisionLog;
use Padosoft\PriceIntelligence\Models\ApiKey;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class AiDecisionApiTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): string
    {
        $tenant = Tenant::create(['code' => 't', 'name' => 'T']);
        [, $key] = ApiKey::issue($tenant->id, 'k', ['*']);
        app(TenantContext::class)->set($tenant->id);

        return $key;
    }

    private function seedDecisions(): void
    {
        AiDecisionLog::query()->create(['feature' => 'narrative', 'model' => 'fake', 'output' => ['x' => 1], 'human_reviewed' => false]);
        AiDecisionLog::query()->create(['feature' => 'forecast', 'model' => 'statistical', 'output' => ['y' => 2], 'subject_type' => 'Product', 'subject_id' => 5, 'human_reviewed' => false]);
    }

    #[Test]
    public function it_lists_ai_decisions(): void
    {
        $key = $this->auth();
        $this->seedDecisions();

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/ai-decisions')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_filters_by_feature(): void
    {
        $key = $this->auth();
        $this->seedDecisions();

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/ai-decisions?feature=narrative')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.feature', 'narrative');
    }

    #[Test]
    public function it_filters_by_subject(): void
    {
        $key = $this->auth();
        $this->seedDecisions();

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/ai-decisions?subject_type=Product&subject_id=5')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.feature', 'forecast');
    }

    #[Test]
    public function it_requires_auth(): void
    {
        $this->getJson('/api/v1/ai-decisions')->assertUnauthorized();
    }
}
