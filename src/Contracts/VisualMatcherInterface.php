<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Data\VisualMatchResult;

interface VisualMatcherInterface
{
    public function isSameProduct(int|string $tenantId, string $imageUrlA, string $imageUrlB): VisualMatchResult;
}
