<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Compliance;

/**
 * Minimal robots.txt parser/matcher. Pure and deterministic. Implements the
 * common subset: User-agent groups, Disallow/Allow with longest-match wins and
 * `*` / `$` wildcards. Unknown directives are ignored.
 */
final class RobotsTxtParser
{
    public function isAllowed(string $robotsTxt, string $path, string $userAgent = '*'): bool
    {
        $rules = $this->rulesFor($robotsTxt, $userAgent);

        if ($rules === []) {
            return true; // no applicable rules -> allowed
        }

        $bestLen = -1;
        $allowed = true;

        foreach ($rules as [$type, $pattern]) {
            if ($this->matches($pattern, $path)) {
                $len = strlen($pattern);
                // Longest match wins; on a tie, Allow wins over Disallow.
                if ($len > $bestLen || ($len === $bestLen && $type === 'allow')) {
                    $bestLen = $len;
                    $allowed = $type === 'allow';
                }
            }
        }

        return $allowed;
    }

    /**
     * @return array<int, array{0: string, 1: string}>  [type, pattern]
     */
    private function rulesFor(string $robotsTxt, string $userAgent): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $robotsTxt) ?: [];
        $groups = [];     // ua => rules
        $currentUas = [];
        $collecting = false;

        foreach ($lines as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');

            if ($line === '') {
                continue;
            }

            [$field, $value] = $this->splitDirective($line);

            if ($field === 'user-agent') {
                if (! $collecting) {
                    $currentUas = [];
                }
                $currentUas[] = strtolower($value);
                $collecting = true;

                foreach ($currentUas as $ua) {
                    $groups[$ua] ??= [];
                }

                continue;
            }

            $collecting = false;

            if (($field === 'disallow' || $field === 'allow') && $currentUas !== []) {
                // An empty value carries no path pattern: an empty Disallow means
                // "allow all" and an empty Allow is meaningless — skip both, so an empty
                // Allow can't compile to a catch-all regex overriding real Disallow rules.
                if ($value === '') {
                    continue;
                }

                foreach ($currentUas as $ua) {
                    $groups[$ua][] = [$field, $value];
                }
            }
        }

        return $this->selectGroup($groups, $userAgent);
    }

    /**
     * Pick the matching group per the robots convention: a crawler matches a group
     * whose user-agent token is a case-insensitive substring of the crawler name;
     * the most specific (longest) matching token wins, falling back to "*".
     *
     * @param  array<string, array<int, array{0: string, 1: string}>>  $groups
     * @return array<int, array{0: string, 1: string}>
     */
    private function selectGroup(array $groups, string $userAgent): array
    {
        $ua = strtolower($userAgent);
        $bestKey = null;
        $bestLen = -1;

        foreach (array_keys($groups) as $key) {
            if ($key === '*') {
                continue;
            }

            if (str_contains($ua, $key) && strlen($key) > $bestLen) {
                $bestKey = $key;
                $bestLen = strlen($key);
            }
        }

        if ($bestKey !== null) {
            return $groups[$bestKey];
        }

        return $groups['*'] ?? [];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitDirective(string $line): array
    {
        $pos = strpos($line, ':');

        if ($pos === false) {
            return ['', ''];
        }

        return [strtolower(trim(substr($line, 0, $pos))), trim(substr($line, $pos + 1))];
    }

    private function matches(string $pattern, string $path): bool
    {
        // Translate robots wildcards (* and trailing $) to a regex.
        $anchoredEnd = str_ends_with($pattern, '$');
        $core = $anchoredEnd ? substr($pattern, 0, -1) : $pattern;

        $regex = preg_quote($core, '#');
        $regex = str_replace('\*', '.*', $regex);
        $regex = '#^' . $regex . ($anchoredEnd ? '$' : '') . '#';

        return preg_match($regex, $path) === 1;
    }
}
