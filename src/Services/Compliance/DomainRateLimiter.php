<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Compliance;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Per-domain "gentleman" rate limiter using a fixed one-minute window in the
 * cache. attempt() returns false when the domain has hit its limit for the
 * current minute, so callers can defer the fetch.
 */
final class DomainRateLimiter
{
    public function __construct(
        private readonly Cache $cache,
    ) {
    }

    public function attempt(string $host, ?int $perMinute = null): bool
    {
        $limit = $perMinute ?? (int) config('price-intelligence.compliance.rate_limit.default_rpm', 30);

        if ($limit <= 0) {
            return true; // unlimited
        }

        $key = $this->key($host);
        $current = (int) $this->cache->get($key, 0);

        if ($current >= $limit) {
            return false;
        }

        // Initialize the window with a 60s TTL on first hit, then increment.
        if ($current === 0) {
            $this->cache->put($key, 1, now()->addSeconds(60));
        } else {
            $this->cache->increment($key);
        }

        return true;
    }

    public function remaining(string $host, ?int $perMinute = null): int
    {
        $limit = $perMinute ?? (int) config('price-intelligence.compliance.rate_limit.default_rpm', 30);
        $current = (int) $this->cache->get($this->key($host), 0);

        return max(0, $limit - $current);
    }

    private function key(string $host): string
    {
        return 'pi:ratelimit:' . strtolower($host) . ':' . now()->format('YmdHi');
    }
}
