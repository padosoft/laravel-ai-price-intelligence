<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Data\LlmResult;
use Padosoft\PriceIntelligence\Data\WantedAttribute;
use Padosoft\PriceIntelligence\Services\Ai\AttributeExtractor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AttributeExtractorTest extends TestCase
{
    private const PAGE = "Wool gabardine shorts\nComposition: 100% virgin wool\nMade in Romania\nZip fastening";

    /** @var array<string, mixed> */
    private array $sentPrompt = [];

    #[Test]
    public function it_reads_schema_org_properties_and_additional_properties_without_the_llm(): void
    {
        $jsonld = [[
            '@type' => 'Product',
            'material' => 'lana',
            'additionalProperty' => [
                ['@type' => 'PropertyValue', 'name' => 'Made in', 'value' => 'Italia'],
                ['@type' => 'PropertyValue', 'name' => 'Chiusura', 'value' => 'Velcro'],
            ],
        ]];

        $results = $this->extractor([])->fromJsonLd([
            new WantedAttribute('a1', 'Materiale', ['Lana', 'Cotone'], jsonldProperty: 'material'),
            new WantedAttribute('a2', 'Made in', ['Italia', 'Francia']),
            new WantedAttribute('a3', 'Chiusura', ['Zip', 'Bottoni']),
            new WantedAttribute('a4', 'Fascia Età', ['Adulto', 'Bambino']),
        ], $jsonld);

        // Case may differ, the returned value is the client's exact spelling.
        $this->assertSame('Lana', $results['a1']->value);
        $this->assertSame('jsonld', $results['a1']->source);
        $this->assertSame('Italia', $results['a2']->value);
        // A schema.org value outside the list is kept as raw, never forced into a wrong value.
        $this->assertNull($results['a3']->value);
        $this->assertSame('Velcro', $results['a3']->raw);
        $this->assertSame('value_not_allowed', $results['a3']->rejectedReason);
        $this->assertFalse($results['a4']->found());
    }

    #[Test]
    public function it_keeps_llm_values_that_are_allowed_and_quoted_from_the_page(): void
    {
        $run = $this->extractor(['attributes' => [
            'a1' => ['value' => 'lana', 'raw' => '100% virgin wool', 'evidence' => 'Composition:  100% VIRGIN wool', 'confidence' => 0.9],
            'a2' => ['value' => 'Zip', 'raw' => 'Zip fastening', 'evidence' => 'Zip fastening', 'confidence' => 1.4],
        ]])->fromPageText([
            new WantedAttribute('a1', 'Materiale', ['Lana', 'Cotone']),
            new WantedAttribute('a2', 'Chiusura', ['Zip', 'Bottoni']),
            new WantedAttribute('a3', 'Fascia Età', ['Adulto', 'Bambino']),
        ], self::PAGE, 'Wool gabardine shorts');

        $attributes = $run['attributes'];

        $this->assertSame('Lana', $attributes['a1']->value);
        $this->assertSame('100% virgin wool', $attributes['a1']->raw);
        $this->assertSame('llm', $attributes['a1']->source);
        $this->assertSame(0.9, $attributes['a1']->confidence);
        $this->assertNull($attributes['a1']->rejectedReason);
        $this->assertSame('Zip', $attributes['a2']->value);
        $this->assertSame(1.0, $attributes['a2']->confidence);
        // A key the model did not answer is reported as not found.
        $this->assertFalse($attributes['a3']->found());
        $this->assertNull($attributes['a3']->rejectedReason);
        // The model gets the allowed values and the page, and is told to map across languages.
        $this->assertSame(['Lana', 'Cotone'], $this->sentPrompt['attributes'][0]['allowed_values']);
        $this->assertSame(self::PAGE, $this->sentPrompt['page_text']);
    }

    #[Test]
    public function it_rejects_values_quoted_from_nowhere_or_outside_the_allowed_list(): void
    {
        $attributes = $this->extractor(['attributes' => [
            'a1' => ['value' => 'Lana', 'raw' => 'wool', 'evidence' => 'Material: 100% wool', 'confidence' => 0.9],
            'a2' => ['value' => 'Romania', 'raw' => 'Made in Romania', 'evidence' => 'Made in Romania', 'confidence' => 0.9],
            'a3' => ['value' => null, 'raw' => 'Made in Romania', 'evidence' => 'Made in Romania', 'confidence' => 0.2],
        ]])->fromPageText([
            new WantedAttribute('a1', 'Materiale', ['Lana', 'Cotone']),
            new WantedAttribute('a2', 'Made in', ['Italia', 'Francia']),
            new WantedAttribute('a3', 'Paese', ['Italia', 'Francia']),
        ], self::PAGE)['attributes'];

        $this->assertNull($attributes['a1']->value);
        $this->assertSame('evidence_not_found', $attributes['a1']->rejectedReason);
        $this->assertNull($attributes['a2']->value);
        $this->assertSame('value_not_allowed', $attributes['a2']->rejectedReason);
        $this->assertNull($attributes['a3']->value);
        $this->assertSame('Made in Romania', $attributes['a3']->raw);
        $this->assertSame('value_not_allowed', $attributes['a3']->rejectedReason);
    }

    #[Test]
    public function it_returns_the_page_value_when_no_allowed_values_are_given_and_arrays_for_multiple(): void
    {
        $attributes = $this->extractor(['attributes' => [
            'a1' => ['value' => 'Regular fit', 'raw' => 'Regular fit', 'evidence' => 'Wool gabardine shorts', 'confidence' => 0.7],
            'a2' => ['value' => ['lana', 'Cotone', 'Lana'], 'raw' => 'wool, cotton', 'evidence' => '100% virgin wool', 'confidence' => 0.8],
            'a3' => ['value' => ['Lana', 'Cotone'], 'raw' => 'wool', 'evidence' => '100% virgin wool', 'confidence' => 0.8],
        ]])->fromPageText([
            new WantedAttribute('a1', 'Vestibilità'),
            new WantedAttribute('a2', 'Materiali', ['Lana', 'Cotone'], multiple: true),
            new WantedAttribute('a3', 'Materiale', ['Lana', 'Cotone']),
        ], self::PAGE)['attributes'];

        $this->assertSame('Regular fit', $attributes['a1']->value);
        $this->assertSame(['Lana', 'Cotone'], $attributes['a2']->value);
        // A single-value attribute keeps only the first answer.
        $this->assertSame('Lana', $attributes['a3']->value);
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    private function extractor(array $answer): AttributeExtractor
    {
        $test = $this;

        return new AttributeExtractor(new class($answer, $test) implements LlmProviderInterface
        {
            /**
             * @param  array<string, mixed>  $answer
             */
            public function __construct(private readonly array $answer, private readonly AttributeExtractorTest $test) {}

            public function complete(string $instructions, string $prompt, array $options = []): LlmResult
            {
                return new LlmResult('', 'stub');
            }

            public function completeJson(string $instructions, string $prompt, array $options = []): LlmResult
            {
                $this->test->capturePrompt((array) json_decode($prompt, true));

                return new LlmResult((string) json_encode($this->answer), 'stub-model', json: $this->answer);
            }

            public function vision(string $instructions, string $prompt, array $imageUrls, array $options = []): LlmResult
            {
                return new LlmResult('', 'stub');
            }

            public function isFake(): bool
            {
                return false;
            }
        });
    }

    /**
     * @param  array<string, mixed>  $prompt
     */
    public function capturePrompt(array $prompt): void
    {
        $this->sentPrompt = $prompt;
    }
}
