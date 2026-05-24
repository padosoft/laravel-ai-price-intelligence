<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

/**
 * Redacts personal data from free text before it is processed or persisted.
 * Backed by padosoft/laravel-pii-redactor when installed; a conservative regex
 * fallback is used otherwise.
 */
interface PiiFilterInterface
{
    public function redact(string $text): string;

    /**
     * Whether a real PII engine (laravel-pii-redactor) is active. The review
     * sentiment module refuses to run on raw UGC unless this is true.
     */
    public function isStrong(): bool;
}
