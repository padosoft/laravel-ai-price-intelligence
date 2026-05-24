<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Compliance;

use Padosoft\PriceIntelligence\Contracts\PiiFilterInterface;

/**
 * PII redaction backed by padosoft/laravel-pii-redactor when available. Falls
 * back to a conservative regex pass (email / phone / IBAN-ish) otherwise; that
 * fallback is NOT considered "strong" so the review module won't run on it.
 */
final class PiiFilter implements PiiFilterInterface
{
    public function redact(string $text): string
    {
        if ($this->hasRedactorPackage()) {
            return (string) \Padosoft\PiiRedactor\Facades\Pii::redact($text);
        }

        return $this->regexFallback($text);
    }

    public function isStrong(): bool
    {
        return $this->hasRedactorPackage();
    }

    private function hasRedactorPackage(): bool
    {
        return class_exists(\Padosoft\PiiRedactor\Facades\Pii::class);
    }

    private function regexFallback(string $text): string
    {
        $patterns = [
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',          // email
            '/\+?\d[\d\s().\-]{7,}\d/',                                    // phone-ish
            '/[A-Z]{2}\d{2}[A-Z0-9]{10,30}/',                             // IBAN-ish
        ];

        return (string) preg_replace($patterns, '[REDACTED]', $text);
    }
}
