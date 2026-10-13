<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Enums;

/**
 * Progress of the category-attribute extraction on a content snapshot, exposed to API clients so
 * they know whether to keep polling (only `pending` means "wait").
 */
enum AttributeExtractionStatus: string
{
    // The target asks for no attributes.
    case Skipped = 'skipped';
    // schema.org values are stored; the LLM step is queued or running.
    case Pending = 'pending';
    // Final values (LLM step done, disabled, or reused from an identical earlier extraction).
    case Done = 'done';
    // The LLM step failed after its retries; the schema.org values are kept.
    case Failed = 'failed';
}
