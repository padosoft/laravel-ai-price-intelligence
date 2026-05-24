<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Exceptions;

use RuntimeException;

final class ReviewInsightDisabledException extends RuntimeException
{
    public static function moduleOff(): self
    {
        return new self('Review insight module is disabled (review_insight.enabled = false).');
    }

    public static function domainNotAllowed(string $host): self
    {
        return new self("Review scraping is not opted-in for domain: {$host}.");
    }

    public static function weakPii(): self
    {
        return new self('Review sentiment requires padosoft/laravel-pii-redactor to be installed (strong PII redaction).');
    }
}
