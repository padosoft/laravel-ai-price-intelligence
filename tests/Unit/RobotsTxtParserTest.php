<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Padosoft\PriceIntelligence\Services\Compliance\RobotsTxtParser;

final class RobotsTxtParserTest extends TestCase
{
    private function parser(): RobotsTxtParser
    {
        return new RobotsTxtParser();
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
    public function wildcards_and_end_anchor_work(): void
    {
        $robots = "User-agent: *\nDisallow: /*.pdf\$";
        $this->assertFalse($this->parser()->isAllowed($robots, '/files/report.pdf'));
        $this->assertTrue($this->parser()->isAllowed($robots, '/files/report.html'));
    }
}
