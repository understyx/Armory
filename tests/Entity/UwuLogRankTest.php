<?php

namespace App\Tests\Entity;

use App\Entity\UwuLogRank;
use PHPUnit\Framework\TestCase;

final class UwuLogRankTest extends TestCase
{
    public function testItMapsSavedPointsToRankColorAndClassSpecName(): void
    {
        $rank = (new UwuLogRank())
            ->setSpec('2')
            ->setPayload(['overallPoints' => 91.5]);

        self::assertSame('#ff3c00', $rank->getScoreColor());
        self::assertSame('Frost', $rank->getSpecName('Death Knight'));
        self::assertSame('Fire', $rank->getSpecName('Mage'));
    }

    public function testBestParseUsesPointsBeforeAbsoluteRank(): void
    {
        $highPointsLargeRank = (new UwuLogRank())
            ->setOverallRank(4000)
            ->setPayload(['overallPoints' => 95.0]);
        $lowPointsSmallRank = (new UwuLogRank())
            ->setOverallRank(100)
            ->setPayload(['overallPoints' => 70.0]);

        self::assertTrue($highPointsLargeRank->isBetterThan($lowPointsSmallRank));
        self::assertFalse($lowPointsSmallRank->isBetterThan($highPointsLargeRank));
    }
}
