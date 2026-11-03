<?php

namespace App\Tests\Service;

use App\Service\CharacterHitExpertiseService;
use App\Service\CharacterStatCalculator;
use PHPUnit\Framework\TestCase;

final class CharacterHitExpertiseServiceTest extends TestCase
{
    public function testItMapsMeleeRangedCasterAndHidesHealerSpecs(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());

        $melee = $service->build('Human', 'Warrior', 80, [$this->spec('Arms')], $this->weapons(), $this->talents());
        $ranged = $service->build('Human', 'Hunter', 80, [$this->spec('Marksmanship')], $this->rangedWeapon(), $this->talents());
        $caster = $service->build('Human', 'Mage', 80, [$this->spec('Arcane')], $this->weapons(), $this->talents());
        $healer = $service->build('Human', 'Priest', 80, [$this->spec('Holy')], $this->weapons(), $this->talents());

        self::assertSame('melee', $melee[0]['role']);
        self::assertSame('Melee Hit', $melee[0]['hitLabel']);
        self::assertSame(8.0, $melee[0]['hitTarget']);
        self::assertSame(0.0, $melee[0]['hitRaidDebuff']);
        self::assertSame(0.0, $melee[0]['hitValue']);
        self::assertFalse($melee[0]['hitMet']);
        self::assertSame('ranged', $ranged[0]['role']);
        self::assertSame('Ranged Hit', $ranged[0]['hitLabel']);
        self::assertSame(8.0, $ranged[0]['hitTarget']);
        self::assertSame('caster', $caster[0]['role']);
        self::assertSame('Spell Hit', $caster[0]['hitLabel']);
        self::assertSame(17.0, $caster[0]['hitTarget']);
        self::assertSame(3.0, $caster[0]['hitRaidDebuff']);
        self::assertSame([], $healer);
    }

    public function testCasterRaidDebuffSetsFourteenPercentHitTargetAndBoundary(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $talents = $this->talents([
            ['spellId' => 29440, 'pointsText' => '3/3'], // Mage Precision: 3% to all spells.
            ['spellId' => 12840, 'pointsText' => '1/3'], // Arcane Focus: 1% to Arcane spells.
        ]);
        $weapon = $this->weapons();

        $met = $service->build('Draenei', 'Mage', 80, [$this->spec('Arcane')], [[
            ...$weapon[0],
            'tooltip' => ['stats' => [['type' => 31, 'value' => 236]]],
        ]], $talents);
        $unmet = $service->build('Human', 'Mage', 80, [$this->spec('Arcane')], [[
            ...$weapon[0],
            'tooltip' => ['stats' => [['type' => 31, 'value' => 262]]],
        ]], $talents);

        self::assertSame(14.0, $met[0]['hitValue']);
        self::assertSame(17.0, $met[0]['hitTarget']);
        self::assertTrue($met[0]['hitMet']);
        self::assertSame(13.99, $unmet[0]['hitValue']);
        self::assertSame(17.0, $unmet[0]['hitTarget']);
        self::assertFalse($unmet[0]['hitMet']);
        self::assertStringContainsString(
            '+3% is Misery or Improved Faerie Fire on the target; they do not stack.',
            $met[0]['hitTooltip'],
        );
    }

    public function testNoSectionForLowLevelMissingTalentsNoWeaponsOrUnknownSpec(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $specs = [$this->spec('Arms')];

        self::assertSame([], $service->build('Human', 'Warrior', 79, $specs, $this->weapons(), $this->talents()));
        self::assertSame([], $service->build('Human', 'Warrior', 80, $specs, $this->weapons(), []));
        self::assertSame([], $service->build('Human', 'Warrior', 80, $specs, [], $this->talents()));
        self::assertSame([], $service->build('Human', 'Warrior', 80, [$this->spec('Unknown')], $this->weapons(), $this->talents()));
    }

    public function testItShowsMeleeAndSpellHitFromHandCalculatedLevel80Rating(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $meleeGear = [[
            'class' => 2,
            'type' => 13,
            'subclass' => 7,
            'tooltip' => ['stats' => [['type' => 31, 'value' => 263]]],
        ]];
        $casterGear = [[
            'class' => 2,
            'type' => 17,
            'subclass' => 10,
            'tooltip' => ['stats' => [['type' => 31, 'value' => 446]]],
        ]];

        $melee = $service->build('Human', 'Warrior', 80, [$this->spec('Arms')], $meleeGear, $this->talents());
        $spell = $service->build('Human', 'Mage', 80, [$this->spec('Fire')], $casterGear, $this->talents());

        self::assertSame(8.02, $melee[0]['hitValue']);
        self::assertSame(8.0, $melee[0]['hitTarget']);
        self::assertSame(0.0, $melee[0]['hitRaidDebuff']);
        self::assertTrue($melee[0]['hitMet']);
        self::assertSame(17.0, $spell[0]['hitValue']);
        self::assertSame(17.0, $spell[0]['hitTarget']);
        self::assertTrue($spell[0]['hitMet']);
    }

    public function testWarriorPrecisionAppliesToMeleeHitAndNormalizesItsRankSpellId(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $talents = $this->talents([['spellId' => 29592, 'pointsText' => '3/3']]);

        $rows = $service->build('Human', 'Warrior', 80, [$this->spec('Arms')], $this->weapons(), $talents);

        self::assertSame(3.0, $rows[0]['hitValue']);
    }

    public function testExistingClassHitTalentsUseTheir335aRankValuesAndScopes(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $oneHandedWeapons = [[
            'class' => 2,
            'type' => 13,
            'subclass' => 4,
            'tooltip' => ['stats' => []],
        ]];
        $cases = [
            ['Warrior', 'Arms', 29592, '3/3', 3.0, $this->weapons()],
            ['Rogue', 'Combat', 13845, '5/5', 5.0, $oneHandedWeapons],
            ['Hunter', 'Marksmanship', 53622, '3/3', 3.0, $this->rangedWeapon()],
            ['Death Knight', 'Frost', 50138, '3/3', 3.0, $oneHandedWeapons],
            ['Mage', 'Fire', 29440, '3/3', 3.0, $this->weapons()],
            ['Priest', 'Shadow', 15328, '3/3', 3.0, $this->weapons()],
            ['Shaman', 'Elemental', 30674, '3/3', 3.0, $this->weapons()],
            ['Druid', 'Balance', 33596, '2/2', 4.0, $this->weapons()],
        ];

        foreach ($cases as [$class, $specName, $spellId, $pointsText, $expectedHit, $items]) {
            $rows = $service->build(
                'Human',
                $class,
                80,
                [$this->spec($specName)],
                $items,
                $this->talents([['spellId' => $spellId, 'pointsText' => $pointsText]]),
            );

            self::assertSame($expectedHit, $rows[0]['hitValue'], sprintf('%s %s talent spell %d', $class, $specName, $spellId));
        }
    }

    public function testUnleashedRageAddsExpertiseForEveryDatabaseRankAlias(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());

        foreach ([[30802, 1], [30808, 2], [30809, 3]] as [$spellId, $rank]) {
            $rows = $service->build(
                'Troll',
                'Shaman',
                80,
                [$this->spec('Enhancement')],
                $this->weapons(),
                $this->talents([['spellId' => $spellId, 'pointsText' => sprintf('%d/3', $rank)]]),
            );

            self::assertSame($rank * 3, $rows[0]['expertise'], sprintf('Unleashed Rage rank %d spell %d', $rank, $spellId));
        }
    }

    public function testVeteranOfTheThirdWarAddsExpertiseForDatabaseAndArmoryRankAliases(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $rankSpellIds = [
            [49471, 1], [49472, 2], [49473, 3], // Armory talent links
            [49006, 1], [49526, 2], [50029, 3], // 3.3.5a rank effects
        ];

        foreach ($rankSpellIds as [$spellId, $rank]) {
            $rows = $service->build(
                'Human',
                'Death Knight',
                80,
                [$this->spec('Blood')],
                $this->weapons(),
                $this->talents([['spellId' => $spellId, 'pointsText' => sprintf('%d/3', $rank)]]),
            );

            self::assertSame($rank * 2, $rows[0]['expertise'], sprintf('Veteran rank %d spell %d', $rank, $spellId));
        }
    }

    public function testTundraStalkerAddsUnconditionalExpertiseForEveryRankAlias(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());

        foreach ([[49202, 1], [50127, 2], [50128, 3], [50129, 4], [50130, 5]] as [$spellId, $rank]) {
            $rows = $service->build(
                'Human',
                'Death Knight',
                80,
                [$this->spec('Frost')],
                $this->weapons(),
                $this->talents([['spellId' => $spellId, 'pointsText' => sprintf('%d/5', $rank)]]),
            );

            self::assertSame($rank, $rows[0]['expertise'], sprintf('Tundra Stalker rank %d spell %d', $rank, $spellId));
            self::assertStringContainsString('damage bonuses with target conditions are excluded', strtolower($rows[0]['expertiseTooltip']));
        }
    }

    public function testRageOfRivendareAddsUnconditionalExpertiseForEveryRankAlias(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());

        foreach ([[50117, 1], [50118, 2], [50119, 3], [50120, 4], [50121, 5]] as [$spellId, $rank]) {
            $rows = $service->build(
                'Human',
                'Death Knight',
                80,
                [$this->spec('Unholy')],
                $this->weapons(),
                $this->talents([['spellId' => $spellId, 'pointsText' => sprintf('%d/5', $rank)]]),
            );

            self::assertSame($rank, $rows[0]['expertise'], sprintf('Rage of Rivendare rank %d spell %d', $rank, $spellId));
        }
    }

    public function testWorkedLevel80FrostDeathKnightAndEnhancementShamanExpertiseTotals(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $frostDeathKnightGear = [[
            'class' => 2,
            'type' => 17,
            'subclass' => 10,
            'tooltip' => ['stats' => [['type' => 37, 'value' => 173]]],
        ]];
        $enhancementGear = [
            ['class' => 2, 'type' => 13, 'subclass' => 4, 'tooltip' => ['stats' => [['type' => 37, 'value' => 73]]]],
            ['class' => 2, 'type' => 13, 'subclass' => 4, 'tooltip' => ['stats' => [['type' => 37, 'value' => 73]]]],
        ];

        $frostDeathKnight = $service->build(
            'Human',
            'Death Knight',
            80,
            [$this->spec('Frost')],
            $frostDeathKnightGear,
            $this->talents([['spellId' => 50130, 'pointsText' => '5/5']]),
        );
        $enhancement = $service->build(
            'Troll',
            'Shaman',
            80,
            [$this->spec('Enhancement')],
            $enhancementGear,
            $this->talents([['spellId' => 30809, 'pointsText' => '3/3']]),
        );

        // 173 / 8.197497 = 21 whole expertise; Tundra Stalker adds 5.
        self::assertSame(26, $frostDeathKnight[0]['expertise']);
        self::assertTrue($frostDeathKnight[0]['expertiseTargets'][0]['met']);
        // 146 / 8.197497 = 17 whole expertise; Unleashed Rage adds 9.
        self::assertSame(26, $enhancement[0]['expertise']);
        self::assertTrue($enhancement[0]['expertiseTargets'][0]['met']);
    }

    public function testExpertiseCapsReportMetAndUnmetValuesIncludingTalentAndWeaponRacial(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $gear = [[
            'class' => 2,
            'type' => 13,
            'subclass' => 7,
            'tooltip' => ['stats' => [['type' => 37, 'value' => 214]]],
        ]];
        $talents = $this->talents([['spellId' => 29144, 'pointsText' => '3/3']]);

        $rows = $service->build('Human', 'Warrior', 80, [$this->spec('Protection')], $gear, $talents);

        self::assertSame(35, $rows[0]['expertise']);
        self::assertSame(26, $rows[0]['expertiseTargets'][0]['value']);
        self::assertTrue($rows[0]['expertiseTargets'][0]['met']);
        self::assertSame(56, $rows[0]['expertiseTargets'][1]['value']);
        self::assertFalse($rows[0]['expertiseTargets'][1]['met']);

        $hardCappedGear = [[
            'class' => 2,
            'type' => 13,
            'subclass' => 7,
            'tooltip' => ['stats' => [['type' => 37, 'value' => 460]]],
        ]];
        $hardCapped = $service->build('Human', 'Warrior', 80, [$this->spec('Protection')], $hardCappedGear, $this->talents());
        self::assertSame(59, $hardCapped[0]['expertise']);
        self::assertTrue($hardCapped[0]['expertiseTargets'][1]['met']);
    }

    public function testPersonalDraeneiHitAndWeaponSpecificExpertiseRacialsAreIncluded(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $mace = [['class' => 2, 'type' => 13, 'subclass' => 4, 'tooltip' => ['stats' => []]]];
        $sword = [['class' => 2, 'type' => 13, 'subclass' => 7, 'tooltip' => ['stats' => []]]];
        $axe = [['class' => 2, 'type' => 13, 'subclass' => 0, 'tooltip' => ['stats' => []]]];

        $draenei = $service->build('Draenei', 'Warrior', 80, [$this->spec('Arms')], $sword, $this->talents());
        $humanMace = $service->build('Human', 'Warrior', 80, [$this->spec('Arms')], $mace, $this->talents());
        $dwarfMace = $service->build('Dwarf', 'Warrior', 80, [$this->spec('Arms')], $mace, $this->talents());
        $orcAxe = $service->build('Orc', 'Warrior', 80, [$this->spec('Arms')], $axe, $this->talents());

        self::assertSame(1.0, $draenei[0]['hitValue']);
        self::assertSame(3, $humanMace[0]['expertise']);
        self::assertSame(5, $dwarfMace[0]['expertise']);
        self::assertSame(5, $orcAxe[0]['expertise']);
    }

    public function testMageHitTalentsAreScopedToTheSpecSchool(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $talents = $this->talents([
            ['spellId' => 12840, 'pointsText' => '3/3'], // Arcane Focus
            ['spellId' => 29440, 'pointsText' => '3/3'], // Precision
        ]);

        $fire = $service->build('Human', 'Mage', 80, [$this->spec('Fire')], $this->weapons(), $talents);
        $arcane = $service->build('Human', 'Mage', 80, [$this->spec('Arcane')], $this->weapons(), $talents);

        self::assertSame(3.0, $fire[0]['hitValue']);
        self::assertStringContainsString('Fire specialization spells', $fire[0]['hitTooltip']);
        self::assertSame(6.0, $arcane[0]['hitValue']);
        self::assertStringContainsString('Arcane specialization spells', $arcane[0]['hitTooltip']);
    }

    public function testBothSpecsAreKeptAndWarlockSuppressionAppliesToAllSpellSpecs(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $specs = [$this->spec('Destruction', '0'), $this->spec('Affliction', '1')];
        $talents = [
            ...$this->talents([['spellId' => 18176, 'pointsText' => '3/3']]),
            '1' => [[
                'name' => 'Test Tree',
                'points' => 71,
                'tiers' => [[['spellId' => 18176, 'pointsText' => '3/3']]],
            ]],
        ];

        $rows = $service->build('Human', 'Warlock', 80, $specs, $this->weapons(), $talents);

        self::assertCount(2, $rows);
        self::assertSame(['Destruction', 'Affliction'], array_column($rows, 'specName'));
        self::assertSame([3.0, 3.0], array_column($rows, 'hitValue'));
        self::assertStringContainsString('Fire', $rows[0]['hitTooltip']);
        self::assertStringContainsString('Shadow', $rows[1]['hitTooltip']);
    }

    public function testShamanDualWieldHitOnlyCountsWhenEnhancementHasTwoOneHandedWeapons(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $talents = $this->talents([['spellId' => 30819, 'pointsText' => '3/3']]);
        $twoWeapons = [
            ['class' => 2, 'type' => 13, 'subclass' => 0, 'tooltip' => ['stats' => []]],
            ['class' => 2, 'type' => 13, 'subclass' => 0, 'tooltip' => ['stats' => []]],
        ];

        $enhancement = $service->build('Orc', 'Shaman', 80, [$this->spec('Enhancement')], $twoWeapons, $talents);
        $oneWeapon = $service->build('Orc', 'Shaman', 80, [$this->spec('Enhancement')], [$twoWeapons[0]], $talents);

        self::assertSame(6.0, $enhancement[0]['hitValue']);
        self::assertSame(0.0, $oneWeapon[0]['hitValue']);
    }

    /** @return array<string, mixed> */
    public function testPaladinSealOfVengeanceGlyphAddsTenExpertise(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $glyphs = ['0' => ['Major Glyphs' => ['Glyph of Seal of Vengeance'], 'Minor Glyphs' => []]];

        $glyphed = $service->build('Human', 'Paladin', 80, [$this->spec('Retribution')], $this->weapons(), $this->talents(), $glyphs);
        $unglyphed = $service->build('Human', 'Paladin', 80, [$this->spec('Retribution')], $this->weapons(), $this->talents());
        $warrior = $service->build('Human', 'Warrior', 80, [$this->spec('Arms')], $this->weapons(), $this->talents(), $glyphs);

        self::assertSame($unglyphed[0]['expertise'] + 10, $glyphed[0]['expertise']);
        self::assertSame(0, $warrior[0]['expertise']);
    }

    public function testTankSpecsShowTauntHitWithGlyph(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $gear = [[
            'class' => 2,
            'type' => 17,
            'subclass' => 10,
            'tooltip' => ['stats' => [['type' => 31, 'value' => 236]]],
        ]];
        $glyphs = ['0' => ['Major Glyphs' => ['Glyph of Righteous Defense'], 'Minor Glyphs' => []]];

        $glyphed = $service->build('Draenei', 'Paladin', 80, [$this->spec('Protection')], $gear, $this->talents(), $glyphs);
        $unglyphed = $service->build('Human', 'Warrior', 80, [$this->spec('Protection')], $gear, $this->talents(), $glyphs);
        $dps = $service->build('Human', 'Warrior', 80, [$this->spec('Arms')], $gear, $this->talents());

        // 236 hit rating is 9% spell hit at level 80; Draenei adds 1%.
        $taunt = $glyphed[0]['spellHitChecks'][0];
        self::assertSame('Taunt Hit (Spell Hit)', $taunt['label']);
        self::assertSame(10.0, $taunt['value']);
        self::assertSame('Glyph of Righteous Defense', $taunt['bonusLabel']);
        self::assertSame(8.0, $taunt['bonus']);
        self::assertSame(17.0, $taunt['target']);
        self::assertTrue($taunt['met']);
        self::assertSame(['Gear', 'Racial', 'Glyph'], array_column($taunt['sources'], 'category'));
        self::assertNull($unglyphed[0]['spellHitChecks'][0]['bonusLabel']);
        self::assertSame(0.0, $unglyphed[0]['spellHitChecks'][0]['bonus']);
        self::assertFalse($unglyphed[0]['spellHitChecks'][0]['met']);
        self::assertSame([], $dps[0]['spellHitChecks']);
    }

    public function testRoguePoisonAndDeathKnightDiseaseHitUseSpellHit(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $rogue = $service->build('Human', 'Rogue', 80, [$this->spec('Combat')], $this->weapons(), $this->talents([
            ['spellId' => 13845, 'pointsText' => '5/5'], // Precision: weapons and poisons.
        ]));
        $deathKnight = $service->build('Human', 'Death Knight', 80, [$this->spec('Blood')], $this->weapons(), $this->talents([
            ['spellId' => 49568, 'pointsText' => '3/3'], // Virulence: spells.
        ]));

        self::assertSame('Poison Hit (Spell Hit)', $rogue[0]['spellHitChecks'][0]['label']);
        self::assertSame(5.0, $rogue[0]['spellHitChecks'][0]['value']);
        self::assertSame(5.0, $rogue[0]['hitValue']);
        self::assertSame(
            ['Disease Hit (Spell Hit)', 'Taunt Hit (Spell Hit)'],
            array_column($deathKnight[0]['spellHitChecks'], 'label'),
        );
        self::assertSame(3.0, $deathKnight[0]['spellHitChecks'][0]['value']);
        self::assertSame(0.0, $deathKnight[0]['hitValue']);
    }

    public function testMetricsListContributionSources(): void
    {
        $service = new CharacterHitExpertiseService(new CharacterStatCalculator());
        $gear = [[
            'class' => 2,
            'type' => 17,
            'subclass' => 7,
            'tooltip' => ['stats' => [['type' => 37, 'value' => 82]]],
        ]];
        $glyphs = ['0' => ['Major Glyphs' => ['Glyph of Seal of Vengeance'], 'Minor Glyphs' => []]];
        $rows = $service->build('Human', 'Paladin', 80, [$this->spec('Retribution')], $gear, $this->talents([
            ['spellId' => 31860, 'pointsText' => '3/3'], // Combat Expertise.
        ]), $glyphs);

        $expertise = $rows[0]['metrics'][1];
        self::assertSame('Expertise', $expertise['label']);
        self::assertSame([
            ['category' => 'Gear', 'label' => 'Expertise rating (82)', 'value' => 10],
            ['category' => 'Talents', 'label' => 'Combat Expertise', 'value' => 6],
            ['category' => 'Racial', 'label' => 'Sword Specialization', 'value' => 3],
            ['category' => 'Glyph', 'label' => 'Glyph of Seal of Vengeance', 'value' => 10],
        ], $expertise['sources']);
        self::assertSame(29, $rows[0]['expertise']);
    }

    private function spec(string $name, string $id = '0'): array
    {
        return [
            'specId' => $id,
            'label' => $id === '0' ? 'Primary Spec' : 'Secondary Spec',
            'specName' => $name,
            'totalPoints' => 71,
        ];
    }

    /** @param list<array<string, mixed>> $selectedTalents @return array<int, array<string, mixed>> */
    private function talents(array $selectedTalents = []): array
    {
        if ($selectedTalents === []) {
            $selectedTalents = [['spellId' => 990001, 'pointsText' => '1/1']];
        }

        return ['0' => [[
            'name' => 'Test Tree',
            'points' => 71,
            'tiers' => [$selectedTalents],
        ]]];
    }

    /** @return list<array<string, mixed>> */
    private function weapons(): array
    {
        return [[
            'class' => 2,
            'type' => 17,
            'subclass' => 10,
            'tooltip' => ['stats' => []],
        ]];
    }

    /** @return list<array<string, mixed>> */
    private function rangedWeapon(): array
    {
        return [[
            'class' => 2,
            'type' => 15,
            'subclass' => 2,
            'tooltip' => ['stats' => []],
        ]];
    }
}
