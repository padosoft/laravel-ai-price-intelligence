<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Data\ExtractedAttribute;
use Padosoft\PriceIntelligence\Data\WantedAttribute;

/**
 * Reads the client's category attributes from one competitor product page.
 *
 * fromJsonLd() is deterministic and free: schema.org Product properties and additionalProperty.
 * fromPageText() asks the LLM for the attributes still missing and only keeps an answer when the
 * quoted evidence is really on the page and the value is one of the allowed values (the model may
 * translate: allowed values are the client's labels, the page can be in another language).
 */
final class AttributeExtractor
{
    public const FEATURE = 'attribute_extraction';

    private const JSONLD_CONFIDENCE = 0.95;

    public function __construct(private readonly LlmProviderInterface $llm) {}

    /**
     * @param  array<int, WantedAttribute>  $wanted
     * @param  array<int, array<string, mixed>>  $jsonld  the snapshot's JSON-LD blocks
     * @return array<string, ExtractedAttribute> one entry per wanted key
     */
    public function fromJsonLd(array $wanted, array $jsonld): array
    {
        $product = $this->productNode($jsonld);
        $results = [];

        foreach ($wanted as $attribute) {
            $raw = $product === null ? null : $this->jsonLdValue($product, $attribute);

            if ($raw === null) {
                $results[$attribute->key] = ExtractedAttribute::notFound();

                continue;
            }

            $value = $this->allowedValueFor($attribute, $raw);

            $results[$attribute->key] = $value === null
                ? new ExtractedAttribute(
                    raw: $raw,
                    source: ExtractedAttribute::SOURCE_JSONLD,
                    evidence: $raw,
                    rejectedReason: ExtractedAttribute::REJECTED_VALUE_NOT_ALLOWED,
                )
                : new ExtractedAttribute(
                    value: $attribute->multiple ? [$value] : $value,
                    raw: $raw,
                    source: ExtractedAttribute::SOURCE_JSONLD,
                    evidence: $raw,
                    confidence: self::JSONLD_CONFIDENCE,
                );
        }

        return $results;
    }

    /**
     * @param  array<int, WantedAttribute>  $wanted  the attributes still missing
     * @param  array<string, string>  $hints  raw schema.org values by key that matched no allowed value
     * @return array{attributes: array<string, ExtractedAttribute>, model: string, output: array<string, mixed>}
     */
    public function fromPageText(array $wanted, string $pageText, ?string $title = null, array $hints = []): array
    {
        $result = $this->llm->completeJson(
            $this->instructions(),
            (string) json_encode([
                'attributes' => array_map(static fn (WantedAttribute $attribute): array => [
                    'key' => $attribute->key,
                    'label' => $attribute->label,
                    'allowed_values' => $attribute->allowedValues,
                    'multiple' => $attribute->multiple,
                ], $wanted),
                'page_title' => $title,
                'structured_hints' => $hints === [] ? (object) [] : $hints,
                'page_text' => $pageText,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['feature' => self::FEATURE],
        );

        $answers = is_array($result->json['attributes'] ?? null) ? $result->json['attributes'] : [];
        // Evidence may quote any text the model was given: the page, its title or a schema.org hint.
        $haystack = $this->normalize(implode("\n", [$pageText, (string) $title, ...array_values($hints)]));
        $attributes = [];

        foreach ($wanted as $attribute) {
            $answer = $answers[$attribute->key] ?? null;
            $attributes[$attribute->key] = is_array($answer)
                ? $this->checkedAnswer($attribute, $answer, $haystack)
                : ExtractedAttribute::notFound();
        }

        return ['attributes' => $attributes, 'model' => $result->model, 'output' => $result->json ?? []];
    }

    private function instructions(): string
    {
        return <<<'TXT'
You extract product attributes from the text of ONE e-commerce product page.
Rules:
1. Use only information explicitly written in the page text, page title or structured hints. Never infer from images, brand knowledge or what similar products usually have.
2. "evidence" is a quote copied verbatim from the page text, in the page's own language, at most 200 characters.
3. "raw" is the attribute value as written on the page, in the page's language.
4. When an attribute has allowed_values: those values are in Italian and the page may be in another language. Map the meaning (e.g. "100% virgin wool" -> "Lana") and put in "value" ONE allowed value copied exactly, character by character. If no allowed value fits, set "value" to null and keep "raw".
5. When an attribute has no allowed_values, "value" is the value as written on the page.
6. When "multiple" is true, "value" is an array of allowed values.
7. If the page does not state the attribute, set "value", "raw" and "evidence" to null.
8. "confidence" is a number between 0 and 1.
Return {"attributes": {"<key>": {"value": ..., "raw": ..., "evidence": ..., "confidence": ...}}} with one entry for every requested key.
TXT;
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    private function checkedAnswer(WantedAttribute $attribute, array $answer, string $haystack): ExtractedAttribute
    {
        $raw = $this->text($answer['raw'] ?? null);
        $evidence = $this->text($answer['evidence'] ?? null);
        $confidence = is_numeric($answer['confidence'] ?? null) ? max(0.0, min(1.0, (float) $answer['confidence'])) : 0.0;
        $values = $this->answerValues($answer['value'] ?? null, $attribute->multiple);

        if ($values === []) {
            // The page states something the client's list cannot express: report it, never guess.
            return new ExtractedAttribute(
                raw: $raw,
                source: $raw === null ? null : ExtractedAttribute::SOURCE_LLM,
                evidence: $evidence,
                rejectedReason: $raw !== null && $attribute->allowedValues !== [] ? ExtractedAttribute::REJECTED_VALUE_NOT_ALLOWED : null,
            );
        }

        if ($evidence === null || ! str_contains($haystack, $this->normalize($evidence))) {
            return new ExtractedAttribute(
                raw: $raw,
                source: ExtractedAttribute::SOURCE_LLM,
                evidence: $evidence,
                rejectedReason: ExtractedAttribute::REJECTED_EVIDENCE_NOT_FOUND,
            );
        }

        $canonical = [];

        foreach ($values as $value) {
            $allowed = $attribute->allowedValues === [] ? $value : $this->allowedValueFor($attribute, $value);

            if ($allowed === null) {
                return new ExtractedAttribute(
                    raw: $raw ?? $value,
                    source: ExtractedAttribute::SOURCE_LLM,
                    evidence: $evidence,
                    rejectedReason: ExtractedAttribute::REJECTED_VALUE_NOT_ALLOWED,
                );
            }

            $canonical[$allowed] = $allowed;
        }

        return new ExtractedAttribute(
            value: $attribute->multiple ? array_values($canonical) : (string) reset($canonical),
            raw: $raw,
            source: ExtractedAttribute::SOURCE_LLM,
            evidence: $evidence,
            confidence: $confidence,
        );
    }

    /**
     * @return array<int, string>
     */
    private function answerValues(mixed $value, bool $multiple): array
    {
        $values = is_array($value) ? $value : [$value];

        if (! $multiple) {
            $values = array_slice($values, 0, 1);
        }

        return array_values(array_filter(
            array_map(fn (mixed $item): ?string => $this->text($item), $values),
            static fn (?string $item): bool => $item !== null,
        ));
    }

    /**
     * The allowed value matching $candidate, spelled exactly as the client sent it. Only case,
     * HTML entities and blank space may differ; anything else is not a match.
     */
    private function allowedValueFor(WantedAttribute $attribute, string $candidate): ?string
    {
        if ($attribute->allowedValues === []) {
            return $candidate;
        }

        $needle = $this->normalize($candidate);

        foreach ($attribute->allowedValues as $allowed) {
            if ($this->normalize($allowed) === $needle) {
                return $allowed;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function jsonLdValue(array $product, WantedAttribute $attribute): ?string
    {
        if ($attribute->jsonldProperty !== null && array_key_exists($attribute->jsonldProperty, $product)) {
            $value = $this->flatten($product[$attribute->jsonldProperty]);

            if ($value !== null) {
                return $value;
            }
        }

        $properties = $product['additionalProperty'] ?? [];
        $properties = is_array($properties) && ! array_is_list($properties) ? [$properties] : $properties;
        $names = array_filter([$this->normalize($attribute->label), $attribute->jsonldProperty === null ? null : $this->normalize($attribute->jsonldProperty)]);

        foreach (is_array($properties) ? $properties : [] as $property) {
            if (is_array($property) && in_array($this->normalize((string) ($this->flatten($property['name'] ?? null) ?? '')), $names, true)) {
                return $this->flatten($property['value'] ?? null);
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>|null
     */
    private function productNode(array $blocks): ?array
    {
        foreach ($blocks as $node) {
            $types = (array) ($node['@type'] ?? []);

            if (in_array('Product', $types, true) || in_array('ProductGroup', $types, true)) {
                return $node;
            }
        }

        return null;
    }

    private function flatten(mixed $value): ?string
    {
        if (is_array($value)) {
            if (isset($value['name'])) {
                return $this->flatten($value['name']);
            }

            $parts = array_filter(array_map(fn (mixed $item): ?string => $this->flatten($item), $value));

            return $parts === [] ? null : implode(', ', $parts);
        }

        return $this->text($value);
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function normalize(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));
    }
}
