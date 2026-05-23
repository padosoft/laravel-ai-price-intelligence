<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Pricing;

/**
 * Parses human price strings into integer cents, handling EU ("1.299,00") and
 * US ("1,299.00") thousand/decimal conventions and currency symbols.
 */
final class PriceParser
{
    /**
     * @return array{cents: int, currency: ?string}|null
     */
    public static function parse(string $text): ?array
    {
        $currency = self::detectCurrency($text);

        // Keep digits, separators, minus.
        $clean = preg_replace('/[^0-9.,]/', '', $text) ?? '';

        if ($clean === '' || ! preg_match('/\d/', $clean)) {
            return null;
        }

        $normalized = self::normalizeDecimal($clean);

        if (! is_numeric($normalized)) {
            return null;
        }

        $cents = (int) round(((float) $normalized) * 100);

        return ['cents' => $cents, 'currency' => $currency];
    }

    public static function detectCurrency(string $text): ?string
    {
        return match (true) {
            str_contains($text, '€') || stripos($text, 'EUR') !== false => 'EUR',
            str_contains($text, '£') || stripos($text, 'GBP') !== false => 'GBP',
            str_contains($text, '$') || stripos($text, 'USD') !== false => 'USD',
            default => null,
        };
    }

    private static function normalizeDecimal(string $clean): string
    {
        $hasComma = str_contains($clean, ',');
        $hasDot = str_contains($clean, '.');

        if ($hasComma && $hasDot) {
            // The rightmost separator is the decimal separator.
            if (strrpos($clean, ',') > strrpos($clean, '.')) {
                // EU: dot=thousands, comma=decimal.
                return str_replace(',', '.', str_replace('.', '', $clean));
            }

            // US: comma=thousands, dot=decimal.
            return str_replace(',', '', $clean);
        }

        if ($hasComma) {
            // Only comma: treat as decimal if it looks like 2-decimal, else thousands.
            if (preg_match('/,\d{1,2}$/', $clean) === 1) {
                return str_replace(',', '.', $clean);
            }

            return str_replace(',', '', $clean);
        }

        // Only dot or none: dot is decimal unless it's a thousands group (e.g. 1.299).
        if ($hasDot && preg_match('/\.\d{3}$/', $clean) === 1 && substr_count($clean, '.') === 1) {
            return str_replace('.', '', $clean);
        }

        return $clean;
    }
}
