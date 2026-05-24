<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use Padosoft\PriceIntelligence\Services\Webhooks\WebhookSigner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WebhookSignerTest extends TestCase
{
    #[Test]
    public function it_signs_and_verifies(): void
    {
        $payload = '{"event":"price.dropped"}';
        $secret = 'shh';

        $sig = WebhookSigner::sign($payload, $secret);

        $this->assertStringStartsWith('sha256=', $sig);
        $this->assertTrue(WebhookSigner::verify($payload, $secret, $sig));
    }

    #[Test]
    public function it_rejects_tampered_payload(): void
    {
        $sig = WebhookSigner::sign('{"a":1}', 'secret');

        $this->assertFalse(WebhookSigner::verify('{"a":2}', 'secret', $sig));
        $this->assertFalse(WebhookSigner::verify('{"a":1}', 'wrong', $sig));
    }
}
