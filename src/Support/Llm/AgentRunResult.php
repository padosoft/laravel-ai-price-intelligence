<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Llm;

final class AgentRunResult
{
    public function __construct(
        public readonly string $text,
        public readonly string $model,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
    ) {}
}
