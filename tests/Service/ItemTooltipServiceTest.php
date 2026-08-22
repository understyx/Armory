<?php

namespace App\Tests\Service;

use App\Service\ItemTooltipService;
use PHPUnit\Framework\TestCase;

class ItemTooltipServiceTest extends TestCase
{
    public function testBuildsHeroicTooltipAndActivatesMatchingSocketBonus(): void
    {
        $tooltip = (new ItemTooltipService())->build([
            'id' => 50653,
            'name' => "Shadowvault Slayer's Cloak",
            'quality' => 4,
            'icon_url' => 'https://wow.zamimg.com/images/wow/icons/large/inv_misc_cape_20.jpg',
            'icon_fallback_url' => '/wow-icons/large/inv_misc_cape_20.jpg',
            'type' => 16,
            'class' => 4,
            'subclass' => 1,
            'requires' => 80,
            'ilvl' => 277,
            'enchant_name' => 'Swordguard Embroidery',
            'gem_details' => [[
                'name' => 'Inscribed Ametrine',
                'effect' => '+20 Armor Pen Rating',
                'color_mask' => 2 | 4,
                'icon_url' => '/gem.jpg',
            ]],
            'tooltip' => [
                'flags' => 8,
                'bonding' => 1,
                'max_count' => 0,
                'armor' => 185,
                'stats' => [
                    ['type' => 38, 'value' => 120],
                    ['type' => 3, 'value' => 102],
                    ['type' => 7, 'value' => 102],
                    ['type' => 32, 'value' => 60],
                    ['type' => 36, 'value' => 68],
                ],
                'damage' => [],
                'sockets' => [['color' => 4, 'content' => 0]],
                'socket_bonus_id' => 2877,
                'sell_price' => 76779,
                'description' => '',
                'item_set_id' => 0,
            ],
        ]);

        $this->assertNotNull($tooltip);
        $this->assertSame(
            'https://wow.zamimg.com/images/wow/icons/large/inv_misc_cape_20.jpg',
            $tooltip['icon_url']
        );
        $this->assertSame('/wow-icons/large/inv_misc_cape_20.jpg', $tooltip['icon_fallback_url']);
        $this->assertTrue($tooltip['heroic']);
        $this->assertSame('Binds when picked up', $tooltip['binding']);
        $this->assertSame('Back', $tooltip['slot']);
        $this->assertSame('Cloth', $tooltip['subclass']);
        $this->assertSame(['+102 Agility', '+102 Stamina'], $tooltip['primary_stats']);
        $this->assertContains('Increases attack power by 120.', $tooltip['equip_effects']);
        $this->assertTrue($tooltip['sockets'][0]['matches']);
        $this->assertSame('+20 Armor Penetration Rating', $tooltip['sockets'][0]['gem']['effect']);
        $this->assertSame('+4 Agility', $tooltip['socket_bonus']['text']);
        $this->assertTrue($tooltip['socket_bonus']['active']);
        $this->assertSame(['gold' => 7, 'silver' => 67, 'copper' => 79], $tooltip['sell_price']);
    }

    public function testMismatchedGemLeavesSocketBonusInactive(): void
    {
        $tooltip = (new ItemTooltipService())->build([
            'id' => 1,
            'name' => 'Test Item',
            'quality' => 4,
            'type' => 1,
            'class' => 4,
            'subclass' => 4,
            'gem_details' => [['name' => 'Red Gem', 'effect' => '+10 Strength', 'color_mask' => 2]],
            'tooltip' => [
                'flags' => 0,
                'bonding' => 0,
                'max_count' => 0,
                'stats' => [],
                'damage' => [],
                'sockets' => [['color' => 8, 'content' => 0]],
                'socket_bonus_id' => 3312,
                'sell_price' => 0,
                'description' => '',
                'item_set_id' => 0,
            ],
        ]);

        $this->assertNotNull($tooltip);
        $this->assertFalse($tooltip['sockets'][0]['matches']);
        $this->assertFalse($tooltip['socket_bonus']['active']);
    }

    public function testExcessArmoryGemsBecomePrismaticProfessionSockets(): void
    {
        $tooltip = (new ItemTooltipService())->build([
            'id' => 2,
            'name' => 'Socketed Belt',
            'quality' => 4,
            'type' => 6,
            'class' => 4,
            'subclass' => 4,
            'gem_details' => [
                ['effect' => '+20 Strength', 'color_mask' => 2],
                ['effect' => '+20 Hit Rating', 'color_mask' => 4],
                ['effect' => '+20 Armor Pen', 'color_mask' => 2],
            ],
            'tooltip' => [
                'flags' => 0,
                'bonding' => 0,
                'max_count' => 0,
                'stats' => [],
                'damage' => [],
                'sockets' => [
                    ['color' => 2, 'content' => 0],
                    ['color' => 4, 'content' => 0],
                ],
                'socket_bonus_id' => 3312,
                'sell_price' => 0,
                'description' => '',
                'item_set_id' => 0,
            ],
        ]);

        $this->assertNotNull($tooltip);
        $this->assertCount(3, $tooltip['sockets']);
        $this->assertSame('Prismatic', $tooltip['sockets'][2]['color']);
        $this->assertTrue($tooltip['sockets'][2]['matches']);
        $this->assertTrue($tooltip['sockets'][2]['inferred']);
        $this->assertFalse($tooltip['sockets'][2]['counts_for_bonus']);
        $this->assertSame('+20 Armor Penetration Rating', $tooltip['sockets'][2]['gem']['effect']);
        $this->assertTrue($tooltip['socket_bonus']['active']);
    }

    public function testAddsCachedSpecialEffectsAndSetBonuses(): void
    {
        $tooltip = (new ItemTooltipService())->build([
            'id' => 51159,
            'name' => 'Sanctified Bloodmage Gloves',
            'quality' => 4,
            'external_effects' => [[
                'spell_id' => 71562,
                'type' => 'use',
                'text' => 'Awaken the powers of Northrend.',
                'source' => 'cavern_of_time',
            ]],
            'item_set_details' => [
                'name' => "Sanctified Bloodmage's Regalia",
                'members' => [
                    ['item_id' => 51159, 'name' => 'Sanctified Bloodmage Gloves'],
                    ['item_id' => 51158, 'name' => 'Sanctified Bloodmage Hood'],
                ],
                'bonuses' => [
                    ['required_count' => 2, 'description' => 'Gain 12% haste.'],
                    ['required_count' => 4, 'description' => 'Deal 18% additional damage.'],
                ],
            ],
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
                'item_set_id' => 883,
            ],
        ], [883 => 2], [51159, 51158]);

        self::assertNotNull($tooltip);
        self::assertSame('use', $tooltip['effects'][0]['type']);
        self::assertSame('Awaken the powers of Northrend.', $tooltip['effects'][0]['text']);
        self::assertSame("Sanctified Bloodmage's Regalia", $tooltip['item_set']['name']);
        self::assertTrue($tooltip['item_set']['members'][0]['equipped']);
        self::assertTrue($tooltip['item_set']['bonuses'][0]['active']);
        self::assertFalse($tooltip['item_set']['bonuses'][1]['active']);
    }

    public function testHighlightsSingleEquippedSetPieceAcrossDifficultyIds(): void
    {
        $itemName = 'Sanctified Bloodmage Gloves';
        $tooltip = (new ItemTooltipService())->build([
            'id' => 51280,
            'name' => $itemName,
            'quality' => 4,
            'item_set_details' => [
                'name' => "Sanctified Bloodmage's Regalia",
                // External databases may list another difficulty's item ID.
                'members' => [
                    ['item_id' => 51159, 'name' => $itemName],
                    ['item_id' => 51158, 'name' => 'Sanctified Bloodmage Hood'],
                ],
                'bonuses' => [],
            ],
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
                'item_set_id' => 883,
            ],
        ], [883 => 1], [51280], [$itemName]);

        self::assertNotNull($tooltip);
        self::assertSame(1, $tooltip['item_set']['equipped_count']);
        self::assertTrue($tooltip['item_set']['members'][0]['equipped']);
        self::assertFalse($tooltip['item_set']['members'][1]['equipped']);
    }

    public function testHighlightsSanctifiedHunterPiecesListedByTheirBaseNames(): void
    {
        $tooltip = (new ItemTooltipService())->build([
            'id' => 51154,
            'name' => "Sanctified Ahn'Kahar Blood Hunter's Handguards",
            'quality' => 4,
            'item_set_details' => [
                'name' => "Ahn'Kahar Blood Hunter's Battlegear",
                'members' => [
                    ['item_id' => 50114, 'name' => "Ahn'Kahar Blood Hunter's Handguards"],
                    ['item_id' => 50115, 'name' => "Ahn'Kahar Blood Hunter's Headpiece"],
                    ['item_id' => 50116, 'name' => "Ahn'Kahar Blood Hunter's Legguards"],
                    ['item_id' => 50117, 'name' => "Ahn'Kahar Blood Hunter's Spaulders"],
                    ['item_id' => 50118, 'name' => "Ahn'Kahar Blood Hunter's Tunic"],
                ],
                'bonuses' => [],
            ],
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
                'item_set_id' => 859,
            ],
        ], [859 => 4], [51154, 51153, 51152, 51151], [
            "Sanctified Ahn'Kahar Blood Hunter's Handguards",
            "Sanctified Ahn'Kahar Blood Hunter's Headpiece",
            "Sanctified Ahn'Kahar Blood Hunter's Legguards",
            "Sanctified Ahn'Kahar Blood Hunter's Spaulders",
        ]);

        self::assertNotNull($tooltip);
        self::assertSame(4, $tooltip['item_set']['equipped_count']);
        self::assertSame(
            [true, true, true, true, false],
            array_column($tooltip['item_set']['members'], 'equipped')
        );
    }

    public function testExpandsGemAndEnchantStatShorthand(): void
    {
        $tooltip = (new ItemTooltipService())->build([
            'id' => 1,
            'name' => 'Shorthand Test Item',
            'quality' => 4,
            'enchant_name' => '+23 SP & +20 Crit & +20 Hit',
            'gem_details' => [[
                'effect' => '+10 Haste & +10 Expertise & +10 Armor Pen',
                'color_mask' => 2,
            ]],
            'tooltip' => [
                'flags' => 0,
                'bonding' => 0,
                'max_count' => 0,
                'stats' => [],
                'damage' => [],
                'sockets' => [['color' => 2, 'content' => 0]],
                'socket_bonus_id' => 0,
                'sell_price' => 0,
                'description' => '',
                'item_set_id' => 0,
            ],
        ]);

        self::assertNotNull($tooltip);
        self::assertSame(
            '+23 Spell Power & +20 Critical Strike Rating & +20 Hit Rating',
            $tooltip['enchant']
        );
        self::assertSame(
            '+10 Haste Rating & +10 Expertise Rating & +10 Armor Penetration Rating',
            $tooltip['sockets'][0]['gem']['effect']
        );
    }
}
