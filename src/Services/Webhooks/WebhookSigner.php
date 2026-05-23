<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Webhooks;

/**
 * Signs and verifies webhook payloads with HMAC-SHA256. The header value is
 * "sha256=<hex>" (GitHub-style). With a shared secret this gives payload integrity
 * and authenticity (the receiver knows it came from a holder of the secret) —
 * not cryptographic non-repudiation.
 */
final class WebhookSigner
{
    public const HEADER = 'X-PI-Signature';

    public static function sign(string $payload, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    public static function verify(string $payload, string $secret, string $signature): bool
    {
        return hash_equals(self::sign($payload, $secret), $signature);
    }
}
