<?php

namespace App\Tests\Service;

use App\Service\WotlkBaseStatTable;
use PHPUnit\Framework\TestCase;

class WotlkBaseStatTableTest extends TestCase
{
    public function testItSeparatesRaceClassAndLevelWithoutChangingTheExactTotal(): void
    {
        $stats = (new WotlkBaseStatTable())->getBreakdown('Orc', 'Shaman', 80);

        self::assertNotNull($stats);
        self::assertSame([
            'strength' => 123,
            'agility' => 71,
            'stamina' => 137,
            'intellect' => 125,
            'spirit' => 145,
        ], $stats['total']);
        self::assertSame(3, $stats['race']['strength']);
        self::assertSame(21, $stats['class']['strength']);
        self::assertSame(99, $stats['level']['strength']);
    }

    public function testItReturnsNullForUnsupportedCharacters(): void
    {
        self::assertNull((new WotlkBaseStatTable())->getBreakdown('Pandaren', 'Monk', 80));
    }
}
