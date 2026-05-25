<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Llm;

use Padosoft\PriceIntelligence\Services\Ai\Llm\FakeLlmProvider;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FakeLlmProviderTest extends TestCase
{
    #[Test]
    public function it_is_fake_and_deterministic(): void
    {
        $provider = new FakeLlmProvider;

        $this->assertTrue($provider->isFake());

        $a = $provider->complete('sys', 'hello world', ['feature' => 'general']);
        $b = $provider->complete('sys', 'hello world', ['feature' => 'general']);

        $this->assertSame($a->text, $b->text);
        $this->assertSame('fake', $a->model);
        $this->assertNotSame('', $a->text);
    }

    #[Test]
    public function complete_json_returns_feature_shaped_payload(): void
    {
        $provider = new FakeLlmProvider;

        $narrative = $provider->completeJson('sys', 'p', ['feature' => 'narrative']);
        $this->assertIsArray($narrative->json);
        $this->assertArrayHasKey('summary_md', $narrative->json);
        $this->assertArrayHasKey('highlights', $narrative->json);

        $gap = $provider->completeJson('sys', 'p', ['feature' => 'content_gap']);
        $this->assertArrayHasKey('missing_attributes', $gap->json);

        $promo = $provider->completeJson('sys', 'p', ['feature' => 'promo_detection']);
        $this->assertArrayHasKey('has_promo', $promo->json);

        $judge = $provider->completeJson('sys', 'p', ['feature' => 'match_judge']);
        $this->assertArrayHasKey('confidence', $judge->json);
    }

    #[Test]
    public function vision_returns_deterministic_same_product_payload(): void
    {
        $provider = new FakeLlmProvider;

        $same = $provider->vision('sys', 'compare', ['https://x/a.jpg', 'https://x/a.jpg'], ['feature' => 'visual_match']);
        $diff = $provider->vision('sys', 'compare', ['https://x/a.jpg', 'https://x/b.jpg'], ['feature' => 'visual_match']);

        $this->assertIsArray($same->json);
        $this->assertTrue($same->json['same_product']);
        $this->assertFalse($diff->json['same_product']);
    }
}
