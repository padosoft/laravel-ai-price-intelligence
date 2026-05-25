<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

final class LlmResult
{
    /**
     * @param  array<string, mixed>|null  $json  decoded JSON payload when the caller requested structured output
     */
    public function __construct(
        public readonly string $text,
        public readonly string $model,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
        public readonly ?array $json = null,
    ) {}

    public function totalTokens(): ?int
    {
        if ($this->promptTokens === null && $this->completionTokens === null) {
            return null;
        }

        return (int) $this->promptTokens + (int) $this->completionTokens;
    }
}
