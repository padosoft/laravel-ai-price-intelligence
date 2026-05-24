<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use Padosoft\PriceIntelligence\Services\Compliance\RobotsTxtParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RobotsTxtParserTest extends TestCase
{
    private function parser(): RobotsTxtParser
    {
        return new RobotsTxtParser;
    }

    #[Test]
    public function empty_robots_allows_everything(): void
    {
        $this->assertTrue($this->parser()->isAllowed('', '/anything'));
    }

    #[Test]
    public function disallow_blocks_matching_paths(): void
    {
        $robots = "User-agent: *\nDisallow: /private";
        $this->assertFalse($this->parser()->isAllowed($robots, '/private/page'));
        $this->assertTrue($this->parser()->isAllowed($robots, '/public/page'));
    }

    #[Test]
    public function allow_overrides_disallow_by_longest_match(): void
    {
        $robots = "User-agent: *\nDisallow: /products\nAllow: /products/public";
        $this->assertFalse($this->parser()->isAllowed($robots, '/products/secret'));
        $this->assertTrue($this->parser()->isAllowed($robots, '/products/public/item'));
    }

    #[Test]
    public function specific_user_agent_group_wins(): void
    {
        $robots = "User-agent: *\nDisallow: /\n\nUser-agent: GoodBot\nDisallow:";
        $this->assertFalse($this->parser()->isAllowed($robots, '/x', 'OtherBot'));
        $this->assertTrue($this->parser()->isAllowed($robots, '/x', 'GoodBot'));
    }

    #[Test]
    public function comment_lines_do_not_terminate_a_group(): void
    {
        $robots = "User-agent: *\n# a comment\nDisallow: /private";
        $this->assertFalse($this->parser()->isAllowed($robots, '/private/x'));
    }

    #[Test]
    public function wildcards_and_end_anchor_work(): void
    {
        $robots = "User-agent: *\nDisallow: /*.pdf\$";
        $this->assertFalse($this->parser()->isAllowed($robots, '/files/report.pdf'));
        $this->assertTrue($this->parser()->isAllowed($robots, '/files/report.html'));
    }

    #[Test]
    public function user_agent_is_matched_case_insensitively_by_substring(): void
    {
        // A real UA like "PriceIntelligenceBot/1.0" matches a "priceintelligencebot" group.
        $robots = "User-agent: PriceIntelligenceBot\nDisallow: /no\n\nUser-agent: *\nDisallow:";
        $this->assertFalse($this->parser()->isAllowed($robots, '/no/x', 'PriceIntelligenceBot/1.0 (+https://x)'));
        $this->assertTrue($this->parser()->isAllowed($robots, '/yes', 'PriceIntelligenceBot/1.0'));
    }
}
