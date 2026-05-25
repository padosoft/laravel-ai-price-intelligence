<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Contracts\PromoDetectorInterface;
use Padosoft\PriceIntelligence\Data\PromoResult;

final class PromoDetector implements PromoDetectorInterface
{
    public function __construct(
        private readonly LlmProviderInterface $llm,
        private readonly AiDecisionLogger $logger,
    ) {}

    public function detect(int|string $tenantId, string $pageText, ?int $listPriceCents = null): PromoResult
    {
        $prompt = 'List price (cents): '.($listPriceCents ?? 'unknown')."\n\nPage text:\n".$pageText;

        $result = $this->llm->completeJson(
            'You detect retail promotions in product page text. Return JSON: '
            .'{"has_promo": bool, "promo_type": "sale|coupon|bundle|loyalty|clearance"|null, '
            .'"valid_from": ISO-date|null, "valid_to": ISO-date|null, "conditions": string|null, '
            .'"effective_discount_pct": number|null}.',
            $prompt,
            ['feature' => 'promo_detection'],
        );

        $json = $result->json ?? [];
        $promo = new PromoResult(
            hasPromo: (bool) ($json['has_promo'] ?? false),
            promoType: $this->nullableString($json['promo_type'] ?? null),
            validFrom: $this->nullableString($json['valid_from'] ?? null),
            validTo: $this->nullableString($json['valid_to'] ?? null),
            conditions: $this->nullableString($json['conditions'] ?? null),
            effectiveDiscountPct: isset($json['effective_discount_pct']) && is_numeric($json['effective_discount_pct'])
                ? (float) $json['effective_discount_pct']
                : null,
            model: $result->model,
        );

        $this->logger->record(
            tenantId: $tenantId,
            feature: 'promo_detection',
            output: $json,
            model: $result->model,
        );

        return $promo;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
