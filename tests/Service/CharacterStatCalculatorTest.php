<?php

namespace App\Tests\Service;

use App\Service\CharacterStatCalculator;
use App\Service\ItemDatabaseService;
use PHPUnit\Framework\TestCase;

class CharacterStatCalculatorTest extends TestCase
{
    public function testAlreadyEnrichedPaperdollItemsDoNotRepeatDatabaseLookups(): void
    {
        $itemDatabase = $this->createMock(ItemDatabaseService::class);
        $itemDatabase->expects(self::never())->method('getItemsBulk');
        $calculator = new CharacterStatCalculator(itemDatabaseService: $itemDatabase);

        $result = $calculator->calculate('Human', 'Warrior', 80, [[
            'id' => 50001,
            'name' => 'Enriched Helm',
            'tooltip' => ['stats' => [['type' => 4, 'value' => 20]]],
        ]]);

        self::assertSame(20, $result['primary']['strength']['gear']);
    }

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
        self::assertSame(1.59, $result['ratings']['melee_haste_rating']['percent']);
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

    public function testLevel80RatingAnchorsAndGenericItemStatIdsMatchWotlk335a(): void
    {
        $result = (new CharacterStatCalculator())->calculate('Human', 'Warrior', 80, [[
            'tooltip' => ['stats' => [
                ['type' => 13, 'value' => 45],
                ['type' => 12, 'value' => 4],
                ['type' => 14, 'value' => 45],
                ['type' => 15, 'value' => 16],
                ['type' => 35, 'value' => 94],
                ['type' => 44, 'value' => 14],
                ['type' => 37, 'value' => 8],
                ['type' => 31, 'value' => 263],
                ['type' => 32, 'value' => 46],
                ['type' => 36, 'value' => 33],
            ]],
        ]]);

        self::assertSame(0.99, $result['ratings']['dodge_rating']['percent']);
        self::assertSame(45.25, $result['ratings']['dodge_rating']['ratingPerUnit']);
        self::assertSame(45.25, $result['ratings']['parry_rating']['ratingPerUnit']);
        self::assertSame(94.27, $result['ratings']['resilience_rating']['ratingPerUnit']);
        self::assertSame(1.0, $result['ratings']['resilience_rating']['percent']);
        self::assertSame(122.963, $result['ratings']['defense_rating']['ratingPerUnit']);
        self::assertSame(4.92, round($result['ratings']['defense_rating']['ratingPerUnit'] / 25, 2));
        self::assertSame(0.03, $result['ratings']['defense_rating']['percent']);
        self::assertSame(0.98, $result['ratings']['block_rating']['percent']);
        self::assertSame(16.395, $result['ratings']['block_rating']['ratingPerUnit']);
        self::assertSame(8.197, $result['ratings']['expertise_rating']['ratingPerUnit']);
        self::assertSame(1.0, $result['ratings']['armor_penetration_rating']['percent']);
        self::assertSame(13.996, $result['ratings']['armor_penetration_rating']['ratingPerUnit']);
        self::assertSame(0.98, $result['ratings']['expertise_rating']['value']);
        self::assertSame(263, $result['ratings']['melee_hit_rating']['rating']);
        self::assertSame(263, $result['ratings']['ranged_hit_rating']['rating']);
        self::assertSame(263, $result['ratings']['spell_hit_rating']['rating']);
        self::assertSame(46, $result['ratings']['melee_crit_rating']['rating']);
        self::assertSame(46, $result['ratings']['ranged_crit_rating']['rating']);
        self::assertSame(46, $result['ratings']['spell_crit_rating']['rating']);
        self::assertSame(33, $result['ratings']['melee_haste_rating']['rating']);
        self::assertSame(33, $result['ratings']['ranged_haste_rating']['rating']);
        self::assertSame(33, $result['ratings']['spell_haste_rating']['rating']);
    }

    public function testPost32DodgeAndResilienceAnchorsAtLevel60And70(): void
    {
        $calculator = new CharacterStatCalculator();
        $item = [[
            'tooltip' => ['stats' => [
                ['type' => 13, 'value' => 1],
                ['type' => 35, 'value' => 1],
            ]],
        ]];

        $level60 = $calculator->calculate('Human', 'Warrior', 60, $item);
        $level70 = $calculator->calculate('Human', 'Warrior', 70, $item);

        self::assertSame(13.8, $level60['ratings']['dodge_rating']['ratingPerUnit']);
        self::assertSame(28.75, $level60['ratings']['resilience_rating']['ratingPerUnit']);
        self::assertSame(21.759, $level70['ratings']['dodge_rating']['ratingPerUnit']);
        self::assertSame(45.34, $level70['ratings']['resilience_rating']['ratingPerUnit']);
    }

    public function testHybridClassScalarAppliesOnlyToMeleeHaste(): void
    {
        $item = [[
            'tooltip' => ['stats' => [
                ['type' => 28, 'value' => 33],
                ['type' => 29, 'value' => 33],
                ['type' => 30, 'value' => 33],
            ]],
        ]];

        $paladin = (new CharacterStatCalculator())->calculate('Human', 'Paladin', 80, $item);
        $warrior = (new CharacterStatCalculator())->calculate('Human', 'Warrior', 80, $item);

        self::assertSame(1.31, $paladin['ratings']['melee_haste_rating']['percent']);
        self::assertSame(25.223, $paladin['ratings']['melee_haste_rating']['ratingPerUnit']);
        self::assertSame(1.01, $paladin['ratings']['ranged_haste_rating']['percent']);
        self::assertSame(1.01, $paladin['ratings']['spell_haste_rating']['percent']);
        self::assertSame(32.79, $paladin['ratings']['ranged_haste_rating']['ratingPerUnit']);
        self::assertSame(32.79, $paladin['ratings']['spell_haste_rating']['ratingPerUnit']);
        self::assertSame(1.01, $warrior['ratings']['melee_haste_rating']['percent']);

        foreach (['Shaman', 'Druid', 'Death Knight'] as $class) {
            $hybrid = (new CharacterStatCalculator())->calculate('Human', $class, 80, $item);
            self::assertSame(25.223, $hybrid['ratings']['melee_haste_rating']['ratingPerUnit']);
            self::assertSame(32.79, $hybrid['ratings']['ranged_haste_rating']['ratingPerUnit']);
            self::assertSame(32.79, $hybrid['ratings']['spell_haste_rating']['ratingPerUnit']);
        }
    }

    public function testDwarfGunAndTrollBowRacialsOnlyAddRangedCriticalChanceWithTheirWeapon(): void
    {
        $calculator = new CharacterStatCalculator();
        $gun = ['class' => 2, 'subclass' => 3, 'tooltip' => ['stats' => []]];
        $bow = ['class' => 2, 'subclass' => 2, 'tooltip' => ['stats' => []]];

        $dwarfGun = $calculator->calculate('Dwarf', 'Hunter', 80, [$gun]);
        $dwarfBow = $calculator->calculate('Dwarf', 'Hunter', 80, [$bow]);
        $trollBow = $calculator->calculate('Troll', 'Hunter', 80, [$bow]);
        $trollGun = $calculator->calculate('Troll', 'Hunter', 80, [$gun]);

        self::assertSame(1.0, $dwarfGun['ratings']['ranged_crit_rating']['flatBonusPercent']);
        self::assertSame(0.0, $dwarfBow['ratings']['ranged_crit_rating']['flatBonusPercent']);
        self::assertSame(1.0, $trollBow['ratings']['ranged_crit_rating']['flatBonusPercent']);
        self::assertSame(0.0, $trollGun['ratings']['ranged_crit_rating']['flatBonusPercent']);
    }

    public function testWotlkDualWieldSpecializationAddsPhysicalHitAndVitalityUsesIts3Ranks(): void
    {
        $talents = ['0' => [[
            'name' => 'Enhancement',
            'tiers' => [[
                ['spellId' => 30819, 'pointsText' => '3/3'],
            ]],
        ]]];
        $shaman = (new CharacterStatCalculator())->calculate('Orc', 'Shaman', 80, [], $talents);
        self::assertSame(['Attacks while dual wielding' => 6.0], $shaman['hitBonuses']);

        $warriorTalents = ['0' => [[
            'name' => 'Protection',
            'tiers' => [[
                ['spellId' => 29144, 'pointsText' => '3/3'],
                ['spellId' => 29595, 'pointsText' => '3/3'],
            ]],
        ]]];
        $warrior = (new CharacterStatCalculator())->calculate('Human', 'Warrior', 80, [], $warriorTalents);

        self::assertSame(6.0, $warrior['primary']['strength']['talentPercent']);
        self::assertSame(9.0, $warrior['primary']['stamina']['talentPercent']);
        self::assertSame(['Vitality'], array_column($warrior['talentModifiers'], 'name'));
        self::assertSame(false, $warrior['talentBreakdown'][1]['recognized']);
    }

    public function testSpellCriticalStrikeIncludesIntellectClassBaseAndRating(): void
    {
        $calculator = new CharacterStatCalculator();
        $nakedResult = $calculator->calculate('Human', 'Mage', 80, []);
        self::assertSame(1.99, $nakedResult['ratings']['spell_crit_rating']['totalPercent']);

        $result = $calculator->calculate('Human', 'Mage', 80, [[
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

    public function testNitroBoostsAddsTwentyFourCriticalStrikeRating(): void
    {
        $result = (new CharacterStatCalculator())->calculate('Human', 'Warrior', 80, [[
            'name' => 'Test Boots',
            'enchant' => 3606,
            'tooltip' => ['stats' => []],
        ]]);

        self::assertSame(24, $result['ratings']['melee_crit_rating']['rating']);
        self::assertSame(24, $result['ratings']['ranged_crit_rating']['rating']);
        self::assertSame(24, $result['ratings']['spell_crit_rating']['rating']);
        self::assertSame(
            ['melee_crit_rating' => 24, 'ranged_crit_rating' => 24, 'spell_crit_rating' => 24],
            $result['itemBreakdown'][0]['enchant']['stats'],
        );
    }

    public function testFuryWarriorCritIncludesCrueltyRampageAndBerserkerStance(): void
    {
        $talents = ['0' => [
            ['name' => 'Arms', 'points' => 0, 'tiers' => []],
            ['name' => 'Fury', 'points' => 71, 'tiers' => [[
                ['spellId' => 12856, 'pointsText' => '5/5'],
                ['spellId' => 29801, 'pointsText' => '1/1'],
            ]]],
            ['name' => 'Protection', 'points' => 0, 'tiers' => []],
        ]];

        $result = (new CharacterStatCalculator())->calculate('Human', 'Warrior', 80, [], $talents);

        self::assertSame(13.0, $result['ratings']['melee_crit_rating']['flatBonusPercent']);
        self::assertSame(13.0, $result['ratings']['ranged_crit_rating']['flatBonusPercent']);
        self::assertSame(0.0, $result['ratings']['spell_crit_rating']['flatBonusPercent']);
        self::assertSame(['Cruelty', 'Rampage'], array_column($result['talentModifiers'], 'name'));
        self::assertSame('Berserker Stance', $result['assumedEffects'][0]['name']);
    }

    public function testArmsWarriorUsesBattleStanceArmorPenetration(): void
    {
        $talents = ['0' => [
            ['name' => 'Arms', 'points' => 71, 'tiers' => []],
            ['name' => 'Fury', 'points' => 0, 'tiers' => []],
            ['name' => 'Protection', 'points' => 0, 'tiers' => []],
        ]];

        $result = (new CharacterStatCalculator())->calculate('Human', 'Warrior', 80, [], $talents);

        self::assertSame(0, $result['ratings']['armor_penetration_rating']['rating']);
        self::assertSame(10.0, $result['ratings']['armor_penetration_rating']['flatBonusPercent']);
        self::assertSame(10.0, $result['ratings']['armor_penetration_rating']['value']);
        self::assertSame('Battle Stance', $result['assumedEffects'][0]['name']);
    }

    public function testGlobalCritTalentsUseTheirAllocatedRankValues(): void
    {
        $talents = ['0' => [[
            'name' => 'Protection',
            'tiers' => [[
                ['spellId' => 20119, 'pointsText' => '3/5'],
                ['spellId' => 31860, 'pointsText' => '3/3'],
            ]],
        ]]];

        $result = (new CharacterStatCalculator())->calculate('Human', 'Paladin', 80, [], $talents);

        self::assertSame(9.0, $result['ratings']['melee_crit_rating']['flatBonusPercent']);
        self::assertSame(9.0, $result['ratings']['spell_crit_rating']['flatBonusPercent']);
        self::assertSame(6.0, $result['primary']['stamina']['talentPercent']);
        self::assertSame(['Conviction', 'Combat Expertise'], array_column($result['talentModifiers'], 'name'));
    }
}
