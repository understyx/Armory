<?php

namespace App\Tests\Service;

use App\Service\GemDatabase;
use PHPUnit\Framework\TestCase;

class GemDatabaseTest extends TestCase
{
    public function testGetAllByItemIdReturnsUniqueItems(): void
    {
        $gems = GemDatabase::getAllByItemId();
        self::assertGreaterThanOrEqual(190, count($gems));

        foreach ($gems as $itemId => $gem) {
            self::assertSame($itemId, $gem['item_id']);
            self::assertGreaterThan(0, $gem['enchant_id']);
            self::assertNotEmpty($gem['name']);
            self::assertNotEmpty($gem['icon']);
            self::assertGreaterThan(0, $gem['quality']);
            self::assertNotNull($gem['spell_id']);
        }
    }

    public function testGetAllByEnchantId(): void
    {
        $gems = GemDatabase::getAllByEnchantId();
        self::assertCount(count(GemDatabase::GEMS), $gems);

        foreach ($gems as $enchantId => $gem) {
            self::assertSame($enchantId, $gem['enchant_id']);
        }
    }
}
