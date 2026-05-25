<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Discovery;

use Padosoft\PriceIntelligence\Enums\AdapterCode;
use Padosoft\PriceIntelligence\Models\CompetitorSource;

/**
 * Maps a URL to a CompetitorSource row, creating one on first sight with an
 * adapter inferred from the host. Sources are global (not tenant-scoped).
 */
final class CompetitorSourceResolver
{
    public function resolveForUrl(string $url): CompetitorSource
    {
        $host = $this->normalizeHost($url);

        return CompetitorSource::query()->firstOrCreate(
            ['host' => $host],
            [
                'display_name' => $host,
                'country' => $this->countryFromHost($host),
                'adapter_code' => $this->adapterForHost($host)->value,
                'robots_policy' => (string) config('price-intelligence.compliance.robots.default', 'respect'),
                'rate_limit_rpm' => (int) config('price-intelligence.compliance.rate_limit.default_rpm', 30),
            ],
        );
    }

    public function normalizeHost(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    public function adapterForHost(string $host): AdapterCode
    {
        return match (true) {
            str_contains($host, 'amazon.') => AdapterCode::Amazon,
            str_contains($host, 'ebay.') => AdapterCode::Ebay,
            str_contains($host, 'google.') || str_contains($host, 'shopping.google') => AdapterCode::GoogleShopping,
            str_contains($host, 'idealo.') => AdapterCode::Idealo,
            str_contains($host, 'trovaprezzi.') => AdapterCode::Trovaprezzi,
            str_contains($host, 'farfetch.') => AdapterCode::Farfetch,
            default => AdapterCode::Generic,
        };
    }

    private function countryFromHost(string $host): ?string
    {
        if (preg_match('/\.([a-z]{2})$/', $host, $m) === 1) {
            $tld = strtoupper($m[1]);

            // Common ccTLDs map directly; .com/.net/etc are not countries.
            return in_array($tld, ['IT', 'FR', 'DE', 'ES', 'UK', 'NL', 'PL', 'BE', 'AT', 'PT'], true)
                ? ($tld === 'UK' ? 'GB' : $tld)
                : null;
        }

        return null;
    }
}
