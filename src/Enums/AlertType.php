<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Enums;

enum AlertType: string
{
    case PriceChanged = 'price.changed';
    case PriceDropped = 'price.dropped';
    case PriceRaised = 'price.raised';
    case UndercutDetected = 'undercut.detected';
    case StockOut = 'stock.out';
    case StockBackIn = 'stock.back_in';
    case BuyBoxLost = 'buybox.lost';
    case BuyBoxWon = 'buybox.won';
    case MapViolated = 'map.violated';
    case CompetitorFound = 'competitor.new_found';
    case CompetitorUrlDead = 'competitor.url_dead';
    case MatchSuggested = 'match.suggested';
    case MatchConfirmed = 'match.confirmed';
    case MatchRejected = 'match.rejected';
    case AnomalyDetected = 'anomaly.detected';
    case PromoStarted = 'promo.started';
    case PromoEnded = 'promo.ended';
    case RepricingSuggested = 'repricing.suggested';
    case NarrativeGenerated = 'narrative.generated';
    case DigestDaily = 'digest.daily';
}
