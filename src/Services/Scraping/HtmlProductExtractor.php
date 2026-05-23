<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Scraping;

use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Support\Pricing\PriceParser;

/**
 * Extracts a ProductSnapshot from raw HTML using, in priority order:
 *  1. schema.org/Product JSON-LD (most reliable, structured)
 *  2. OpenGraph product meta tags (og:title, product:price:amount, ...)
 *  3. fallback heuristics (title tag).
 * Pure and deterministic — unit-testable against saved HTML fixtures.
 */
final class HtmlProductExtractor
{
    public function extract(string $html, string $url): ProductSnapshot
    {
        $jsonld = $this->extractJsonLd($html);
        $og = $this->extractOpenGraph($html);
        $product = $this->findProductNode($jsonld);

        $title = $product['name'] ?? ($og['og:title'] ?? $this->titleTag($html));
        $gtin = $this->firstOf($product, ['gtin13', 'gtin', 'gtin12', 'gtin14', 'gtin8']);
        $mpn = $product['mpn'] ?? null;
        $brand = $this->brandName($product);

        [$priceCents, $currency, $rawPrice, $available] = $this->extractOffer($product, $og);

        $images = $this->images($product, $og);
        $htmlHash = hash('sha256', $html);

        return new ProductSnapshot(
            url: $url,
            priceCents: $priceCents,
            currency: $currency,
            rawPriceText: $rawPrice,
            available: $available,
            title: is_string($title) ? $title : null,
            description: is_string($product['description'] ?? null) ? $product['description'] : ($og['og:description'] ?? null),
            images: $images,
            jsonld: $jsonld,
            og: $og,
            gtin: is_string($gtin) ? $gtin : null,
            mpn: is_string($mpn) ? $mpn : null,
            brand: is_string($brand) ? $brand : null,
            htmlHash: $htmlHash,
            reachable: true,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractJsonLd(string $html): array
    {
        $blocks = [];

        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches) === false) {
            return [];
        }

        foreach ($matches[1] ?? [] as $raw) {
            $decoded = json_decode(trim($raw), true);

            if (is_array($decoded)) {
                // Handle @graph arrays and bare arrays.
                if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
                    foreach ($decoded['@graph'] as $node) {
                        if (is_array($node)) {
                            $blocks[] = $node;
                        }
                    }
                } elseif (array_is_list($decoded)) {
                    foreach ($decoded as $node) {
                        if (is_array($node)) {
                            $blocks[] = $node;
                        }
                    }
                } else {
                    $blocks[] = $decoded;
                }
            }
        }

        return $blocks;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>|null
     */
    private function findProductNode(array $blocks): ?array
    {
        foreach ($blocks as $node) {
            $type = $node['@type'] ?? null;
            $types = is_array($type) ? $type : [$type];

            if (in_array('Product', $types, true)) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function extractOpenGraph(string $html): array
    {
        $og = [];

        if (preg_match_all('/<meta[^>]+>/i', $html, $metas) !== false) {
            foreach ($metas[0] ?? [] as $tag) {
                if (preg_match('/(?:property|name)=["\']([^"\']+)["\']/i', $tag, $p) === 1
                    && preg_match('/content=["\']([^"\']*)["\']/i', $tag, $c) === 1) {
                    $key = strtolower($p[1]);
                    if (str_starts_with($key, 'og:') || str_starts_with($key, 'product:')) {
                        $og[$key] = html_entity_decode($c[1]);
                    }
                }
            }
        }

        return $og;
    }

    /**
     * @param  array<string, mixed>|null  $product
     * @param  array<string, string>  $og
     * @return array{0: ?int, 1: ?string, 2: ?string, 3: bool}
     */
    private function extractOffer(?array $product, array $og): array
    {
        $offer = null;

        if ($product !== null && isset($product['offers'])) {
            $offer = $product['offers'];
            if (is_array($offer) && array_is_list($offer)) {
                $offer = $offer[0] ?? null;
            }
        }

        if (is_array($offer) && isset($offer['price'])) {
            $raw = (string) $offer['price'];
            // Reuse PriceParser so JSON-LD prices with thousands separators (e.g. "1.299,00")
            // are handled consistently rather than via a naive float cast.
            $parsed = PriceParser::parse($raw);
            $currency = isset($offer['priceCurrency'])
                ? (string) $offer['priceCurrency']
                : ($parsed['currency'] ?? null);
            $available = ! isset($offer['availability'])
                || stripos((string) $offer['availability'], 'OutOfStock') === false;

            return [$parsed['cents'] ?? null, $currency, $raw, $available];
        }

        // OpenGraph product price fallback.
        if (isset($og['product:price:amount'])) {
            $parsed = PriceParser::parse($og['product:price:amount']);

            if ($parsed === null) {
                return [null, $og['product:price:currency'] ?? null, $og['product:price:amount'], true];
            }

            $currency = $og['product:price:currency'] ?? $parsed['currency'];

            return [$parsed['cents'], $currency, $og['product:price:amount'], true];
        }

        return [null, null, null, true];
    }

    /**
     * @param  array<string, mixed>|null  $product
     * @param  array<string, string>  $og
     * @return array<int, string>
     */
    private function images(?array $product, array $og): array
    {
        $images = [];

        if ($product !== null && isset($product['image'])) {
            $img = $product['image'];
            if (is_string($img)) {
                $images[] = $img;
            } elseif (is_array($img)) {
                foreach ($img as $i) {
                    if (is_string($i)) {
                        $images[] = $i;
                    } elseif (is_array($i) && isset($i['url']) && is_string($i['url'])) {
                        $images[] = $i['url'];
                    }
                }
            }
        }

        if ($images === [] && isset($og['og:image'])) {
            $images[] = $og['og:image'];
        }

        return array_values(array_unique($images));
    }

    /**
     * @param  array<string, mixed>|null  $product
     */
    private function brandName(?array $product): ?string
    {
        if ($product === null || ! isset($product['brand'])) {
            return null;
        }

        $brand = $product['brand'];

        if (is_string($brand)) {
            return $brand;
        }

        if (is_array($brand) && isset($brand['name']) && is_string($brand['name'])) {
            return $brand['name'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $node
     * @param  array<int, string>  $keys
     */
    private function firstOf(?array $node, array $keys): ?string
    {
        if ($node === null) {
            return null;
        }

        foreach ($keys as $key) {
            if (isset($node[$key]) && is_scalar($node[$key])) {
                return (string) $node[$key];
            }
        }

        return null;
    }

    private function titleTag(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m) === 1) {
            return trim(html_entity_decode(strip_tags($m[1])));
        }

        return null;
    }
}
