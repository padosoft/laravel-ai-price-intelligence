<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Compliance;

use Illuminate\Cache\RateLimiter;

/**
 * Per-domain "gentleman" rate limiter built on Laravel's RateLimiter (atomic hit
 * counter with a managed decay window). attempt() returns false when the domain
 * has hit its per-minute limit, so callers can defer the fetch.
 *
 * Note: this is a politeness guard, not a security control. Under heavy concurrency
 * across many workers the limit may be exceeded by a small margin (the check + hit
 * are not globally locked); that is an acceptable trade-off for polite scraping.
 */
final class DomainRateLimiter
{
    private const WINDOW_SECONDS = 60;

    public function __construct(
        private readonly RateLimiter $limiter,
    ) {
    }

    public function attempt(string $host, ?int $perMinute = null): bool
    {
        $limit = $perMinute ?? (int) config('price-intelligence.compliance.rate_limit.default_rpm', 30);

        if ($limit <= 0) {
            return true; // unlimited
        }

        $key = $this->key($host);

        if ($this->limiter->tooManyAttempts($key, $limit)) {
            return false;
        }

        $this->limiter->hit($key, self::WINDOW_SECONDS);

        return true;
    }

    public function remaining(string $host, ?int $perMinute = null): int
    {
        $limit = $perMinute ?? (int) config('price-intelligence.compliance.rate_limit.default_rpm', 30);

        return max(0, $this->limiter->remaining($this->key($host), $limit));
    }

    private function key(string $host): string
    {
        return 'pi:ratelimit:' . strtolower($host);
    }
}
