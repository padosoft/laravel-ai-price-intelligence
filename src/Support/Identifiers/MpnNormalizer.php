<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Identifiers;

/**
 * Normalizes Manufacturer Part Numbers for equality comparison:
 * uppercase and strip all non-alphanumeric characters (spaces, dashes, dots),
 * so "AC-X1-64" and "ac x1 64" compare equal.
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
