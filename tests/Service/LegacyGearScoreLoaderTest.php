<?php

namespace App\Tests\Service;

use App\Service\LegacyGearScoreLoader;
use PHPUnit\Framework\TestCase;

class LegacyGearScoreLoaderTest extends TestCase
{
    public function testLoadsIntegerAndFractionalItemLevelRows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'gear-score-');
        self::assertNotFalse($path);
        file_put_contents($path, implode("\n", [
            "(49623,'Shadowmourne',284,4,17,80,2,1,3,1433),",
            "(42943,'Bloodied Arcanite Reaper',187.05,3,17,0,2,1,0,484);",
        ]));

        try {
            $scores = (new LegacyGearScoreLoader())->load($path);
        } finally {
            unlink($path);
        }

        $this->assertSame(1433, $scores[49623]);
        $this->assertSame(484, $scores[42943]);
    }
}
