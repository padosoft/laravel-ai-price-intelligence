<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Compliance;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Per-domain "gentleman" rate limiter using an atomic fixed-window counter.
 * `add()` creates the window key with a TTL only if absent (atomic), and
 * `increment()` is atomic, so concurrent workers each consume exactly one slot
 * and the allowed count is never overshot — unlike a read-check-write sequence.
 */
final class DomainRateLimiter
{
    private const WINDOW_SECONDS = 60;

    public function __construct(
        private readonly Cache $cache,
    ) {}

    public function attempt(string $host, ?int $perMinute = null): bool
    {
        $limit = $perMinute ?? (int) config('price-intelligence.compliance.rate_limit.default_rpm', 30);

        if ($limit <= 0) {
            return true; // unlimited
        }

        $key = $this->key($host);

        // Atomically ensure the window counter exists with a TTL, then atomically
        // increment it. The increment result is this caller's unique slot number,
        // so the allowed count is never overshot under concurrency.
        $this->cache->add($key, 0, self::WINDOW_SECONDS);
        $count = (int) $this->cache->increment($key);

        return $count <= $limit;
    }

    public function remaining(string $host, ?int $perMinute = null): int
    {
        $limit = $perMinute ?? (int) config('price-intelligence.compliance.rate_limit.default_rpm', 30);

        return max(0, $limit - (int) $this->cache->get($this->key($host), 0));
    }

    private function key(string $host): string
    {
        return 'pi:ratelimit:'.strtolower($host);
    }
}
