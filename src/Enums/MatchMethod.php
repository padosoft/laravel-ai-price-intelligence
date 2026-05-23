<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Enums;

enum MatchMethod: string
{
    case Gtin = 'gtin';
    case MpnBrand = 'mpn_brand';
    case NormalizedName = 'normalized_name';
    case Embedding = 'embedding';
    case Visual = 'visual';
    case Llm = 'llm';
    case Manual = 'manual';
}
