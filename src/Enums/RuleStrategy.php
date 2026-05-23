<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Enums;

enum RuleStrategy: string
{
    case MatchCheapest = 'match_cheapest';
    case BeatTopN = 'beat_top_n';
    case UndercutPct = 'undercut_pct';
    case MatchWithFloor = 'match_with_floor';
    case DynamicDemand = 'dynamic_demand';
    case Custom = 'custom';
}
