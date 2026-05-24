<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Padosoft\PriceIntelligence\Services\Compliance\PiiFilter;

final class PiiFilterTest extends TestCase
{
    #[Test]
    public function regex_fallback_redacts_email_phone_and_iban(): void
    {
        $filter = new PiiFilter();
        $out = $filter->redact('Contact mario@example.com or +39 333 1234567, IBAN IT60X0542811101000000123456');

        $this->assertStringNotContainsString('mario@example.com', $out);
        $this->assertStringNotContainsString('1234567', $out);
        $this->assertStringNotContainsString('IT60X0542811101000000123456', $out);
        $this->assertStringContainsString('[REDACTED]', $out);
    }

    #[Test]
    public function fallback_is_not_considered_strong(): void
    {
        // Without padosoft/laravel-pii-redactor installed, the filter is the weak
        // regex fallback and must report itself as NOT strong.
        $this->assertFalse((new PiiFilter())->isStrong());
    }

    #[Test]
    public function plain_text_is_unchanged(): void
    {
        $filter = new PiiFilter();
        $this->assertSame('great product fast shipping', $filter->redact('great product fast shipping'));
    }
}
