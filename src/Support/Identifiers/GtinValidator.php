<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Identifiers;

/**
 * Validates GTIN-8 / GTIN-12 (UPC-A) / GTIN-13 (EAN-13) / GTIN-14 using the
 * standard GS1 mod-10 check digit algorithm. EAN and UPC are subsets of GTIN.
 */
final class GtinValidator
{
    private const VALID_LENGTHS = [8, 12, 13, 14];

    public static function isValid(string $value): bool
    {
        $digits = self::normalize($value);

        if ($digits === null) {
            return false;
        }

        return self::checkDigit(substr($digits, 0, -1)) === (int) substr($digits, -1);
    }

    /**
     * Returns the stripped digit string (8/12/13/14 digits, as-is) or null if the
     * shape is invalid. Use toGtin14() for the left-padded canonical form.
     */
    public static function normalize(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (! in_array(strlen($digits), self::VALID_LENGTHS, true)) {
            return null;
        }

        return $digits;
    }

    /**
     * Returns a GTIN-14 left-padded canonical form for cross-format equality
     * (e.g. UPC-A 12 and EAN-13 of the same product compare equal).
     */
    public static function toGtin14(string $value): ?string
    {
        if (! self::isValid($value)) {
            return null;
        }

        $digits = self::normalize($value);

        return str_pad((string) $digits, 14, '0', STR_PAD_LEFT);
    }

    public static function equals(string $a, string $b): bool
    {
        $ga = self::toGtin14($a);
        $gb = self::toGtin14($b);

        return $ga !== null && $ga === $gb;
    }

    /**
     * Compute the GS1 mod-10 check digit for a payload (without its check digit).
     */
    public static function checkDigit(string $payload): int
    {
        $sum = 0;
        $position = 1;

        // Weight 3,1,3,1... starting from the rightmost payload digit.
        for ($i = strlen($payload) - 1; $i >= 0; $i--) {
            $digit = (int) $payload[$i];
            $sum += ($position % 2 === 1) ? $digit * 3 : $digit;
            $position++;
        }

        return (10 - ($sum % 10)) % 10;
    }
}
