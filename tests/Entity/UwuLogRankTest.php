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
}
