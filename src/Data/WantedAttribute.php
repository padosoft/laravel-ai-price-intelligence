<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

/**
 * One category attribute a client wants read from competitor pages (target
 * options.wanted_attributes). allowed_values are the client's own labels (any language): an
 * extracted value is always one of them, spelled exactly the same, so the client can map it back.
 */
final class WantedAttribute
{
    /**
     * @param  array<int, string>  $allowedValues
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $allowedValues = [],
        public readonly bool $multiple = false,
        public readonly ?string $jsonldProperty = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $allowed = is_array($data['allowed_values'] ?? null) ? $data['allowed_values'] : [];
        $jsonldProperty = $data['jsonld_property'] ?? null;

        return new self(
            key: (string) ($data['key'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            allowedValues: array_values(array_map(
                static fn (mixed $value): string => (string) $value,
                array_filter($allowed, 'is_scalar'),
            )),
            multiple: (bool) ($data['multiple'] ?? false),
            jsonldProperty: is_string($jsonldProperty) && $jsonldProperty !== '' ? $jsonldProperty : null,
        );
    }

    /**
     * The wanted attributes stored on a monitoring target's options.
     *
     * @return array<int, self>
     */
    public static function listFromOptions(mixed $options): array
    {
        $items = is_array($options) && is_array($options['wanted_attributes'] ?? null)
            ? $options['wanted_attributes']
            : [];

        $wanted = [];

        foreach ($items as $item) {
            if (is_array($item) && ($item['key'] ?? '') !== '') {
                $wanted[] = self::fromArray($item);
            }
        }

        return $wanted;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'allowed_values' => $this->allowedValues,
            'multiple' => $this->multiple,
            'jsonld_property' => $this->jsonldProperty,
        ];
    }
}
