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
        self::assertSame(80, $result['itemBreakdown'][0]['total']['strength']);
        self::assertSame(76, $result['itemBreakdown'][0]['total']['stamina']);
        self::assertTrue($result['itemBreakdown'][0]['socketBonus']['active']);
        self::assertSame(['stamina' => 6], $result['itemBreakdown'][0]['socketBonus']['appliedStats']);
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

    public function testItAppliesFlatTalentHitBonusesForTheSelectedSpecialization(): void
    {
        $talents = ['1' => [[
            'name' => 'Marksmanship',
            'tiers' => [[
                ['spellId' => 53622, 'pointsText' => '3/3'],
                ['spellId' => 99999, 'pointsText' => '2/5'],
            ]],
        ]]];

        $result = (new CharacterStatCalculator())->calculate(
            'Dwarf',
            'Hunter',
            72,
            [],
            $talents,
            '1',
        );

        self::assertSame(3.0, $result['hitBonuses']['Melee and ranged attacks']);
        self::assertSame('Focused Aim', $result['talentModifiers'][0]['name']);
        self::assertSame(['Melee and ranged attacks' => 3.0], $result['talentModifiers'][0]['hit']);
        self::assertCount(2, $result['talentBreakdown']);
        self::assertSame('Marksmanship', $result['talentBreakdown'][0]['tree']);
        self::assertFalse($result['talentBreakdown'][1]['recognized']);
        self::assertSame('Talent spell #99999', $result['talentBreakdown'][1]['name']);
        self::assertSame(
            $result['primary']['agility']['race'] + $result['primary']['agility']['classAtLevel'],
            $result['primary']['agility']['base'],
        );
    }

    public function testExpertiseUsesCharacterSheetExpertisePoints(): void
    {
        $result = (new CharacterStatCalculator())->calculate('Human', 'Warrior', 80, [[
            'name' => 'Expertise Test Item',
            'tooltip' => ['stats' => [['type' => 37, 'value' => 82]]],
        ]]);

        self::assertSame('Expertise', $result['ratings']['expertise_rating']['name']);
        self::assertSame(10.0, $result['ratings']['expertise_rating']['value']);
        self::assertSame(2.5, $result['ratings']['expertise_rating']['percent']);
        self::assertSame('', $result['ratings']['expertise_rating']['unit']);
        self::assertSame('expertise', $result['ratings']['expertise_rating']['unitLabel']);
        self::assertSame(8.197, $result['ratings']['expertise_rating']['ratingPerUnit']);
    }

    public function testItReadsEveryStatFromLegacyGemEffectText(): void
    {
        $result = (new CharacterStatCalculator())->calculate('Human', 'Warrior', 80, [[
            'name' => 'Gem Test Item',
            'tooltip' => ['stats' => []],
            'gem_details' => [
                ['name' => 'Fractured Cardinal Ruby', 'effect' => '+20 Armor Pen'],
                ['name' => 'Etched Ametrine', 'effect' => '+10 Strength & +10 Hit'],
            ],
        ]]);

        self::assertSame(
            ['armor_penetration_rating' => 20],
            $result['itemBreakdown'][0]['gems'][0]['stats'],
        );
        self::assertSame(
            [
                'strength' => 10,
                'melee_hit_rating' => 10,
                'ranged_hit_rating' => 10,
                'spell_hit_rating' => 10,
            ],
            $result['itemBreakdown'][0]['gems'][1]['stats'],
        );
        self::assertArrayNotHasKey('armor', $result['gearTotals']);
        self::assertSame(20, $result['gearTotals']['armor_penetration_rating']);
        self::assertSame(10, $result['gearTotals']['strength']);
    }

    public function testSpellCriticalStrikeIncludesIntellectClassBaseAndRating(): void
    {
        $result = (new CharacterStatCalculator())->calculate('Human', 'Mage', 80, [[
            'tooltip' => ['stats' => [
                ['type' => 5, 'value' => 100],
                ['type' => 21, 'value' => 46],
            ]],
        ]]);

        $spellCrit = $result['ratings']['spell_crit_rating'];
        self::assertSame(281, $spellCrit['attributeValue']);
        self::assertSame(1.686, $spellCrit['attributePercent']);
        self::assertSame(1.002, $spellCrit['ratingPercent']);
        self::assertSame(3.6, $spellCrit['totalPercent']);
        self::assertSame(3.6, $spellCrit['value']);
    }

    public function testPhysicalCriticalStrikeIncludesAgilityClassBaseAndRating(): void
    {
        $result = (new CharacterStatCalculator())->calculate('Human', 'Rogue', 80, [[
            'tooltip' => ['stats' => [
                ['type' => 3, 'value' => 100],
                ['type' => 19, 'value' => 46],
            ]],
        ]]);

        $meleeCrit = $result['ratings']['melee_crit_rating'];
        self::assertSame(289, $meleeCrit['attributeValue']);
        self::assertSame(3.468, $meleeCrit['attributePercent']);
        self::assertSame(1.002, $meleeCrit['ratingPercent']);
        self::assertSame(4.18, $meleeCrit['totalPercent']);
        self::assertSame(4.18, $meleeCrit['value']);
    }
}
