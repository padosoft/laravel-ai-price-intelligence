<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Exceptions;

use RuntimeException;

final class ReviewInsightDisabledException extends RuntimeException
{
    public static function moduleOff(): self
    {
        return new self('Review insight module is disabled (set price-intelligence.review_insight.enabled = true).');
    }

    public static function domainNotAllowed(string $host): self
    {
        return new self(
            "Review insight pipeline is not opted-in for domain: {$host} "
            . '(add it to price-intelligence.review_insight.allowed_domains).'
        );
    }

    public static function weakPii(): self
    {
        return new self('Review sentiment requires padosoft/laravel-pii-redactor to be installed (strong PII redaction).');
    }
}
