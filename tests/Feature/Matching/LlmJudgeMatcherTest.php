<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Feature\Matching;

use Padosoft\PriceIntelligence\Contracts\BorderlineOnlyStep;
use Padosoft\PriceIntelligence\Contracts\LlmProviderInterface;
use Padosoft\PriceIntelligence\Contracts\MatchStepInterface;
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

    private function fixedStep(int $confidence, MatchMethod $method): MatchStepInterface
    {
        return new class($confidence, $method) implements MatchStepInterface
        {
            public function __construct(private readonly int $confidence, private readonly MatchMethod $method) {}

            public function applicable(Product $product, ProductSnapshot $candidate): bool
            {
                return true;
            }

            public function score(Product $product, ProductSnapshot $candidate): MatchScore
            {
                return new MatchScore($this->confidence, $this->method, []);
            }
        };
    }

    #[Test]
    public function judge_scores_a_borderline_candidate(): void
    {
        $llm = $this->llmReturning(78, true);
        $judge = new LlmJudgeMatcher($llm);

        $product = new Product(['name' => 'Widget', 'brand' => 'Acme']);
        $candidate = new ProductSnapshot(url: 'https://x/p', title: 'Acme Widget');

        $score = $judge->score($product, $candidate);

        $this->assertSame(78, $score->confidence);
        $this->assertSame(MatchMethod::Llm, $score->method);
    }

    #[Test]
    public function pipeline_skips_judge_when_best_is_already_confident(): void
    {
        $llm = $this->llmReturning(78, true);
        $judge = new LlmJudgeMatcher($llm);
        $confident = $this->fixedStep(92, MatchMethod::MpnBrand);

        $pipeline = new MatchingPipeline([$confident, $judge], [60, 85]);
        $outcome = $pipeline->match(new Product(['name' => 'Widget']), new ProductSnapshot(url: 'https://x/p', title: 'Widget'));

        $this->assertSame(0, $llm->calls, 'judge must not run when best >= high');
        $this->assertSame(92, $outcome->confidence);
    }

    #[Test]
    public function pipeline_survives_a_throwing_borderline_step(): void
    {
        $throwingJudge = new class implements BorderlineOnlyStep, MatchStepInterface
        {
            public function applicable(Product $product, ProductSnapshot $candidate): bool
            {
                return true;
            }

            public function score(Product $product, ProductSnapshot $candidate): MatchScore
            {
                throw new \RuntimeException('flaky LLM / undecodable JSON');
            }
        };
        $weak = $this->fixedStep(64, MatchMethod::NormalizedName);

        $pipeline = new MatchingPipeline([$weak, $throwingJudge], [60, 85]);
        $outcome = $pipeline->match(new Product(['name' => 'Widget']), new ProductSnapshot(url: 'https://x/p', title: 'Widget'));

        // The deterministic step's score stands; the throwing judge is swallowed (reported, non-fatal).
        $this->assertSame(64, $outcome->confidence);
    }

    #[Test]
    public function pipeline_runs_judge_when_best_is_uncertain(): void
    {
        $llm = $this->llmReturning(80, true);
        $judge = new LlmJudgeMatcher($llm);
        $weak = $this->fixedStep(64, MatchMethod::NormalizedName);

        $pipeline = new MatchingPipeline([$weak, $judge], [60, 85]);
        $outcome = $pipeline->match(new Product(['name' => 'Widget']), new ProductSnapshot(url: 'https://x/p', title: 'Widget'));

        $this->assertSame(1, $llm->calls, 'judge must run when 60 <= best < 85');
        $this->assertSame(80, $outcome->confidence);
    }
}
