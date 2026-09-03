<?php

namespace App\Tests\Service;

use App\Service\MetaGemRequirementChecker;
use PHPUnit\Framework\TestCase;

class MetaGemRequirementCheckerTest extends TestCase
{
    public function testWotlkMinimumRequirementCountsHybridGemsForBothColours(): void
    {
        $results = (new MetaGemRequirementChecker())->check([
            3627, // Insightful: requires red, yellow, and blue
            3477, // orange: red + yellow
            3464, // purple: red + blue
        ]);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['active']);
        $this->assertSame(['red' => 2, 'yellow' => 1, 'blue' => 1], $results[0]['colors']);
    }

    public function testPrismaticGemCountsAsEveryPrimaryColour(): void
    {
        $results = (new MetaGemRequirementChecker())->check([3628, 3879]);

        $this->assertTrue($results[0]['active']);
        $this->assertSame(['red' => 1, 'yellow' => 1, 'blue' => 1], $results[0]['colors']);
    }

    public function testMetaGemsDoNotCountTowardOtherMetaRequirements(): void
    {
        $results = (new MetaGemRequirementChecker())->check([3621, 3623, 3371, 3371]);

        $this->assertFalse($results[0]['active']);
        $this->assertFalse($results[1]['active']);
        $this->assertSame(['red' => 2, 'yellow' => 0, 'blue' => 0], $results[0]['colors']);
    }

    public function testTbcComparisonRequirementIsStrict(): void
    {
        $checker = new MetaGemRequirementChecker();

        $equal = $checker->check([2832, 3464]); // purple is one red and one blue
        $moreRed = $checker->check([2832, 3464, 3371]);

        $this->assertFalse($equal[0]['active']);
        $this->assertTrue($moreRed[0]['active']);
    }

    public function testChecksStarflareAndEarthshatterVariants(): void
    {
        $results = (new MetaGemRequirementChecker())->check([3800, 3371, 3454, 3454]);

        $this->assertSame('Impassive Starflare Diamond', $results[0]['name']);
        $this->assertTrue($results[0]['active']);
    }
}
