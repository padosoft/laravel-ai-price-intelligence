<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Config;

/**
 * Robust boolean reader for config flags. Unlike `(bool) config(...)`, this
 * interprets common falsy strings ('false', '0', 'off', 'no', '') correctly, so
 * host apps wiring flags from env/strings get the behavior they expect.
 */
final class Flag
{
    public static function enabled(string $key, bool $default = true): bool
    {
        $value = config($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
