<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping\Drivers;

use Illuminate\Support\Facades\Http;
use Padosoft\PriceIntelligence\Contracts\ProductScraperInterface;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Services\Scraping\HtmlProductExtractor;
use Throwable;

/**
 * Default scraper: a plain HTTP GET + HTML extraction. Handles transient
 * unreachability gracefully (returns an unreachable snapshot rather than
 * throwing) so a momentarily-down site doesn't kill the job — the URL stays
 * valid and is retried next cycle.
 */
final class GenericHttpScraper implements ProductScraperInterface
{
    public function __construct(
        private readonly HtmlProductExtractor $extractor,
    ) {
    }

    public function supports(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function scrape(string $url, array $options = []): ProductSnapshot
    {
        $userAgents = (array) config('price-intelligence.scraping.user_agents', []);
        $ua = $userAgents[0] ?? 'Mozilla/5.0 PriceIntelligenceBot';

        try {
            $response = Http::withHeaders(['User-Agent' => $ua, 'Accept-Language' => $this->acceptLanguage($options)])
                ->timeout((int) ($options['timeout'] ?? 20))
                ->get($url);

            if (! $response->successful()) {
                return ProductSnapshot::unreachable($url);
            }

            return $this->extractor->extract($response->body(), $url);
        } catch (Throwable) {
            return ProductSnapshot::unreachable($url);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function acceptLanguage(array $options): string
    {
        $locale = $options['locale'] ?? null;

        return is_string($locale) && $locale !== '' ? $locale : 'en';
    }
}
