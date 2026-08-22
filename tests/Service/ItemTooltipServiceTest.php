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
            'type' => 16,
            'class' => 4,
            'subclass' => 1,
            'requires' => 80,
            'ilvl' => 277,
            'enchant_name' => 'Swordguard Embroidery',
            'gem_details' => [[
                'name' => 'Inscribed Ametrine',
                'effect' => '+20 Armor Penetration Rating',
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
        $this->assertTrue($tooltip['heroic']);
        $this->assertSame('Binds when picked up', $tooltip['binding']);
        $this->assertSame('Back', $tooltip['slot']);
        $this->assertSame('Cloth', $tooltip['subclass']);
        $this->assertSame(['+102 Agility', '+102 Stamina'], $tooltip['primary_stats']);
        $this->assertContains('Increases attack power by 120.', $tooltip['equip_effects']);
        $this->assertTrue($tooltip['sockets'][0]['matches']);
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
}
