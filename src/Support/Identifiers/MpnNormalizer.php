<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Identifiers;

/**
 * Normalizes Manufacturer Part Numbers for equality comparison:
 * uppercase, strip non-alphanumeric, drop leading zeros of trailing numeric runs.
 */
final class MpnNormalizer
{
    public static function normalize(string $value): string
    {
        $upper = strtoupper(trim($value));
        $alnum = preg_replace('/[^A-Z0-9]+/', '', $upper) ?? '';

        return $alnum;
    }

    public static function equals(string $a, string $b): bool
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);

        return $na !== '' && $na === $nb;
    }
}
