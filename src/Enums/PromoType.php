<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Enums;

enum PromoType: string
{
    case Sale = 'sale';
    case Coupon = 'coupon';
    case Bundle = 'bundle';
    case Loyalty = 'loyalty';
    case Clearance = 'clearance';
    case None = 'none';
}
