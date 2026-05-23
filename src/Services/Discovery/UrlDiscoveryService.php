<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Discovery;

use Padosoft\LaravelAiSearchProviders\SearchProviderManager;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Models\CompetitorProduct;
use Padosoft\PriceIntelligence\Models\MonitoringTarget;
use Padosoft\PriceIntelligence\Services\Matching\MatchingPipeline;
use Padosoft\PriceIntelligence\Services\Matching\MatchingPipelineFactory;
use Padosoft\PriceIntelligence\Services\Matching\MatchPersister;
use Padosoft\PriceIntelligence\Support\Discovery\GeoSearchQueryFactory;

/**
 * Discovers competitor URLs for a monitoring target via the AI search providers,
 * matches each candidate against the host product and persists confirmed matches
 * / review proposals. If the target carries given_urls, discovery is skipped and
 * those URLs are matched directly (manual link path).
 *
 * @return array{confirmed: int, suggested: int, rejected: int, candidates: int}
 */
final class UrlDiscoveryService
{
    public function __construct(
        private readonly SearchProviderManager $search,
        private readonly MatchingPipelineFactory $pipelineFactory,
        private readonly MatchPersister $persister,
    ) {
    }

    /**
     * @return array{confirmed: int, suggested: int, rejected: int, candidates: int}
     */
    public function discover(MonitoringTarget $target): array
    {
        $givenUrls = (array) (($target->options['given_urls'] ?? []) ?: []);

        // Manual direct URLs: the user explicitly says "monitor this", so auto-confirm
        // without AI search or matching.
        if ($givenUrls !== []) {
            return $this->confirmManualUrls($target, $givenUrls);
        }

        $pipeline = $this->pipelineFactory->make();

        return $this->matchAndPersist($target, $pipeline, $this->snapshotsFromSearch($target));
    }

    /**
     * @param  array<int, string>  $urls
     * @return array{confirmed: int, suggested: int, rejected: int, candidates: int}
     */
    private function confirmManualUrls(MonitoringTarget $target, array $urls): array
    {
        $confirmed = 0;

        foreach ($urls as $url) {
            $outcome = new \Padosoft\PriceIntelligence\Data\MatchOutcome(
                status: \Padosoft\PriceIntelligence\Enums\MatchStatus::Confirmed,
                confidence: 100,
                method: \Padosoft\PriceIntelligence\Enums\MatchMethod::Manual,
            );
            $this->persister->persist($target, $url, $outcome);
            $confirmed++;
        }

        return ['confirmed' => $confirmed, 'suggested' => 0, 'rejected' => 0, 'candidates' => count($urls)];
    }

    /**
     * @param  array<int, ProductSnapshot>  $candidates
     * @return array{confirmed: int, suggested: int, rejected: int, candidates: int}
     */
    private function matchAndPersist(MonitoringTarget $target, MatchingPipeline $pipeline, array $candidates): array
    {
        $stats = ['confirmed' => 0, 'suggested' => 0, 'rejected' => 0, 'candidates' => count($candidates)];
        $product = $target->product;

        foreach ($candidates as $candidate) {
            // Skip already-known confirmed URLs for this target.
            $exists = CompetitorProduct::query()
                ->where('monitoring_target_id', $target->id)
                ->where('url', $candidate->url)
                ->exists();

            if ($exists) {
                continue;
            }

            $outcome = $pipeline->match($product, $candidate);
            $this->persister->persist($target, $candidate->url, $outcome);

            $stats[$outcome->status->value] = ($stats[$outcome->status->value] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * @return array<int, ProductSnapshot>
     */
    private function snapshotsFromSearch(MonitoringTarget $target): array
    {
        $product = $target->product;
        $domains = (array) (($target->options['given_domains'] ?? []) ?: []);

        $query = GeoSearchQueryFactory::make(
            brand: $product->brand,
            model: $product->model ?? $product->name,
            ean: $product->gtin,
            country: $target->country,
            locale: $target->locale,
            site: $domains[0] ?? null,
            limit: 10,
        );

        $execution = $this->search->searchWeb($query);

        $snapshots = [];

        foreach ($execution->results as $result) {
            if ($result->pageUrl === null || $result->pageUrl === '') {
                continue;
            }

            $snapshots[] = new ProductSnapshot(
                url: $result->pageUrl,
                title: $result->title,
                images: $result->imageUrl !== null ? [$result->imageUrl] : [],
            );
        }

        return $snapshots;
    }
}
