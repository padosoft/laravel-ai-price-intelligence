<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\PromoDetectorInterface;
use Padosoft\PriceIntelligence\Data\PromoResult;

/**
 * No-op detector bound when ai.promo_detection.enabled is false, so the feature
 * toggle is actually honored (callers receive a no-promo result instead of a live LLM call).
 */
final class NullPromoDetector implements PromoDetectorInterface
{
    public function detect(int|string $tenantId, string $pageText, ?int $listPriceCents = null): PromoResult
    {
        return new PromoResult(
            hasPromo: false,
            promoType: null,
            validFrom: null,
            validTo: null,
            conditions: null,
            effectiveDiscountPct: null,
            model: 'disabled',
        );
    }
}
