<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Data\PromoResult;

interface PromoDetectorInterface
{
    public function detect(int|string $tenantId, string $pageText, ?int $listPriceCents = null): PromoResult;
}
