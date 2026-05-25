<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Enums;

enum AdapterCode: string
{
    case Amazon = 'amazon';
    case Ebay = 'ebay';
    case GoogleShopping = 'google_shopping';
    case Idealo = 'idealo';
    case Trovaprezzi = 'trovaprezzi';
    case Farfetch = 'farfetch';
    case Generic = 'generic';
}
