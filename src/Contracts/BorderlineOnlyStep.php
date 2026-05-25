<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

/**
 * Marks an expensive MatchStep (e.g. an LLM judge) that the pipeline runs ONLY when the
 * best score so far is uncertain (within [judgeFloor, high) of the confidence band).
 */
interface BorderlineOnlyStep {}
