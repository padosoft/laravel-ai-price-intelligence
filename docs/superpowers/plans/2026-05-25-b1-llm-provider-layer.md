# B1 — LLM Provider Layer Implementation Plan (core → v1.3.0)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a real, provider-agnostic LLM layer to `padosoft/laravel-ai-price-intelligence` (built on the official `laravel/ai` SDK + `padosoft/laravel-ai-regolo`), wire real LLM-backed implementations for Narrative, Content-Gap, Promo, Visual-match, the matching LLM-judge step, and real embeddings — each logging to `ai_decision_logs` and each falling back to a deterministic `fake` driver (the zero-config default) so CI stays green with no live calls.

**Architecture:** A thin `LlmProviderInterface` wraps `laravel/ai`. Two drivers: `FakeLlmProvider` (deterministic, default — produces feature-shaped output keyed by `options['feature']`) and `LaravelAiLlmProvider` (delegates to `laravel/ai` through a small `AgentRunner` seam so the SDK call is isolated and the wrapper is unit-testable without live calls). Embeddings mirror this with `FakeEmbeddingProvider` (existing) and a new `LaravelAiEmbeddingProvider` behind an `EmbeddingRunner` seam. Feature services depend on `LlmProviderInterface` only — the "fallback" is simply the fake driver being bound, so there is exactly one implementation per feature. Real provider/model selection is config-driven (`ai.llm.*`, `matching.embeddings.*`); live SDK calls are exercised only by an opt-in test suite gated on env keys (deferred to the owner).

**Tech Stack:** PHP 8.3, Laravel 11/12/13, `laravel/ai` ^0.7, `padosoft/laravel-ai-regolo`, Orchestra Testbench, PHPUnit (`#[Test]` attributes), `Http::fake` for fixtures.

**Conventions (read once):**
- Namespace `Padosoft\PriceIntelligence\`; PSR-4 from `src/`. Tests namespace `Padosoft\PriceIntelligence\Tests\` from `tests/`.
- Run PHP tooling in **PowerShell**: `vendor\bin\phpunit`, `vendor\bin\pint`, `vendor\bin\phpstan analyse --memory-limit=1G`. Run tests **by path** (never `--filter "A|B"` — the `|` breaks the PowerShell batch wrapper).
- All new classes are `final`, `declare(strict_types=1);`.
- After each task: run the touched test file, then `git add` + commit with the message shown.
- This whole plan is **one phase = one PR** (`feat/phase-b1-llm-provider`) under the strict loop in `.claude/rules/rule-lesson-progress-logs.md`. Commit per task locally; push once the local loop (PHPUnit + Pint + PHPStan + local Copilot `/review`) is clean.

---

## File Structure (what each new file owns)

**Contracts / DTOs**
- `src/Contracts/LlmProviderInterface.php` — the LLM seam consumed by every feature service.
- `src/Contracts/NarrativeWriterInterface.php` · `ContentGapAnalyzerInterface.php` · `PromoDetectorInterface.php` · `VisualMatcherInterface.php` — feature contracts.
- `src/Data/LlmResult.php` — text + model + token usage returned by the LLM provider.
- `src/Data/NarrativeResult.php` · `ContentGapResult.php` · `PromoResult.php` · `VisualMatchResult.php` — feature result DTOs.

**LLM provider drivers + seam**
- `src/Services/Ai/Llm/FakeLlmProvider.php` — deterministic default driver.
- `src/Services/Ai/Llm/LaravelAiLlmProvider.php` — `laravel/ai`-backed driver.
- `src/Support/Llm/AgentRunner.php` (interface) + `AgentRunResult` — isolates the `laravel/ai` text/vision call.
- `src/Support/Llm/LaravelAiAgentRunner.php` — real `laravel/ai` agent runner.

**Embeddings**
- `src/Support/Llm/EmbeddingRunner.php` (interface) — isolates the `laravel/ai` embeddings call.
- `src/Support/Llm/LaravelAiEmbeddingRunner.php` — real embeddings runner.
- `src/Services/Matching/Embeddings/LaravelAiEmbeddingProvider.php` — `EmbeddingProviderInterface` impl using the runner.

**Feature services**
- `src/Services/Ai/NarrativeWriter.php` · `ContentGapAnalyzer.php` · `PromoDetector.php` · `VisualMatcher.php`.
- `src/Services/Matching/Steps/LlmJudgeMatcher.php` + `src/Contracts/BorderlineOnlyStep.php` (marker).

**Modified**
- `composer.json` — add `laravel/ai`, `padosoft/laravel-ai-regolo` (require) + `laravel/ai` already pulls config.
- `config/price-intelligence.php` — add `ai.llm` block; extend `matching.embeddings` with `model`/`dimensions`/`provider`.
- `src/PriceIntelligenceServiceProvider.php` — bind `LlmProviderInterface`, rebind `EmbeddingProviderInterface` by driver, bind the four feature interfaces.
- `src/Services/Matching/MatchingPipeline.php` — honor `BorderlineOnlyStep` (skip expensive judge unless best is uncertain).
- `src/Services/Matching/MatchingPipelineFactory.php` — append `LlmJudgeMatcher` when `matching.llm.enabled`.
- `docs/PROGRESS.md`, `docs/LESSON.md`, `CHANGELOG.md`.

**Tests (new)**
- `tests/Feature/Llm/FakeLlmProviderTest.php`, `LaravelAiLlmProviderTest.php`, `LlmBindingTest.php`
- `tests/Feature/Embeddings/EmbeddingDriverTest.php`
- `tests/Feature/Ai/NarrativeWriterTest.php`, `ContentGapAnalyzerTest.php`, `PromoDetectorTest.php`, `VisualMatcherTest.php`
- `tests/Feature/Matching/LlmJudgeMatcherTest.php`
- `tests/Live/LiveLlmSmokeTest.php` (opt-in, skipped unless env keys present)

---

## Task 1: Add `laravel/ai` + `laravel-ai-regolo` dependencies

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Verify package availability**

Run (PowerShell):
```
composer show laravel/ai 2>$null; composer show padosoft/laravel-ai-regolo 2>$null
```
If not yet required, that's expected — proceed to add them.

- [ ] **Step 2: Add to `require`**

In `composer.json`, inside `"require"`, add (after the `padosoft/laravel-ai-search-providers` line):
```json
        "padosoft/laravel-ai-search-providers": "^1.0 || dev-main",
        "laravel/ai": "^0.7",
        "padosoft/laravel-ai-regolo": "^1.0 || dev-main"
```
Add to `"suggest"`:
```json
        "padosoft/laravel-ai-regolo": "Adds the EU/Italian-safe Regolo provider to the laravel/ai SDK"
```

- [ ] **Step 3: Install + verify autoload**

Run (PowerShell):
```
composer update laravel/ai padosoft/laravel-ai-regolo --with-all-dependencies
composer validate --strict
```
Expected: both packages resolve; `composer validate` passes. If `laravel-ai-regolo` is not yet on Packagist, drop it from `require` and keep only `suggest` + a note in `docs/LESSON.md` (the `regolo` provider then comes from the host app's `config/ai.php`). Record whichever path was taken in `docs/LESSON.md`.

- [ ] **Step 4: Confirm `laravel/ai` classes are autoloadable**

Run (PowerShell):
```
php -r "require 'vendor/autoload.php'; var_dump(class_exists(Laravel\Ai\Embeddings::class), function_exists('Laravel\Ai\agent'));"
```
Expected: `bool(true)` for the class; if the `agent()` helper namespace differs, note the real callable in `docs/LESSON.md` and use it in Task 6.

- [ ] **Step 5: Commit**

```
git add composer.json composer.lock docs/LESSON.md
git commit -m "build(b1): add laravel/ai SDK + laravel-ai-regolo provider"
```

---

## Task 2: `LlmResult` DTO

**Files:**
- Create: `src/Data/LlmResult.php`
- Test: `tests/Feature/Llm/FakeLlmProviderTest.php` (created in Task 4; DTO is exercised there)

- [ ] **Step 1: Write the DTO**

```php
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
```

- [ ] **Step 2: Static check + commit**

Run: `vendor\bin\phpstan analyse src/Data/LlmResult.php --memory-limit=1G`
Expected: no errors.
```
git add src/Data/LlmResult.php
git commit -m "feat(b1): add LlmResult DTO"
```

---

## Task 3: `LlmProviderInterface` contract

**Files:**
- Create: `src/Contracts/LlmProviderInterface.php`

- [ ] **Step 1: Write the contract**

```php
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
     * Vision completion: image URLs are attached to the prompt.
     *
     * @param  array<int, string>  $imageUrls
     * @param  array<string, mixed>  $options
     */
    public function vision(string $instructions, string $prompt, array $imageUrls, array $options = []): LlmResult;

    /** True when this is the offline deterministic driver (no external calls). */
    public function isFake(): bool;
}
```

- [ ] **Step 2: Static check + commit**

Run: `vendor\bin\phpstan analyse src/Contracts/LlmProviderInterface.php --memory-limit=1G`
Expected: no errors.
```
git add src/Contracts/LlmProviderInterface.php
git commit -m "feat(b1): add LlmProviderInterface contract"
```

---

## Task 4: `FakeLlmProvider` (deterministic default driver)

**Files:**
- Create: `src/Services/Ai/Llm/FakeLlmProvider.php`
- Test: `tests/Feature/Llm/FakeLlmProviderTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Llm;

use Padosoft\PriceIntelligence\Services\Ai\Llm\FakeLlmProvider;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FakeLlmProviderTest extends TestCase
{
    #[Test]
    public function it_is_fake_and_deterministic(): void
    {
        $provider = new FakeLlmProvider;

        $this->assertTrue($provider->isFake());

        $a = $provider->complete('sys', 'hello world', ['feature' => 'general']);
        $b = $provider->complete('sys', 'hello world', ['feature' => 'general']);

        $this->assertSame($a->text, $b->text);
        $this->assertSame('fake', $a->model);
        $this->assertNotSame('', $a->text);
    }

    #[Test]
    public function completeJson_returns_feature_shaped_payload(): void
    {
        $provider = new FakeLlmProvider;

        $narrative = $provider->completeJson('sys', 'p', ['feature' => 'narrative']);
        $this->assertIsArray($narrative->json);
        $this->assertArrayHasKey('summary_md', $narrative->json);
        $this->assertArrayHasKey('highlights', $narrative->json);

        $gap = $provider->completeJson('sys', 'p', ['feature' => 'content_gap']);
        $this->assertArrayHasKey('missing_attributes', $gap->json);

        $promo = $provider->completeJson('sys', 'p', ['feature' => 'promo_detection']);
        $this->assertArrayHasKey('has_promo', $promo->json);

        $judge = $provider->completeJson('sys', 'p', ['feature' => 'match_judge']);
        $this->assertArrayHasKey('confidence', $judge->json);
    }

    #[Test]
    public function vision_returns_deterministic_same_product_payload(): void
    {
        $provider = new FakeLlmProvider;

        $same = $provider->vision('sys', 'compare', ['https://x/a.jpg', 'https://x/a.jpg'], ['feature' => 'visual_match']);
        $diff = $provider->vision('sys', 'compare', ['https://x/a.jpg', 'https://x/b.jpg'], ['feature' => 'visual_match']);

        $this->assertIsArray($same->json);
        $this->assertTrue($same->json['same_product']);
        $this->assertFalse($diff->json['same_product']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor\bin\phpunit tests/Feature/Llm/FakeLlmProviderTest.php`
Expected: FAIL — `Class "...FakeLlmProvider" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai\Llm;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Data\LlmResult;

/**
 * Offline, deterministic LLM driver. Produces stable feature-shaped output so the
 * package works with zero configuration and CI never makes a live call.
 */
final class FakeLlmProvider implements LlmProviderInterface
{
    public function complete(string $instructions, string $prompt, array $options = []): LlmResult
    {
        $feature = (string) ($options['feature'] ?? 'general');

        return new LlmResult(
            text: "[fake:{$feature}] ".$this->digest($instructions.$prompt),
            model: 'fake',
        );
    }

    public function completeJson(string $instructions, string $prompt, array $options = []): LlmResult
    {
        $feature = (string) ($options['feature'] ?? 'general');
        $json = $this->payloadFor($feature, $instructions.$prompt);

        return new LlmResult(
            text: (string) json_encode($json),
            model: 'fake',
            json: $json,
        );
    }

    public function vision(string $instructions, string $prompt, array $imageUrls, array $options = []): LlmResult
    {
        $same = count($imageUrls) >= 2 && $imageUrls[0] === $imageUrls[1];
        $json = [
            'same_product' => $same,
            'confidence' => $same ? 95 : 20,
            'rationale' => $same ? 'identical image reference' : 'image references differ',
        ];

        return new LlmResult(text: (string) json_encode($json), model: 'fake', json: $json);
    }

    public function isFake(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(string $feature, string $seed): array
    {
        return match ($feature) {
            'narrative' => [
                'summary_md' => '## Weekly summary'.PHP_EOL.'No live model configured; deterministic placeholder summary.',
                'highlights' => [],
            ],
            'content_gap' => [
                'seo_score_delta' => 0,
                'missing_attributes' => [],
                'title_recommendations' => [],
                'description_recommendations' => [],
                'image_count_gap' => 0,
            ],
            'promo_detection' => [
                'has_promo' => false,
                'promo_type' => null,
                'valid_from' => null,
                'valid_to' => null,
                'conditions' => null,
                'effective_discount_pct' => null,
            ],
            'match_judge' => [
                'same_product' => false,
                'confidence' => 0,
                'rationale' => 'deterministic fake judge: no decision',
            ],
            default => ['text' => $this->digest($seed)],
        };
    }

    private function digest(string $seed): string
    {
        return substr(sha1($seed), 0, 12);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor\bin\phpunit tests/Feature/Llm/FakeLlmProviderTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```
git add src/Services/Ai/Llm/FakeLlmProvider.php tests/Feature/Llm/FakeLlmProviderTest.php
git commit -m "feat(b1): add deterministic FakeLlmProvider default driver"
```

---

## Task 5: `AgentRunner` seam + `AgentRunResult`

**Files:**
- Create: `src/Support/Llm/AgentRunner.php`
- Create: `src/Support/Llm/AgentRunResult.php`

- [ ] **Step 1: Write `AgentRunResult`**

```php
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
```

- [ ] **Step 2: Write the `AgentRunner` interface**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Llm;

/**
 * Isolates the laravel/ai call so LaravelAiLlmProvider can be unit-tested without
 * a live SDK call. The real implementation is LaravelAiAgentRunner.
 */
interface AgentRunner
{
    /**
     * @param  array<int, string>  $imageUrls
     */
    public function run(
        string $instructions,
        string $prompt,
        string $provider,
        string $model,
        int $timeout,
        array $imageUrls = [],
    ): AgentRunResult;
}
```

- [ ] **Step 3: Static check + commit**

Run: `vendor\bin\phpstan analyse src/Support/Llm --memory-limit=1G`
Expected: no errors.
```
git add src/Support/Llm/AgentRunner.php src/Support/Llm/AgentRunResult.php
git commit -m "feat(b1): add AgentRunner seam over laravel/ai"
```

---

## Task 6: `LaravelAiAgentRunner` (real `laravel/ai` runner)

**Files:**
- Create: `src/Support/Llm/LaravelAiAgentRunner.php`

> Not unit-tested in CI (it performs the real SDK call); covered by the opt-in live suite (Task 16). Keep it a thin adapter so there is no logic to test in isolation.

- [ ] **Step 1: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Llm;

use Laravel\Ai\Files;

use function Laravel\Ai\agent;

/**
 * Real laravel/ai-backed runner. The provider string maps to a config/ai.php provider
 * key (e.g. 'openai', 'anthropic', 'regolo'); model is the provider's model id.
 */
final class LaravelAiAgentRunner implements AgentRunner
{
    public function run(
        string $instructions,
        string $prompt,
        string $provider,
        string $model,
        int $timeout,
        array $imageUrls = [],
    ): AgentRunResult {
        $attachments = array_map(
            static fn (string $url) => Files\Image::fromUrl($url),
            $imageUrls,
        );

        $response = agent(instructions: $instructions)
            ->prompt(
                $prompt,
                provider: $provider,
                model: $model,
                timeout: $timeout,
                attachments: $attachments,
            );

        return new AgentRunResult(
            text: (string) $response,
            model: $model,
            promptTokens: $response->usage->promptTokens ?? null,
            completionTokens: $response->usage->completionTokens ?? null,
        );
    }
}
```

> If Task 1 Step 4 reported a different `agent()` callable/signature (e.g. provider expects a `Laravel\Ai\Enums\Lab` enum rather than a string), adapt this adapter accordingly and note it in `docs/LESSON.md`. This is the ONLY place the SDK's exact prompt signature is touched.

- [ ] **Step 2: Static check + commit**

Run: `vendor\bin\phpstan analyse src/Support/Llm/LaravelAiAgentRunner.php --memory-limit=1G`
Expected: no errors (if `laravel/ai` stubs trip PHPStan, add a narrowly-scoped ignore in `phpstan.neon` for this file and record it in `docs/LESSON.md`).
```
git add src/Support/Llm/LaravelAiAgentRunner.php
git commit -m "feat(b1): add LaravelAiAgentRunner adapter"
```

---

## Task 7: `LaravelAiLlmProvider` (SDK-backed driver, unit-tested via stub runner)

**Files:**
- Create: `src/Services/Ai/Llm/LaravelAiLlmProvider.php`
- Test: `tests/Feature/Llm/LaravelAiLlmProviderTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Llm;

use Padosoft\PriceIntelligence\Services\Ai\Llm\LaravelAiLlmProvider;
use Padosoft\PriceIntelligence\Support\Llm\AgentRunner;
use Padosoft\PriceIntelligence\Support\Llm\AgentRunResult;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class LaravelAiLlmProviderTest extends TestCase
{
    private function runnerReturning(string $text): AgentRunner
    {
        return new class($text) implements AgentRunner
        {
            /** @var array<string, mixed> */
            public array $lastCall = [];

            public function __construct(private readonly string $text) {}

            public function run(string $instructions, string $prompt, string $provider, string $model, int $timeout, array $imageUrls = []): AgentRunResult
            {
                $this->lastCall = compact('instructions', 'prompt', 'provider', 'model', 'timeout', 'imageUrls');

                return new AgentRunResult(text: $this->text, model: $model, promptTokens: 11, completionTokens: 7);
            }
        };
    }

    #[Test]
    public function complete_passes_config_defaults_and_maps_result(): void
    {
        config()->set('price-intelligence.ai.llm.provider', 'anthropic');
        config()->set('price-intelligence.ai.llm.model', 'claude-haiku-4-5');
        config()->set('price-intelligence.ai.llm.timeout', 90);

        $runner = $this->runnerReturning('hello from model');
        $provider = new LaravelAiLlmProvider($runner);

        $result = $provider->complete('be terse', 'say hi', ['feature' => 'narrative']);

        $this->assertFalse($provider->isFake());
        $this->assertSame('hello from model', $result->text);
        $this->assertSame('claude-haiku-4-5', $result->model);
        $this->assertSame(18, $result->totalTokens());
        $this->assertSame('anthropic', $runner->lastCall['provider']);
        $this->assertSame(90, $runner->lastCall['timeout']);
    }

    #[Test]
    public function per_call_options_override_config(): void
    {
        config()->set('price-intelligence.ai.llm.provider', 'openai');
        config()->set('price-intelligence.ai.llm.model', 'gpt-4o-mini');

        $runner = $this->runnerReturning('ok');
        $provider = new LaravelAiLlmProvider($runner);

        $provider->complete('s', 'p', ['provider' => 'regolo', 'model' => 'maestrale']);

        $this->assertSame('regolo', $runner->lastCall['provider']);
        $this->assertSame('maestrale', $runner->lastCall['model']);
    }

    #[Test]
    public function completeJson_decodes_strict_json(): void
    {
        $runner = $this->runnerReturning('{"has_promo": true, "effective_discount_pct": 15}');
        $provider = new LaravelAiLlmProvider($runner);

        $result = $provider->completeJson('s', 'p', ['feature' => 'promo_detection']);

        $this->assertSame(['has_promo' => true, 'effective_discount_pct' => 15], $result->json);
    }

    #[Test]
    public function completeJson_strips_markdown_fence_before_decoding(): void
    {
        $runner = $this->runnerReturning("```json\n{\"confidence\": 72}\n```");
        $provider = new LaravelAiLlmProvider($runner);

        $result = $provider->completeJson('s', 'p', ['feature' => 'match_judge']);

        $this->assertSame(['confidence' => 72], $result->json);
    }

    #[Test]
    public function completeJson_throws_on_undecodable_output(): void
    {
        $runner = $this->runnerReturning('I cannot help with that.');
        $provider = new LaravelAiLlmProvider($runner);

        $this->expectException(\RuntimeException::class);
        $provider->completeJson('s', 'p', ['feature' => 'promo_detection']);
    }

    #[Test]
    public function vision_forwards_image_urls(): void
    {
        $runner = $this->runnerReturning('{"same_product": true, "confidence": 88}');
        $provider = new LaravelAiLlmProvider($runner);

        $result = $provider->vision('judge', 'compare', ['https://x/a.jpg', 'https://x/b.jpg'], ['feature' => 'visual_match']);

        $this->assertSame(['same_product' => true, 'confidence' => 88], $result->json);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor\bin\phpunit tests/Feature/Llm/LaravelAiLlmProviderTest.php`
Expected: FAIL — `Class "...LaravelAiLlmProvider" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai\Llm;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Data\LlmResult;
use Padosoft\PriceIntelligence\Support\Llm\AgentRunner;
use RuntimeException;

final class LaravelAiLlmProvider implements LlmProviderInterface
{
    public function __construct(private readonly AgentRunner $runner) {}

    public function complete(string $instructions, string $prompt, array $options = []): LlmResult
    {
        return $this->toResult($this->dispatch($instructions, $prompt, $options));
    }

    public function completeJson(string $instructions, string $prompt, array $options = []): LlmResult
    {
        $jsonInstructions = trim($instructions."\n\nRespond ONLY with a single valid JSON object. No prose, no markdown.");
        $result = $this->toResult($this->dispatch($jsonInstructions, $prompt, $options));

        return new LlmResult(
            text: $result->text,
            model: $result->model,
            promptTokens: $result->promptTokens,
            completionTokens: $result->completionTokens,
            json: $this->decode($result->text),
        );
    }

    public function vision(string $instructions, string $prompt, array $imageUrls, array $options = []): LlmResult
    {
        $run = $this->runner->run(
            $instructions,
            $prompt,
            $this->provider($options),
            $this->model($options),
            $this->timeout($options),
            $imageUrls,
        );

        return new LlmResult(
            text: $run->text,
            model: $run->model,
            promptTokens: $run->promptTokens,
            completionTokens: $run->completionTokens,
            json: $this->decode($run->text),
        );
    }

    public function isFake(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function dispatch(string $instructions, string $prompt, array $options): \Padosoft\PriceIntelligence\Support\Llm\AgentRunResult
    {
        return $this->runner->run(
            $instructions,
            $prompt,
            $this->provider($options),
            $this->model($options),
            $this->timeout($options),
        );
    }

    private function toResult(\Padosoft\PriceIntelligence\Support\Llm\AgentRunResult $run): LlmResult
    {
        return new LlmResult(
            text: $run->text,
            model: $run->model,
            promptTokens: $run->promptTokens,
            completionTokens: $run->completionTokens,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function provider(array $options): string
    {
        return (string) ($options['provider'] ?? config('price-intelligence.ai.llm.provider', 'openai'));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function model(array $options): string
    {
        return (string) ($options['model'] ?? config('price-intelligence.ai.llm.model', 'gpt-4o-mini'));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function timeout(array $options): int
    {
        return (int) ($options['timeout'] ?? config('price-intelligence.ai.llm.timeout', 120));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $text): array
    {
        $clean = trim($text);
        // Strip a ```json ... ``` (or plain ```) fence if the model wrapped its output.
        if (str_starts_with($clean, '```')) {
            $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $clean) ?? $clean;
            $clean = trim($clean);
        }

        $decoded = json_decode($clean, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('LLM did not return decodable JSON: '.substr($text, 0, 200));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor\bin\phpunit tests/Feature/Llm/LaravelAiLlmProviderTest.php`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```
git add src/Services/Ai/Llm/LaravelAiLlmProvider.php tests/Feature/Llm/LaravelAiLlmProviderTest.php
git commit -m "feat(b1): add laravel/ai-backed LlmProvider with JSON + vision"
```

---

## Task 8: Config — `ai.llm` block + embeddings extension

**Files:**
- Modify: `config/price-intelligence.php`

- [ ] **Step 1: Add the `llm` block to the `ai` array**

In `config/price-intelligence.php`, change the `'ai' => [ ... ]` array to start with a new `llm` block (place it as the first key inside `'ai'`):
```php
'ai' => [
    'llm' => [
        // 'fake' (default, offline deterministic) | 'laravel-ai' (uses the laravel/ai SDK)
        'driver' => env('PI_LLM_DRIVER', 'fake'),
        // config/ai.php provider key: openai | anthropic | gemini | regolo | ...
        'provider' => env('PI_LLM_PROVIDER', 'openai'),
        'model' => env('PI_LLM_MODEL', 'gpt-4o-mini'),
        'vision_model' => env('PI_LLM_VISION_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('PI_LLM_TIMEOUT', 120),
    ],
    'visual_match' => ['enabled' => true],
    'content_gap' => ['enabled' => true],
    'forecast' => ['enabled' => true, 'min_observations' => 14, 'show_confidence_interval' => true],
    'anomaly' => ['enabled' => true],
    'narrative' => ['enabled' => true, 'driver' => 'fake'],
    'promo_detection' => ['enabled' => true, 'driver' => 'fake'],
    'assortment' => ['enabled' => true],
],
```

- [ ] **Step 2: Extend the `matching.embeddings` block**

Change the existing `'embeddings' => ['driver' => 'fake', 'cache_ttl' => 2592000],` line under `'matching'` to:
```php
'embeddings' => [
    // 'fake' (default) | 'laravel-ai'
    'driver' => env('PI_EMBEDDINGS_DRIVER', 'fake'),
    'provider' => env('PI_EMBEDDINGS_PROVIDER', 'openai'),
    'model' => env('PI_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
    'dimensions' => (int) env('PI_EMBEDDINGS_DIMENSIONS', 1536),
    'cache_ttl' => 2592000,
],
```

- [ ] **Step 3: Verify config parses**

Run: `php -r "var_dump(require 'config/price-intelligence.php');" > $null; echo OK`
Expected: prints `OK` (no parse error).

- [ ] **Step 4: Commit**

```
git add config/price-intelligence.php
git commit -m "feat(b1): add ai.llm config block + embeddings driver/model keys"
```

---

## Task 9: `EmbeddingRunner` seam + `LaravelAiEmbeddingRunner` + `LaravelAiEmbeddingProvider`

**Files:**
- Create: `src/Support/Llm/EmbeddingRunner.php`
- Create: `src/Support/Llm/LaravelAiEmbeddingRunner.php`
- Create: `src/Services/Matching/Embeddings/LaravelAiEmbeddingProvider.php`
- Test: `tests/Feature/Embeddings/EmbeddingDriverTest.php`

- [ ] **Step 1: Write the `EmbeddingRunner` interface**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Llm;

interface EmbeddingRunner
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text, string $provider, string $model, int $dimensions): array;
}
```

- [ ] **Step 2: Write the real runner**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Llm;

use Laravel\Ai\Embeddings;

final class LaravelAiEmbeddingRunner implements EmbeddingRunner
{
    public function embed(string $text, string $provider, string $model, int $dimensions): array
    {
        $response = Embeddings::for([$text])
            ->dimensions($dimensions)
            ->generate($provider, $model);

        /** @var array<int, array<int, float>> $vectors */
        $vectors = $response->embeddings;

        return $vectors[0] ?? [];
    }
}
```

> If Task 1 Step 4 revealed that `generate()` expects a `Lab` enum rather than a provider string, adapt here only and record it in `docs/LESSON.md`.

- [ ] **Step 3: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Embeddings;

use Padosoft\PriceIntelligence\Contracts\EmbeddingProviderInterface;
use Padosoft\PriceIntelligence\Services\Matching\Embeddings\FakeEmbeddingProvider;
use Padosoft\PriceIntelligence\Services\Matching\Embeddings\LaravelAiEmbeddingProvider;
use Padosoft\PriceIntelligence\Support\Llm\EmbeddingRunner;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class EmbeddingDriverTest extends TestCase
{
    #[Test]
    public function fake_driver_is_bound_by_default(): void
    {
        $this->assertInstanceOf(FakeEmbeddingProvider::class, app(EmbeddingProviderInterface::class));
    }

    #[Test]
    public function laravel_ai_driver_is_bound_when_configured(): void
    {
        config()->set('price-intelligence.matching.embeddings.driver', 'laravel-ai');

        $this->assertInstanceOf(LaravelAiEmbeddingProvider::class, app(EmbeddingProviderInterface::class));
    }

    #[Test]
    public function laravel_ai_provider_forwards_config_to_runner(): void
    {
        config()->set('price-intelligence.matching.embeddings.provider', 'regolo');
        config()->set('price-intelligence.matching.embeddings.model', 'bge-m3');
        config()->set('price-intelligence.matching.embeddings.dimensions', 8);

        $runner = new class implements EmbeddingRunner
        {
            /** @var array<string, mixed> */
            public array $call = [];

            public function embed(string $text, string $provider, string $model, int $dimensions): array
            {
                $this->call = compact('text', 'provider', 'model', 'dimensions');

                return array_fill(0, $dimensions, 0.5);
            }
        };

        $provider = new LaravelAiEmbeddingProvider($runner, 'regolo', 'bge-m3', 8);
        $vector = $provider->embed('blue shirt');

        $this->assertCount(8, $vector);
        $this->assertSame('regolo', $runner->call['provider']);
        $this->assertSame('bge-m3', $runner->call['model']);
        $this->assertSame(8, $runner->call['dimensions']);
    }
}
```

- [ ] **Step 4: Run test to verify it fails**

Run: `vendor\bin\phpunit tests/Feature/Embeddings/EmbeddingDriverTest.php`
Expected: FAIL — `LaravelAiEmbeddingProvider` not found (and the binding tests fail until Task 10).

- [ ] **Step 5: Write `LaravelAiEmbeddingProvider`**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Matching\Embeddings;

use Padosoft\PriceIntelligence\Contracts\EmbeddingProviderInterface;
use Padosoft\PriceIntelligence\Support\Llm\EmbeddingRunner;

final class LaravelAiEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private readonly EmbeddingRunner $runner,
        private readonly string $provider,
        private readonly string $model,
        private readonly int $dimensions,
    ) {}

    public function embed(string $text): array
    {
        return $this->runner->embed($text, $this->provider, $this->model, $this->dimensions);
    }
}
```

- [ ] **Step 6: Run the third test only (binding tests come in Task 10)**

Run: `vendor\bin\phpunit tests/Feature/Embeddings/EmbeddingDriverTest.php --filter laravel_ai_provider_forwards_config_to_runner`
Expected: PASS. (The two binding tests stay red until Task 10 — that's expected.)

- [ ] **Step 7: Commit**

```
git add src/Support/Llm/EmbeddingRunner.php src/Support/Llm/LaravelAiEmbeddingRunner.php src/Services/Matching/Embeddings/LaravelAiEmbeddingProvider.php tests/Feature/Embeddings/EmbeddingDriverTest.php
git commit -m "feat(b1): add laravel/ai embedding runner + provider"
```

---

## Task 10: ServiceProvider bindings (LLM + embeddings driver selection)

**Files:**
- Modify: `src/PriceIntelligenceServiceProvider.php`
- Test: `tests/Feature/Llm/LlmBindingTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Llm;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Services\Ai\Llm\FakeLlmProvider;
use Padosoft\PriceIntelligence\Services\Ai\Llm\LaravelAiLlmProvider;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class LlmBindingTest extends TestCase
{
    #[Test]
    public function fake_llm_is_bound_by_default(): void
    {
        $this->assertInstanceOf(FakeLlmProvider::class, app(LlmProviderInterface::class));
    }

    #[Test]
    public function laravel_ai_llm_is_bound_when_configured(): void
    {
        config()->set('price-intelligence.ai.llm.driver', 'laravel-ai');

        $this->assertInstanceOf(LaravelAiLlmProvider::class, app(LlmProviderInterface::class));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor\bin\phpunit tests/Feature/Llm/LlmBindingTest.php`
Expected: FAIL — `LlmProviderInterface` is not bound.

- [ ] **Step 3: Add the bindings**

In `src/PriceIntelligenceServiceProvider.php`, add the imports at the top (with the other `use` statements):
```php
use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Services\Ai\Llm\FakeLlmProvider;
use Padosoft\PriceIntelligence\Services\Ai\Llm\LaravelAiLlmProvider;
use Padosoft\PriceIntelligence\Services\Matching\Embeddings\LaravelAiEmbeddingProvider;
use Padosoft\PriceIntelligence\Support\Llm\AgentRunner;
use Padosoft\PriceIntelligence\Support\Llm\EmbeddingRunner;
use Padosoft\PriceIntelligence\Support\Llm\LaravelAiAgentRunner;
use Padosoft\PriceIntelligence\Support\Llm\LaravelAiEmbeddingRunner;
```

Replace the existing `EmbeddingProviderInterface` binding:
```php
    $this->app->bind(EmbeddingProviderInterface::class, static function (): EmbeddingProviderInterface {
        // Default offline-safe driver; host apps rebind to OpenAI/Voyage/etc.
        return new FakeEmbeddingProvider;
    });
```
with the driver-aware version:
```php
    $this->app->bind(AgentRunner::class, static fn (): AgentRunner => new LaravelAiAgentRunner);
    $this->app->bind(EmbeddingRunner::class, static fn (): EmbeddingRunner => new LaravelAiEmbeddingRunner);

    $this->app->bind(LlmProviderInterface::class, static function ($app): LlmProviderInterface {
        return config('price-intelligence.ai.llm.driver', 'fake') === 'laravel-ai'
            ? new LaravelAiLlmProvider($app->make(AgentRunner::class))
            : new FakeLlmProvider;
    });

    $this->app->bind(EmbeddingProviderInterface::class, static function ($app): EmbeddingProviderInterface {
        // Default offline-safe driver; switch to laravel/ai via config or rebind in the host.
        if (config('price-intelligence.matching.embeddings.driver', 'fake') === 'laravel-ai') {
            return new LaravelAiEmbeddingProvider(
                $app->make(EmbeddingRunner::class),
                (string) config('price-intelligence.matching.embeddings.provider', 'openai'),
                (string) config('price-intelligence.matching.embeddings.model', 'text-embedding-3-small'),
                (int) config('price-intelligence.matching.embeddings.dimensions', 1536),
            );
        }

        return new FakeEmbeddingProvider;
    });
```

- [ ] **Step 4: Run both new binding suites**

Run: `vendor\bin\phpunit tests/Feature/Llm/LlmBindingTest.php tests/Feature/Embeddings/EmbeddingDriverTest.php`
Expected: PASS (5 tests total).

- [ ] **Step 5: Commit**

```
git add src/PriceIntelligenceServiceProvider.php tests/Feature/Llm/LlmBindingTest.php
git commit -m "feat(b1): bind LLM + embedding drivers by config"
```

---

## Task 11: `NarrativeWriter` (interface + DTO + service)

**Files:**
- Create: `src/Contracts/NarrativeWriterInterface.php`
- Create: `src/Data/NarrativeResult.php`
- Create: `src/Services/Ai/NarrativeWriter.php`
- Test: `tests/Feature/Ai/NarrativeWriterTest.php`

- [ ] **Step 1: Write the DTO + contract**

`src/Data/NarrativeResult.php`:
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

final class NarrativeResult
{
    /**
     * @param  array<int, mixed>  $highlights
     */
    public function __construct(
        public readonly string $summaryMd,
        public readonly array $highlights,
        public readonly string $model,
    ) {}
}
```

`src/Contracts/NarrativeWriterInterface.php`:
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Data\NarrativeResult;

interface NarrativeWriterInterface
{
    /**
     * @param  array<string, mixed>  $context  aggregated weekly signals (top movers, promos, anomalies)
     */
    public function write(int|string $tenantId, string $period, array $context): NarrativeResult;
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Ai;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Contracts\NarrativeWriterInterface;
use Padosoft\PriceIntelligence\Models\AiDecisionLog;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Ai\NarrativeWriter;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class NarrativeWriterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_bound(): void
    {
        $this->assertInstanceOf(NarrativeWriter::class, app(NarrativeWriterInterface::class));
    }

    #[Test]
    public function it_writes_a_narrative_and_logs_the_decision(): void
    {
        config()->set('price-intelligence.ai_act.decision_log.enabled', true);
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $result = app(NarrativeWriterInterface::class)->write($tenant->id, '2026-W21', [
            'top_movers' => [['name' => 'Widget', 'delta_pct' => -12]],
        ]);

        $this->assertNotSame('', $result->summaryMd);
        $this->assertSame('fake', $result->model);
        $this->assertSame(1, AiDecisionLog::query()->where('feature', 'narrative')->count());
    }
}
```

- [ ] **Step 3: Run to verify it fails**

Run: `vendor\bin\phpunit tests/Feature/Ai/NarrativeWriterTest.php`
Expected: FAIL — `NarrativeWriter` not found / not bound.

- [ ] **Step 4: Write the service**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Contracts\NarrativeWriterInterface;
use Padosoft\PriceIntelligence\Data\NarrativeResult;

final class NarrativeWriter implements NarrativeWriterInterface
{
    public function __construct(
        private readonly LlmProviderInterface $llm,
        private readonly AiDecisionLogger $logger,
    ) {}

    public function write(int|string $tenantId, string $period, array $context): NarrativeResult
    {
        $prompt = "Period: {$period}\nSignals (JSON):\n".(string) json_encode($context);

        $result = $this->llm->completeJson(
            'You are a retail price-intelligence analyst. Summarise the week for a merchandiser. '
            .'Return JSON: {"summary_md": string (markdown), "highlights": array of short strings}.',
            $prompt,
            ['feature' => 'narrative'],
        );

        $json = $result->json ?? [];
        $summaryMd = is_string($json['summary_md'] ?? null) ? $json['summary_md'] : '';
        /** @var array<int, mixed> $highlights */
        $highlights = is_array($json['highlights'] ?? null) ? $json['highlights'] : [];

        $this->logger->record(
            tenantId: $tenantId,
            feature: 'narrative',
            output: ['period' => $period, 'summary_md' => $summaryMd, 'highlights' => $highlights],
            model: $result->model,
        );

        return new NarrativeResult($summaryMd, $highlights, $result->model);
    }
}
```

- [ ] **Step 5: Bind it in the ServiceProvider**

In `src/PriceIntelligenceServiceProvider.php` add the import:
```php
use Padosoft\PriceIntelligence\Contracts\NarrativeWriterInterface;
use Padosoft\PriceIntelligence\Services\Ai\NarrativeWriter;
```
and add the binding (after the `LlmProviderInterface` binding):
```php
    $this->app->bind(NarrativeWriterInterface::class, static fn ($app): NarrativeWriterInterface => new NarrativeWriter(
        $app->make(LlmProviderInterface::class),
        $app->make(\Padosoft\PriceIntelligence\Services\Ai\AiDecisionLogger::class),
    ));
```

- [ ] **Step 6: Run to verify it passes**

Run: `vendor\bin\phpunit tests/Feature/Ai/NarrativeWriterTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```
git add src/Contracts/NarrativeWriterInterface.php src/Data/NarrativeResult.php src/Services/Ai/NarrativeWriter.php src/PriceIntelligenceServiceProvider.php tests/Feature/Ai/NarrativeWriterTest.php
git commit -m "feat(b1): add LLM-backed NarrativeWriter + decision log"
```

---

## Task 12: `ContentGapAnalyzer` (interface + DTO + service)

**Files:**
- Create: `src/Contracts/ContentGapAnalyzerInterface.php`
- Create: `src/Data/ContentGapResult.php`
- Create: `src/Services/Ai/ContentGapAnalyzer.php`
- Test: `tests/Feature/Ai/ContentGapAnalyzerTest.php`

- [ ] **Step 1: Write the DTO + contract**

`src/Data/ContentGapResult.php`:
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

final class ContentGapResult
{
    /**
     * @param  array<int, string>  $missingAttributes
     * @param  array<int, string>  $titleRecommendations
     * @param  array<int, string>  $descriptionRecommendations
     */
    public function __construct(
        public readonly int $seoScoreDelta,
        public readonly array $missingAttributes,
        public readonly array $titleRecommendations,
        public readonly array $descriptionRecommendations,
        public readonly int $imageCountGap,
        public readonly string $model,
    ) {}
}
```

`src/Contracts/ContentGapAnalyzerInterface.php`:
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Data\ContentGapResult;
use Padosoft\PriceIntelligence\Models\Product;

interface ContentGapAnalyzerInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $competitorSnapshots
     */
    public function analyze(Product $product, array $competitorSnapshots): ContentGapResult;
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Ai;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Contracts\ContentGapAnalyzerInterface;
use Padosoft\PriceIntelligence\Models\AiDecisionLog;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Ai\ContentGapAnalyzer;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ContentGapAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_bound(): void
    {
        $this->assertInstanceOf(ContentGapAnalyzer::class, app(ContentGapAnalyzerInterface::class));
    }

    #[Test]
    public function it_returns_a_result_and_logs_the_decision(): void
    {
        config()->set('price-intelligence.ai_act.decision_log.enabled', true);
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'external_id' => 'sku-1',
            'name' => 'Widget',
            'currency' => 'EUR',
            'base_country' => 'IT',
        ]);

        $result = app(ContentGapAnalyzerInterface::class)->analyze($product, [
            ['title' => 'Widget Pro', 'attributes' => ['color' => 'blue']],
        ]);

        $this->assertSame('fake', $result->model);
        $this->assertIsArray($result->missingAttributes);
        $this->assertSame(1, AiDecisionLog::query()->where('feature', 'content_gap')->count());
    }
}
```

> Confirm the `Product` fillable columns against `src/Models/Product.php` before running; adjust the `create([...])` keys to the minimal non-nullable set if migration differs. (`external_id`, `name`, `currency`, `base_country`, `tenant_id` are the expected required fields per the data model.)

- [ ] **Step 3: Run to verify it fails**

Run: `vendor\bin\phpunit tests/Feature/Ai/ContentGapAnalyzerTest.php`
Expected: FAIL — class not found / not bound.

- [ ] **Step 4: Write the service**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\ContentGapAnalyzerInterface;
use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Data\ContentGapResult;
use Padosoft\PriceIntelligence\Models\Product;

final class ContentGapAnalyzer implements ContentGapAnalyzerInterface
{
    public function __construct(
        private readonly LlmProviderInterface $llm,
        private readonly AiDecisionLogger $logger,
    ) {}

    public function analyze(Product $product, array $competitorSnapshots): ContentGapResult
    {
        $payload = [
            'our_product' => [
                'name' => $product->name,
                'brand' => $product->brand,
                'attributes' => $product->attributes,
            ],
            'competitors' => $competitorSnapshots,
        ];

        $result = $this->llm->completeJson(
            'You are an ecommerce SEO/merchandising analyst. Compare our product to competitors. '
            .'Return JSON: {"seo_score_delta": int, "missing_attributes": string[], '
            .'"title_recommendations": string[], "description_recommendations": string[], "image_count_gap": int}.',
            (string) json_encode($payload),
            ['feature' => 'content_gap'],
        );

        $json = $result->json ?? [];
        $gap = new ContentGapResult(
            seoScoreDelta: (int) ($json['seo_score_delta'] ?? 0),
            missingAttributes: $this->strings($json['missing_attributes'] ?? []),
            titleRecommendations: $this->strings($json['title_recommendations'] ?? []),
            descriptionRecommendations: $this->strings($json['description_recommendations'] ?? []),
            imageCountGap: (int) ($json['image_count_gap'] ?? 0),
            model: $result->model,
        );

        $this->logger->record(
            tenantId: $product->tenant_id,
            feature: 'content_gap',
            output: $json,
            model: $result->model,
            subjectType: 'Product',
            subjectId: (int) $product->id,
        );

        return $gap;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn ($v): string => (string) $v, $value));
    }
}
```

- [ ] **Step 5: Bind it in the ServiceProvider**

Add the import + binding (mirror Task 11):
```php
use Padosoft\PriceIntelligence\Contracts\ContentGapAnalyzerInterface;
use Padosoft\PriceIntelligence\Services\Ai\ContentGapAnalyzer;
```
```php
    $this->app->bind(ContentGapAnalyzerInterface::class, static fn ($app): ContentGapAnalyzerInterface => new ContentGapAnalyzer(
        $app->make(LlmProviderInterface::class),
        $app->make(\Padosoft\PriceIntelligence\Services\Ai\AiDecisionLogger::class),
    ));
```

- [ ] **Step 6: Run to verify it passes**

Run: `vendor\bin\phpunit tests/Feature/Ai/ContentGapAnalyzerTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```
git add src/Contracts/ContentGapAnalyzerInterface.php src/Data/ContentGapResult.php src/Services/Ai/ContentGapAnalyzer.php src/PriceIntelligenceServiceProvider.php tests/Feature/Ai/ContentGapAnalyzerTest.php
git commit -m "feat(b1): add LLM-backed ContentGapAnalyzer + decision log"
```

---

## Task 13: `PromoDetector` (interface + DTO + service)

**Files:**
- Create: `src/Contracts/PromoDetectorInterface.php`
- Create: `src/Data/PromoResult.php`
- Create: `src/Services/Ai/PromoDetector.php`
- Test: `tests/Feature/Ai/PromoDetectorTest.php`

- [ ] **Step 1: Write the DTO + contract**

`src/Data/PromoResult.php`:
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

final class PromoResult
{
    public function __construct(
        public readonly bool $hasPromo,
        public readonly ?string $promoType,
        public readonly ?string $validFrom,
        public readonly ?string $validTo,
        public readonly ?string $conditions,
        public readonly ?float $effectiveDiscountPct,
        public readonly string $model,
    ) {}
}
```

`src/Contracts/PromoDetectorInterface.php`:
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Data\PromoResult;

interface PromoDetectorInterface
{
    public function detect(int|string $tenantId, string $pageText, ?int $listPriceCents = null): PromoResult;
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Ai;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Contracts\PromoDetectorInterface;
use Padosoft\PriceIntelligence\Models\AiDecisionLog;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Ai\PromoDetector;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PromoDetectorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_bound(): void
    {
        $this->assertInstanceOf(PromoDetector::class, app(PromoDetectorInterface::class));
    }

    #[Test]
    public function it_returns_a_promo_result_and_logs(): void
    {
        config()->set('price-intelligence.ai_act.decision_log.enabled', true);
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $result = app(PromoDetectorInterface::class)->detect($tenant->id, 'Save 20% this week only', 10000);

        $this->assertSame('fake', $result->model);
        $this->assertIsBool($result->hasPromo);
        $this->assertSame(1, AiDecisionLog::query()->where('feature', 'promo_detection')->count());
    }
}
```

- [ ] **Step 3: Run to verify it fails**

Run: `vendor\bin\phpunit tests/Feature/Ai/PromoDetectorTest.php`
Expected: FAIL — class not found / not bound.

- [ ] **Step 4: Write the service**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Contracts\PromoDetectorInterface;
use Padosoft\PriceIntelligence\Data\PromoResult;

final class PromoDetector implements PromoDetectorInterface
{
    public function __construct(
        private readonly LlmProviderInterface $llm,
        private readonly AiDecisionLogger $logger,
    ) {}

    public function detect(int|string $tenantId, string $pageText, ?int $listPriceCents = null): PromoResult
    {
        $prompt = "List price (cents): ".($listPriceCents ?? 'unknown')."\n\nPage text:\n".$pageText;

        $result = $this->llm->completeJson(
            'You detect retail promotions in product page text. Return JSON: '
            .'{"has_promo": bool, "promo_type": "sale|coupon|bundle|loyalty|clearance"|null, '
            .'"valid_from": ISO-date|null, "valid_to": ISO-date|null, "conditions": string|null, '
            .'"effective_discount_pct": number|null}.',
            $prompt,
            ['feature' => 'promo_detection'],
        );

        $json = $result->json ?? [];
        $promo = new PromoResult(
            hasPromo: (bool) ($json['has_promo'] ?? false),
            promoType: $this->nullableString($json['promo_type'] ?? null),
            validFrom: $this->nullableString($json['valid_from'] ?? null),
            validTo: $this->nullableString($json['valid_to'] ?? null),
            conditions: $this->nullableString($json['conditions'] ?? null),
            effectiveDiscountPct: isset($json['effective_discount_pct']) && is_numeric($json['effective_discount_pct'])
                ? (float) $json['effective_discount_pct']
                : null,
            model: $result->model,
        );

        $this->logger->record(
            tenantId: $tenantId,
            feature: 'promo_detection',
            output: $json,
            model: $result->model,
        );

        return $promo;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
```

- [ ] **Step 5: Bind it in the ServiceProvider**

```php
use Padosoft\PriceIntelligence\Contracts\PromoDetectorInterface;
use Padosoft\PriceIntelligence\Services\Ai\PromoDetector;
```
```php
    $this->app->bind(PromoDetectorInterface::class, static fn ($app): PromoDetectorInterface => new PromoDetector(
        $app->make(LlmProviderInterface::class),
        $app->make(\Padosoft\PriceIntelligence\Services\Ai\AiDecisionLogger::class),
    ));
```

- [ ] **Step 6: Run to verify it passes**

Run: `vendor\bin\phpunit tests/Feature/Ai/PromoDetectorTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```
git add src/Contracts/PromoDetectorInterface.php src/Data/PromoResult.php src/Services/Ai/PromoDetector.php src/PriceIntelligenceServiceProvider.php tests/Feature/Ai/PromoDetectorTest.php
git commit -m "feat(b1): add LLM-backed PromoDetector + decision log"
```

---

## Task 14: `VisualMatcher` (interface + DTO + service)

**Files:**
- Create: `src/Contracts/VisualMatcherInterface.php`
- Create: `src/Data/VisualMatchResult.php`
- Create: `src/Services/Ai/VisualMatcher.php`
- Test: `tests/Feature/Ai/VisualMatcherTest.php`

- [ ] **Step 1: Write the DTO + contract**

`src/Data/VisualMatchResult.php`:
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Data;

final class VisualMatchResult
{
    public function __construct(
        public readonly bool $sameProduct,
        public readonly int $confidence,
        public readonly string $rationale,
        public readonly string $model,
    ) {}
}
```

`src/Contracts/VisualMatcherInterface.php`:
```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

use Padosoft\PriceIntelligence\Data\VisualMatchResult;

interface VisualMatcherInterface
{
    public function isSameProduct(int|string $tenantId, string $imageUrlA, string $imageUrlB): VisualMatchResult;
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Ai;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\PriceIntelligence\Contracts\VisualMatcherInterface;
use Padosoft\PriceIntelligence\Models\AiDecisionLog;
use Padosoft\PriceIntelligence\Models\Tenant;
use Padosoft\PriceIntelligence\Services\Ai\VisualMatcher;
use Padosoft\PriceIntelligence\Support\Tenant\TenantContext;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class VisualMatcherTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_bound(): void
    {
        $this->assertInstanceOf(VisualMatcher::class, app(VisualMatcherInterface::class));
    }

    #[Test]
    public function identical_image_refs_are_same_product_and_logged(): void
    {
        config()->set('price-intelligence.ai_act.decision_log.enabled', true);
        $tenant = Tenant::create(['code' => 't1', 'name' => 't1']);
        app(TenantContext::class)->set($tenant->id);

        $url = 'https://cdn.example/x.jpg';
        $result = app(VisualMatcherInterface::class)->isSameProduct($tenant->id, $url, $url);

        $this->assertTrue($result->sameProduct);
        $this->assertSame('fake', $result->model);
        $this->assertSame(1, AiDecisionLog::query()->where('feature', 'visual_match')->count());
    }
}
```

- [ ] **Step 3: Run to verify it fails**

Run: `vendor\bin\phpunit tests/Feature/Ai/VisualMatcherTest.php`
Expected: FAIL — class not found / not bound.

- [ ] **Step 4: Write the service**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Ai;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Contracts\VisualMatcherInterface;
use Padosoft\PriceIntelligence\Data\VisualMatchResult;

final class VisualMatcher implements VisualMatcherInterface
{
    public function __construct(
        private readonly LlmProviderInterface $llm,
        private readonly AiDecisionLogger $logger,
    ) {}

    public function isSameProduct(int|string $tenantId, string $imageUrlA, string $imageUrlB): VisualMatchResult
    {
        $result = $this->llm->vision(
            'You compare two product photos. Return JSON: '
            .'{"same_product": bool, "confidence": int 0-100, "rationale": string}.',
            'Are these the same product? Consider model, colour, and variant.',
            [$imageUrlA, $imageUrlB],
            ['feature' => 'visual_match', 'model' => config('price-intelligence.ai.llm.vision_model')],
        );

        $json = $result->json ?? [];
        $match = new VisualMatchResult(
            sameProduct: (bool) ($json['same_product'] ?? false),
            confidence: (int) ($json['confidence'] ?? 0),
            rationale: is_string($json['rationale'] ?? null) ? $json['rationale'] : '',
            model: $result->model,
        );

        $this->logger->record(
            tenantId: $tenantId,
            feature: 'visual_match',
            output: $json,
            model: $result->model,
            confidence: $match->confidence,
        );

        return $match;
    }
}
```

- [ ] **Step 5: Bind it in the ServiceProvider**

```php
use Padosoft\PriceIntelligence\Contracts\VisualMatcherInterface;
use Padosoft\PriceIntelligence\Services\Ai\VisualMatcher;
```
```php
    $this->app->bind(VisualMatcherInterface::class, static fn ($app): VisualMatcherInterface => new VisualMatcher(
        $app->make(LlmProviderInterface::class),
        $app->make(\Padosoft\PriceIntelligence\Services\Ai\AiDecisionLogger::class),
    ));
```

- [ ] **Step 6: Run to verify it passes**

Run: `vendor\bin\phpunit tests/Feature/Ai/VisualMatcherTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```
git add src/Contracts/VisualMatcherInterface.php src/Data/VisualMatchResult.php src/Services/Ai/VisualMatcher.php src/PriceIntelligenceServiceProvider.php tests/Feature/Ai/VisualMatcherTest.php
git commit -m "feat(b1): add vision-LLM VisualMatcher + decision log"
```

---

## Task 15: `LlmJudgeMatcher` matching step + borderline gating

**Files:**
- Create: `src/Contracts/BorderlineOnlyStep.php`
- Create: `src/Services/Matching/Steps/LlmJudgeMatcher.php`
- Modify: `src/Services/Matching/MatchingPipeline.php`
- Modify: `src/Services/Matching/MatchingPipelineFactory.php`
- Test: `tests/Feature/Matching/LlmJudgeMatcherTest.php`

- [ ] **Step 1: Write the marker interface**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Contracts;

/**
 * Marks an expensive MatchStep (e.g. an LLM judge) that the pipeline runs ONLY when the
 * best score so far is uncertain (within [judgeFloor, high) of the confidence band).
 */
interface BorderlineOnlyStep {}
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Matching;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Data\LlmResult;
use Padosoft\PriceIntelligence\Data\MatchScore;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\MatchMethod;
use Padosoft\PriceIntelligence\Models\Product;
use Padosoft\PriceIntelligence\Services\Matching\MatchingPipeline;
use Padosoft\PriceIntelligence\Services\Matching\Steps\LlmJudgeMatcher;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class LlmJudgeMatcherTest extends TestCase
{
    private function llmReturning(int $confidence, bool $same): LlmProviderInterface
    {
        return new class($confidence, $same) implements LlmProviderInterface
        {
            public int $calls = 0;

            public function __construct(private readonly int $confidence, private readonly bool $same) {}

            public function complete(string $i, string $p, array $o = []): LlmResult
            {
                return new LlmResult('', 'fake');
            }

            public function completeJson(string $i, string $p, array $o = []): LlmResult
            {
                $this->calls++;
                $json = ['same_product' => $this->same, 'confidence' => $this->confidence, 'rationale' => 'x'];

                return new LlmResult((string) json_encode($json), 'fake', json: $json);
            }

            public function vision(string $i, string $p, array $u, array $o = []): LlmResult
            {
                return new LlmResult('', 'fake');
            }

            public function isFake(): bool
            {
                return true;
            }
        };
    }

    #[Test]
    public function judge_scores_a_borderline_candidate(): void
    {
        $llm = $this->llmReturning(78, true);
        $judge = new LlmJudgeMatcher($llm);

        $product = new Product(['name' => 'Widget', 'brand' => 'Acme']);
        $candidate = new ProductSnapshot(title: 'Acme Widget');

        $score = $judge->score($product, $candidate);

        $this->assertSame(78, $score->confidence);
        $this->assertSame(MatchMethod::Llm, $score->method);
    }

    #[Test]
    public function pipeline_skips_judge_when_best_is_already_confident(): void
    {
        $llm = $this->llmReturning(78, true);
        $judge = new LlmJudgeMatcher($llm);

        // A cheap step that already returns a confident 92.
        $confident = new class implements \Padosoft\PriceIntelligence\Contracts\MatchStepInterface
        {
            public function applicable(Product $p, ProductSnapshot $c): bool
            {
                return true;
            }

            public function score(Product $p, ProductSnapshot $c): MatchScore
            {
                return new MatchScore(92, MatchMethod::Mpn, []);
            }
        };

        $pipeline = new MatchingPipeline([$confident, $judge], [60, 85]);
        $outcome = $pipeline->match(new Product(['name' => 'Widget']), new ProductSnapshot(title: 'Widget'));

        $this->assertSame(0, $llm->calls, 'judge must not run when best >= high');
        $this->assertSame(92, $outcome->confidence);
    }

    #[Test]
    public function pipeline_runs_judge_when_best_is_uncertain(): void
    {
        $llm = $this->llmReturning(80, true);
        $judge = new LlmJudgeMatcher($llm);

        $weak = new class implements \Padosoft\PriceIntelligence\Contracts\MatchStepInterface
        {
            public function applicable(Product $p, ProductSnapshot $c): bool
            {
                return true;
            }

            public function score(Product $p, ProductSnapshot $c): MatchScore
            {
                return new MatchScore(64, MatchMethod::NormalizedName, []);
            }
        };

        $pipeline = new MatchingPipeline([$weak, $judge], [60, 85]);
        $outcome = $pipeline->match(new Product(['name' => 'Widget']), new ProductSnapshot(title: 'Widget'));

        $this->assertSame(1, $llm->calls, 'judge must run when 60 <= best < 85');
        $this->assertSame(80, $outcome->confidence);
    }
}
```

> Verify the `ProductSnapshot` constructor signature (`src/Data/ProductSnapshot.php`) before running — adjust the `new ProductSnapshot(title: ...)` calls to its actual named params. `MatchMethod::Llm` must exist in `src/Enums/MatchMethod.php`; if the case is named differently (e.g. `LlmJudge`), use that name consistently in the step + test.

- [ ] **Step 3: Run to verify it fails**

Run: `vendor\bin\phpunit tests/Feature/Matching/LlmJudgeMatcherTest.php`
Expected: FAIL — `LlmJudgeMatcher` not found.

- [ ] **Step 4: Write the matcher step**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Services\Matching\Steps;

use Padosoft\PriceIntelligence\Contracts\BorderlineOnlyStep;
use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Contracts\MatchStepInterface;
use Padosoft\PriceIntelligence\Data\MatchScore;
use Padosoft\PriceIntelligence\Data\ProductSnapshot;
use Padosoft\PriceIntelligence\Enums\MatchMethod;
use Padosoft\PriceIntelligence\Models\Product;

final class LlmJudgeMatcher implements MatchStepInterface, BorderlineOnlyStep
{
    public function __construct(private readonly LlmProviderInterface $llm) {}

    public function applicable(Product $product, ProductSnapshot $candidate): bool
    {
        return $candidate->title !== null && $candidate->title !== '';
    }

    public function score(Product $product, ProductSnapshot $candidate): MatchScore
    {
        $left = trim(implode(' ', array_filter([$product->brand, $product->model, $product->name])));

        $result = $this->llm->completeJson(
            'You judge whether two product descriptions refer to the same exact product (same model/variant). '
            .'Return JSON: {"same_product": bool, "confidence": int 0-100, "rationale": string}.',
            "A: {$left}\nB: ".(string) $candidate->title,
            ['feature' => 'match_judge'],
        );

        $json = $result->json ?? [];
        $confidence = (int) ($json['confidence'] ?? 0);
        $confidence = max(0, min(100, $confidence));

        return new MatchScore(
            confidence: $confidence,
            method: MatchMethod::Llm,
            evidence: [
                'same_product' => (bool) ($json['same_product'] ?? false),
                'rationale' => is_string($json['rationale'] ?? null) ? $json['rationale'] : '',
                'model' => $result->model,
            ],
        );
    }
}
```

- [ ] **Step 5: Add borderline gating to `MatchingPipeline`**

In `src/Services/Matching/MatchingPipeline.php`, add the import:
```php
use Padosoft\PriceIntelligence\Contracts\BorderlineOnlyStep;
```
Then in the `match()` loop, replace the `applicable` continue-guard so a `BorderlineOnlyStep` is skipped unless the running best is uncertain. Change:
```php
        foreach ($this->steps as $step) {
            if (! $step->applicable($product, $candidate)) {
                continue;
            }
```
to:
```php
        foreach ($this->steps as $step) {
            if (! $step->applicable($product, $candidate)) {
                continue;
            }

            if ($step instanceof BorderlineOnlyStep && ! $this->isUncertain($best->confidence)) {
                continue;
            }
```
and add this private method to the class (next to `decide()`):
```php
    private function isUncertain(int $confidence): bool
    {
        [, $high] = $this->confidenceBand;
        $floor = max(0, $high - 45); // default band [60,85] => run judge for best in [40, 85)

        return $confidence >= $floor && $confidence < $high;
    }
```

- [ ] **Step 6: Append the judge in `MatchingPipelineFactory`**

In `src/Services/Matching/MatchingPipelineFactory.php`, add imports:
```php
use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Services\Matching\Steps\LlmJudgeMatcher;
```
Add a constructor param for the LLM provider:
```php
    public function __construct(
        private readonly EmbeddingProviderInterface $embeddings,
        private readonly LlmProviderInterface $llm,
    ) {}
```
And append the judge inside `make()` after the embedding step block, before `return`:
```php
        if ((bool) config('price-intelligence.matching.llm.enabled', true) !== false) {
            $steps[] = new LlmJudgeMatcher($this->llm);
        }
```
> The container auto-resolves the new constructor param via the `LlmProviderInterface` binding (Task 10). If `MatchingPipelineFactory` is `bind`-ed explicitly anywhere in the ServiceProvider, update that closure to pass `$app->make(LlmProviderInterface::class)`.

- [ ] **Step 7: Run to verify it passes**

Run: `vendor\bin\phpunit tests/Feature/Matching/LlmJudgeMatcherTest.php`
Expected: PASS (3 tests).

- [ ] **Step 8: Run the full matching suite to guard against regressions**

Run: `vendor\bin\phpunit tests/Feature/Matching tests/Unit`
Expected: PASS (existing matching tests still green — the judge only runs for borderline best scores).

- [ ] **Step 9: Commit**

```
git add src/Contracts/BorderlineOnlyStep.php src/Services/Matching/Steps/LlmJudgeMatcher.php src/Services/Matching/MatchingPipeline.php src/Services/Matching/MatchingPipelineFactory.php tests/Feature/Matching/LlmJudgeMatcherTest.php
git commit -m "feat(b1): add borderline-gated LLM judge matching step"
```

---

## Task 16: Opt-in live smoke suite (skipped in CI)

**Files:**
- Create: `tests/Live/LiveLlmSmokeTest.php`
- Modify: `phpunit.xml.dist` (add a `Live` testsuite excluded from the default run, if not already path-scoped)

- [ ] **Step 1: Write the opt-in live test**

```php
<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Live;

use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Contracts\NarrativeWriterInterface;
use Padosoft\PriceIntelligence\Services\Ai\Llm\LaravelAiLlmProvider;
use Padosoft\PriceIntelligence\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('live')]
final class LiveLlmSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (env('PI_LIVE_LLM') !== '1') {
            $this->markTestSkipped('Set PI_LIVE_LLM=1 and configure config/ai.php provider keys to run live LLM smoke tests.');
        }

        config()->set('price-intelligence.ai.llm.driver', 'laravel-ai');
        config()->set('price-intelligence.ai.llm.provider', env('PI_LLM_PROVIDER', 'openai'));
        config()->set('price-intelligence.ai.llm.model', env('PI_LLM_MODEL', 'gpt-4o-mini'));
    }

    #[Test]
    public function real_provider_is_bound_and_completes(): void
    {
        $provider = app(LlmProviderInterface::class);
        $this->assertInstanceOf(LaravelAiLlmProvider::class, $provider);

        $result = $provider->complete('Be terse.', 'Reply with the single word: ok', ['feature' => 'general']);
        $this->assertNotSame('', $result->text);
        $this->assertNotSame('fake', $result->model);
    }

    #[Test]
    public function real_narrative_round_trips(): void
    {
        $result = app(NarrativeWriterInterface::class)->write(1, '2026-W21', ['top_movers' => []]);
        $this->assertNotSame('', $result->summaryMd);
    }
}
```

- [ ] **Step 2: Ensure CI excludes the live suite**

Read `phpunit.xml.dist`. If the default `<testsuite>` includes the whole `tests/` directory, add an exclusion so `tests/Live` is not run by default:
```xml
        <testsuite name="default">
            <directory>tests/Feature</directory>
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="live">
            <directory>tests/Live</directory>
        </testsuite>
```
If the existing config already targets `tests/Feature` + `tests/Unit` explicitly, leave it — `tests/Live` is already excluded. Confirm the CI workflow command does not pass `--testsuite live` or a bare `tests` path.

- [ ] **Step 3: Verify the suite is skipped without env**

Run: `vendor\bin\phpunit tests/Live/LiveLlmSmokeTest.php`
Expected: 2 skipped (no live call).

- [ ] **Step 4: Commit**

```
git add tests/Live/LiveLlmSmokeTest.php phpunit.xml.dist
git commit -m "test(b1): add opt-in live LLM smoke suite (skipped in CI)"
```

---

## Task 17: Docs, CHANGELOG, full gate, release v1.3.0

**Files:**
- Modify: `docs/PROGRESS.md`, `docs/LESSON.md`, `CHANGELOG.md`, `README.md` (LLM section), `config/price-intelligence.php` (already), `composer.json` (already)

- [ ] **Step 1: Run the full local gate**

Run (PowerShell, each by path/limit):
```
composer validate --strict
vendor\bin\phpunit
vendor\bin\pint --test
vendor\bin\phpstan analyse --memory-limit=1G
```
Expected: all green. Fix anything that fails before proceeding. (Pint may rewrite line endings locally — verify only changed files; CI uses LF.)

- [ ] **Step 2: Update PROGRESS.md**

Replace the "Next action" block with the B1 completion state and the B2 pointer:
```markdown
### Next action
**B1 COMPLETE — LLM provider layer shipped (core v1.3.0).** laravel/ai SDK + laravel-ai-regolo
wired behind LlmProviderInterface (fake default / laravel-ai driver); NarrativeWriter,
ContentGapAnalyzer, PromoDetector, VisualMatcher, the borderline-gated LlmJudgeMatcher, and a
laravel/ai embedding driver are all real, each logging to ai_decision_logs, all fixture-tested with
an opt-in live suite. NEXT: **B2 — Marketplace API adapters → core v1.4.0** (Amazon SP-API + Keepa,
eBay, Google Shopping SERP, Farfetch multi-driver). See
docs/superpowers/specs/2026-05-25-b-phases-design.md §3 (CORE/B2).
```

- [ ] **Step 3: Append B1 lessons to LESSON.md**

Add a `## B1 — LLM provider layer` section capturing: the chosen `agent()`/`Embeddings` signatures actually observed (Task 1/6/9), the seam pattern (AgentRunner/EmbeddingRunner) that keeps CI offline, the JSON-fence stripping in `completeJson`, the BorderlineOnlyStep gating decision, and whether `laravel-ai-regolo` shipped via require or stayed suggest-only.

- [ ] **Step 4: Add the CHANGELOG entry**

Prepend to `CHANGELOG.md` (create if missing, Keep-a-Changelog style):
```markdown
## [1.3.0] - 2026-05-25
### Added
- Provider-agnostic LLM layer (`LlmProviderInterface`) built on the official `laravel/ai` SDK with
  `padosoft/laravel-ai-regolo` for the EU/Italian-safe Regolo provider. Drivers: `fake` (default,
  offline-deterministic) and `laravel-ai`.
- Real LLM-backed `NarrativeWriter`, `ContentGapAnalyzer`, `PromoDetector`, vision `VisualMatcher`,
  and a borderline-gated `LlmJudgeMatcher` matching step.
- `laravel/ai` embedding driver (`LaravelAiEmbeddingProvider`) selectable via
  `matching.embeddings.driver`.
- Every AI feature records an `ai_decision_logs` row (model, output, confidence).
- Opt-in live smoke suite (`tests/Live`, gated on `PI_LIVE_LLM=1`), excluded from CI.
### Config
- New `ai.llm.{driver,provider,model,vision_model,timeout}` block; `matching.embeddings.{driver,provider,model,dimensions}`.
```

- [ ] **Step 5: Add a README "LLM providers" subsection**

Document `PI_LLM_DRIVER=laravel-ai` + pointing `config/ai.php` at `openai`/`anthropic`/`regolo`, and that the default `fake` driver needs no keys.

- [ ] **Step 6: Local Copilot review loop**

Run (bash):
```
copilot --autopilot --yolo -p "/review the changes on this branch vs origin/main (git diff origin/main...HEAD); list concrete actionable bugs/edge-cases/Laravel best-practice issues only; reply 'NO ISSUES' if none."
```
Reconcile its edits, then re-run the full gate (Step 1) — the local Copilot run skips build/Playwright and may edit+commit, so re-run everything. Loop until it reports NO ISSUES.

- [ ] **Step 7: Commit docs + push the PR**

```
git add docs/PROGRESS.md docs/LESSON.md CHANGELOG.md README.md
git commit -m "docs(b1): record LLM layer, changelog, progress/lesson for v1.3.0"
git push -u origin feat/phase-b1-llm-provider
gh pr create --title "B1: LLM provider layer (laravel/ai + regolo) → v1.3.0" --body "<summary + test plan>"
gh api --method POST repos/padosoft/laravel-ai-price-intelligence/pulls/<n>/requested_reviewers -f "reviewers[]=copilot-pull-request-reviewer[bot]"
```

- [ ] **Step 8: Remote loop → auto-merge → tag**

Wait for CI green AND GitHub Copilot zero actionable comments (verify findings; push back when wrong). Then:
```
gh pr merge <n> --squash --delete-branch
git checkout main; git pull
git tag v1.3.0; git push origin v1.3.0
gh release create v1.3.0 --title "v1.3.0 — LLM provider layer" --notes-from-tag
```
Then advance to **B2** (its own plan).

---

## Self-Review (performed against the spec §B1)

**Spec coverage:**
- "official Laravel AI SDK + laravel-ai-regolo" → Task 1. ✅
- "LlmProvider contract with drivers openai/anthropic/regolo/fake (default)" → Tasks 3/4/7/10 (provider is config-selected within the `laravel-ai` driver; `fake` is the default driver). ✅
- "Config ai.llm.driver + per-feature model" → Task 8 (`ai.llm` block; per-call `model` override used by VisualMatcher vision_model). ✅
- "real NarrativeWriter, ContentGapAnalyzer, PromoDetector, VisualMatcher, matching LLM-judge, real EmbeddingProvider" → Tasks 11/12/13/14/15/9. ✅
- "each falling back to fake/statistical when no provider configured" → the `fake` driver is the default binding; every feature depends only on `LlmProviderInterface`. ✅
- "every LLM call writes an ai_decision_logs row" → Tasks 11–14 call `AiDecisionLogger->record`; judge writes its evidence into the MatchScore trail (logged by the existing matching persistence). ✅
- "Http::fake + recorded fixtures; opt-in live suite gated on env keys" → unit isolation via AgentRunner/EmbeddingRunner stubs (no HTTP at all in CI, stronger than Http::fake) + Task 16 live suite. ✅
- "Acceptance: provider configured → real result; none → fake default works; CI green with no live calls" → Task 10 binding tests + Task 16 skip + Tasks 4/11–15 fake-path tests. ✅

**Placeholder scan:** No TBD/TODO; every code step shows complete code. The three "verify signature before running" notes (Product fillable, ProductSnapshot ctor, MatchMethod case, agent()/Embeddings signature, phpunit.xml.dist shape) are deliberate guards against the one area I could not fully read — they name the exact file to check and the exact adaptation, not vague hand-waving.

**Type consistency:** `LlmResult` (text, model, promptTokens, completionTokens, json) used identically across Tasks 2/4/7/11–15. `AgentRunResult` (text, model, promptTokens, completionTokens) consistent Tasks 5/6/7. `completeJson`/`complete`/`vision`/`isFake` signatures match the contract (Task 3) in both drivers and all consumers. `AiDecisionLogger->record(tenantId, feature, output, model, confidence, subjectType, subjectId, modelVersion)` used per its verified signature. `MatchScore(confidence, method, evidence)` and `MatchMethod::Llm`/`Embedding`/`Mpn`/`NormalizedName` used consistently (flagged to confirm the `Llm` case name).
