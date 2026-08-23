<?php

namespace App\Tests\Service;

use App\Service\CharacterStatCalculator;
use PHPUnit\Framework\TestCase;

class CharacterStatCalculatorTest extends TestCase
{
    public function testItCombinesBaseGearGemsEnchantsSocketBonusesTalentsAndRatings(): void
    {
        $items = [[
            'name' => 'Test Helm',
            'tooltip' => [
                'stats' => [
                    ['type' => 4, 'value' => 50],
                    ['type' => 7, 'value' => 60],
                    ['type' => 31, 'value' => 32],
                    ['type' => 36, 'value' => 40],
                ],
                'armor' => 1000,
            ],
            'enchant_name' => '+10 All Stats and +20 Hit Rating',
            'gem_details' => [
                ['name' => 'Bold Test Gem', 'effect' => '+20 Strength'],
                ['name' => 'Smooth Test Gem', 'effect' => '+10 Crit Rating'],
            ],
            'display_tooltip' => [
                'socket_bonus' => ['active' => true, 'text' => '+6 Stamina'],
            ],
        ]];
        $talents = ['0' => [[
            'tiers' => [[
                ['spellId' => 20266, 'pointsText' => '5/5'],
            ]],
        ]]];

        $result = (new CharacterStatCalculator())->calculate('Human', 'Paladin', 80, $items, $talents);

        self::assertTrue($result['available']);
        self::assertSame(151, $result['primary']['strength']['class'] + $result['primary']['strength']['level']);
        self::assertSame(80, $result['primary']['strength']['gear']);
        self::assertSame(34, $result['primary']['strength']['talents']);
        self::assertSame(265, $result['primary']['strength']['total']);
        self::assertSame(76, $result['primary']['stamina']['gear']);
        self::assertSame(1000, $result['secondary']['armor']['value']);
        self::assertSame(52, $result['ratings']['melee_hit_rating']['rating']);
        self::assertSame(1.59, $result['ratings']['melee_hit_rating']['percent']);
        self::assertSame(1.22, $result['ratings']['melee_haste_rating']['percent']);
        self::assertSame(10, $result['ratings']['spell_crit_rating']['rating']);
        self::assertSame('Divine Strength', $result['talentModifiers'][0]['name']);
    }

    public function testRatingConversionsScaleWithCharacterLevel(): void
    {
        $item = [[
            'tooltip' => ['stats' => [['type' => 31, 'value' => 10]]],
        ]];
        $calculator = new CharacterStatCalculator();

        $at60 = $calculator->calculate('Human', 'Warrior', 60, $item);
        $at80 = $calculator->calculate('Human', 'Warrior', 80, $item);

        self::assertSame(1.0, $at60['ratings']['melee_hit_rating']['percent']);
        self::assertSame(0.3, $at80['ratings']['melee_hit_rating']['percent']);
        self::assertSame(0.38, $at80['ratings']['spell_hit_rating']['percent']);
    }
}
