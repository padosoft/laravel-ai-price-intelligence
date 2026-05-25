<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Data\LlmResult;

interface LlmProviderInterface
{
    /**
     * Free-text completion. $options may carry: feature (string, default 'general'),
     * provider (string), model (string), timeout (int).
     *
     * @param  array<string, mixed>  $options
     */
    public function complete(string $instructions, string $prompt, array $options = []): LlmResult;

    /**
     * Structured completion: the model is asked to return strict JSON which is decoded
     * into LlmResult::$json. Implementations throw \RuntimeException on undecodable output.
     *
     * @param  array<string, mixed>  $options
     */
    public function completeJson(string $instructions, string $prompt, array $options = []): LlmResult;

    /**
     * Vision completion: image URLs are attached to the prompt. Like completeJson(), the response is
     * expected to be a single JSON object and is decoded into LlmResult::$json; implementations throw
     * \RuntimeException on undecodable output.
     *
     * @param  array<int, string>  $imageUrls
     * @param  array<string, mixed>  $options
     */
    public function vision(string $instructions, string $prompt, array $imageUrls, array $options = []): LlmResult;

    /** True when this is the offline deterministic driver (no external calls). */
    public function isFake(): bool;
}
