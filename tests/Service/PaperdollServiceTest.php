<?php

namespace App\Tests\Service;

use App\Enum\ItemTypes;
use App\Service\ItemDatabaseService;
use App\Service\ItemTooltipService;
use App\Service\PaperdollService;
use PHPUnit\Framework\TestCase;

class PaperdollServiceTest extends TestCase
{
    public function testBuildsDistinctDynamicTooltipsForTwoInstancesOfTheSameItem(): void
    {
        $mockItemDb = $this->createMock(ItemDatabaseService::class);
        $mockItemDb->method('getItemsBulk')->willReturn([
            50002 => [
                'name' => 'Ring of Testing',
                'quality' => 4,
                'type' => ItemTypes::RING->value,
                'class' => 4,
                'subclass' => 0,
                'tooltip' => [
                    'flags' => 0,
                    'bonding' => 1,
                    'max_count' => 0,
                    'stats' => [],
                    'damage' => [],
                    'sockets' => [],
                    'socket_bonus_id' => 0,
                    'sell_price' => 0,
                    'description' => '',
                    'item_set_id' => 0,
                ],
            ],
        ]);

        $service = new PaperdollService($mockItemDb, new ItemTooltipService());
        $result = $service->buildPaperdollSlots([
            ['id' => 50002, 'enchant' => 1],
            ['id' => 50002, 'enchant' => 2],
        ]);

        $this->assertCount(2, $result['tooltips']);
        $this->assertArrayHasKey('50002-0', $result['tooltips']);
        $this->assertArrayHasKey('50002-1', $result['tooltips']);
        $this->assertSame('Rockbiter 3', $result['tooltips']['50002-0']['enchant']);
        $this->assertSame('Frostbr& 1', $result['tooltips']['50002-1']['enchant']);
    }

    public function testBuildPaperdollSlotsAssignsItemsByTypesAndFallbacks(): void
    {
        $mockItemDb = $this->createMock(ItemDatabaseService::class);
        $mockItemDb->method('getItemsBulk')
            ->willReturn([
                50001 => ['name' => 'Helm of Light', 'quality' => 4, 'type' => ItemTypes::HEAD->value, 'icon' => 'inv_helmet_06'],
                50002 => ['name' => 'Ring of Might', 'quality' => 3, 'type' => ItemTypes::RING->value, 'icon' => 'inv_jewelry_ring_01'],
                50003 => ['name' => 'Ring of Power', 'quality' => 4, 'type' => ItemTypes::RING->value, 'icon' => 'inv_jewelry_ring_02'],
                50004 => ['name' => 'Shadowmourne', 'quality' => 5, 'type' => ItemTypes::WEAPON_2H->value, 'icon' => 'inv_axe_113'],
            ]);

        $service = new PaperdollService($mockItemDb);

        $equippedItems = [
            ['id' => 50001, 'enchant' => 3795, 'gems' => [3628]],
            ['id' => 50002],
            ['id' => 50003],
            ['id' => 50004],
        ];

        $result = $service->buildPaperdollSlots($equippedItems);

        $this->assertArrayHasKey('slots', $result);
        $this->assertArrayHasKey('enrichedItems', $result);

        $slots = $result['slots'];

        // Head slot check
        $this->assertNotNull($slots['head']['item']);
        $this->assertSame('Helm of Light', $slots['head']['item']['name']);
        $this->assertSame(4, $slots['head']['item']['quality']);
        $this->assertSame('/wow-icons/large/inv_helmet_06.jpg', $slots['head']['item']['icon_url']);
        $this->assertNotEmpty($slots['head']['item']['gem_details']);
        $this->assertSame(41398, $slots['head']['item']['gem_details'][0]['id']);
        $this->assertSame(3628, $slots['head']['item']['gem_details'][0]['enchant_id']);
        $this->assertSame('Relentless Earthsiege Diamond', $slots['head']['item']['gem_details'][0]['name']);

        // Rings check (finger1 and finger2)
        $this->assertNotNull($slots['finger1']['item']);
        $this->assertSame('Ring of Might', $slots['finger1']['item']['name']);

        $this->assertNotNull($slots['finger2']['item']);
        $this->assertSame('Ring of Power', $slots['finger2']['item']['name']);

        // 2H Weapon check (mainhand)
        $this->assertNotNull($slots['mainhand']['item']);
        $this->assertSame('Shadowmourne', $slots['mainhand']['item']['name']);
        $this->assertSame(5, $slots['mainhand']['item']['quality']);

        // Empty slot check
        $this->assertNull($slots['neck']['item']);
    }

    public function testRigormortisMainhandWeaponIsNotAssignedToTabard(): void
    {
        $mockItemDb = $this->createMock(ItemDatabaseService::class);
        $mockItemDb->method('getItemsBulk')
            ->willReturn([
                50704 => [
                    'name' => 'Rigormortis',
                    'quality' => 4,
                    'type' => ItemTypes::WEAPON_MAINHAND->value, // type 21
                    'icon' => 'inv_weapon_shortblade_81'
                ],
            ]);

        $service = new PaperdollService($mockItemDb);

        // Simulate character with no shirt or tabard, where Rigormortis is equipped as mainhand
        $equippedItems = [
            ['id' => 50704],
        ];

        $result = $service->buildPaperdollSlots($equippedItems);
        $slots = $result['slots'];

        // Must be in mainhand slot, NOT in tabard or shirt slot
        $this->assertNotNull($slots['mainhand']['item']);
        $this->assertSame('Rigormortis', $slots['mainhand']['item']['name']);
        $this->assertNull($slots['tabard']['item']);
        $this->assertNull($slots['shirt']['item']);
    }

    public function testWarriorCanEquipTwoTwoHandedWeaponsWithTitansGrip(): void
    {
        $mockItemDb = $this->createMock(ItemDatabaseService::class);
        $mockItemDb->method('getItemsBulk')
            ->willReturn([
                50004 => [
                    'name' => 'Shadowmourne',
                    'quality' => 5,
                    'type' => ItemTypes::WEAPON_2H->value,
                    'icon' => 'inv_axe_113',
                ],
                50730 => [
                    'name' => 'Glorenzelg, High-Blade of the Silver Hand',
                    'quality' => 4,
                    'type' => ItemTypes::WEAPON_2H->value,
                    'icon' => 'inv_sword_148',
                ],
            ]);

        $service = new PaperdollService($mockItemDb);
        $result = $service->buildPaperdollSlots([
            ['id' => 50004],
            ['id' => 50730],
        ], 'Warrior');

        $this->assertSame('Shadowmourne', $result['slots']['mainhand']['item']['name']);
        $this->assertSame(
            'Glorenzelg, High-Blade of the Silver Hand',
            $result['slots']['offhand']['item']['name']
        );
    }
}
