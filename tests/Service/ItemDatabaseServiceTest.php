<?php

namespace App\Tests\Service;

use App\Entity\WowItem;
use App\Repository\WowItemRepository;
use App\Service\ItemDatabaseService;
use PHPUnit\Framework\TestCase;

class ItemDatabaseServiceTest extends TestCase
{
    public function testGetItemReturnsFormattedArray(): void
    {
        $item = new WowItem();
        $item->setItemId(51133);
        $item->setName('Sanctified Scourgelord Helmet');
        $item->setItemLevel(264);
        $item->setQuality(4);
        $item->setType(1);
        $item->setRequires(80);
        $item->setClass(4);
        $item->setSubclass(6);
        $item->setGemSlots(2);
        $item->setGearScore(335);
        $item->setIcon('inv_helmet_134');
        $item->setTooltipData(['armor' => 2000]);
        $item->setSourceBuild(12340);
        $item->setGearScoreSource('legacy-items.sql');
        $item->setGearScoreVersion('legacy-items-sql-v1');

        $repo = $this->createMock(WowItemRepository::class);
        $repo->expects($this->once())
            ->method('findByItemId')
            ->with(51133)
            ->willReturn($item);

        $service = new ItemDatabaseService($repo);
        $result = $service->getItem(51133);

        $this->assertNotNull($result);
        $this->assertEquals('Sanctified Scourgelord Helmet', $result['name']);
        $this->assertEquals(264, $result['ilvl']);
        $this->assertEquals(4, $result['quality']);
        $this->assertEquals(1, $result['type']);
        $this->assertEquals('80', $result['requires']);
        $this->assertEquals(4, $result['class']);
        $this->assertEquals(6, $result['subclass']);
        $this->assertEquals(2, $result['gem_slots']);
        $this->assertEquals(335.0, $result['gs']);
        $this->assertEquals('inv_helmet_134', $result['icon']);
        $this->assertSame(['armor' => 2000], $result['tooltip']);
        $this->assertSame(12340, $result['source_build']);
        $this->assertSame('legacy-items.sql', $result['gear_score_source']);
    }

    public function testGetItemReturnsNullWhenNotFound(): void
    {
        $repo = $this->createMock(WowItemRepository::class);
        $repo->expects($this->once())
            ->method('findByItemId')
            ->with(999999)
            ->willReturn(null);

        $service = new ItemDatabaseService($repo);
        $this->assertNull($service->getItem(999999));
    }
}
