<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Identifiers;

/**
 * Normalizes free-text product names for fuzzy comparison and similarity scoring.
 */
final class SlugNormalizer
{
    /** @var array<int, string> */
    private const STOPWORDS = [
        'the', 'a', 'an', 'and', 'of', 'to', 'for', 'with',
        'di', 'da', 'il', 'lo', 'la', 'le', 'i', 'gli', 'un', 'una', 'con', 'per', 'e',
    ];

    public static function normalize(string $value): string
    {
        $lower = mb_strtolower(trim($value));
        $ascii = self::toAscii($lower);
        $clean = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? '';

        return trim(preg_replace('/\s+/', ' ', $clean) ?? '');
    }

    /**
     * @return array<int, string>
     */
    public static function tokens(string $value, bool $dropStopwords = true): array
    {
        $normalized = self::normalize($value);

        if ($normalized === '') {
            return [];
        }

        $tokens = explode(' ', $normalized);

        if ($dropStopwords) {
            $tokens = array_values(array_filter(
                $tokens,
                static fn (string $t): bool => ! in_array($t, self::STOPWORDS, true),
            ));
        }

        return $tokens;
    }

    /**
     * Jaccard token-set similarity in [0,1].
     */
    public static function tokenSimilarity(string $a, string $b): float
    {
        $ta = self::tokens($a);
        $tb = self::tokens($b);

        if ($ta === [] || $tb === []) {
            return 0.0;
        }

        $setA = array_unique($ta);
        $setB = array_unique($tb);

        $intersection = count(array_intersect($setA, $setB));
        $union = count(array_unique(array_merge($setA, $setB)));

        return $union === 0 ? 0.0 : $intersection / $union;
    }

    private static function toAscii(string $value): string
    {
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss',
        ];

        return strtr($value, $map);
    }
}
