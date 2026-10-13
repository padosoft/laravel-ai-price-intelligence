<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

/**
 * Outcome for one wanted attribute on one competitor page, as returned in
 * latest_content.attributes[key]. `value` is set only when it passed every check (one of the
 * allowed values, evidence found on the page); otherwise it is null and `rejected_reason` says why.
 */
final class ExtractedAttribute
{
    public const SOURCE_JSONLD = 'jsonld';

    public const SOURCE_LLM = 'llm';

    public const REJECTED_EVIDENCE_NOT_FOUND = 'evidence_not_found';

    public const REJECTED_VALUE_NOT_ALLOWED = 'value_not_allowed';

    /**
     * @param  string|array<int, string>|null  $value  an array only for multiple-value attributes
     */
    public function __construct(
        public readonly string|array|null $value = null,
        public readonly ?string $raw = null,
        public readonly ?string $source = null,
        public readonly ?string $evidence = null,
        public readonly float $confidence = 0.0,
        public readonly ?string $rejectedReason = null,
    ) {}

    public static function notFound(): self
    {
        return new self;
    }

    public function found(): bool
    {
        return $this->value !== null && $this->value !== '' && $this->value !== [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $value = $data['value'] ?? null;

        return new self(
            value: is_array($value)
                ? array_values(array_map(static fn (mixed $v): string => (string) $v, array_filter($value, 'is_scalar')))
                : (is_scalar($value) ? (string) $value : null),
            raw: is_scalar($data['raw'] ?? null) ? (string) $data['raw'] : null,
            source: is_string($data['source'] ?? null) ? $data['source'] : null,
            evidence: is_scalar($data['evidence'] ?? null) ? (string) $data['evidence'] : null,
            confidence: is_numeric($data['confidence'] ?? null) ? (float) $data['confidence'] : 0.0,
            rejectedReason: is_string($data['rejected_reason'] ?? null) ? $data['rejected_reason'] : null,
        );
    }

    /**
     * @return array{value: string|array<int, string>|null, raw: ?string, source: ?string, evidence: ?string, confidence: float, rejected_reason: ?string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'raw' => $this->raw,
            'source' => $this->source,
            'evidence' => $this->evidence,
            'confidence' => $this->confidence,
            'rejected_reason' => $this->rejectedReason,
        ];
    }
}
