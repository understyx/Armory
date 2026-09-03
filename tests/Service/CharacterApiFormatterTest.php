<?php

namespace App\Tests\Service;

use App\Entity\CharacterSnapshot;
use App\Entity\UwuLogRank;
use App\Service\ArmoryScraperService;
use App\Service\CharacterApiFormatter;
use App\Service\CharacterStatCalculator;
use PHPUnit\Framework\TestCase;

class CharacterApiFormatterTest extends TestCase
{
    public function testItBuildsThePublicApiContract(): void
    {
        $snapshot = (new CharacterSnapshot())
            ->setName('Understyx')
            ->setRealm('Icecrown')
            ->setLevel(80)
            ->setRace('Human')
            ->setClass('Death Knight')
            ->setGuild('Example Guild')
            ->setGearScore(6123)
            ->setAvgIlvl(271.4)
            ->setCharacterModel(['gender' => 0])
            ->setEquippedItems([[
                'id' => 50735,
                'transmog' => 1234,
                'enchant' => 3795,
                'gems' => [40111, 40112],
                'tooltip' => ['stats' => [
                    ['type' => 31, 'value' => 328],
                    ['type' => 37, 'value' => 82],
                    ['type' => 38, 'value' => 200],
                    ['type' => 45, 'value' => 150],
                ]],
            ]])
            ->setProfessions(['Jewelcrafting (450 / 450)', 'Cooking (410)'])
            ->setSpecializations(['Unholy (0 / 17 / 54)', 'Frost (0 / 53 / 18)'])
            ->setTalentStrings(['012345', '543210'])
            ->setTalentTreesData([
                '0' => [[
                    'name' => 'Unholy',
                    'tiers' => [[
                        ['spellId' => 49568, 'pointsText' => '3/3'],
                        ['spellId' => 49480, 'pointsText' => '5/5'],
                    ]],
                ]],
                '1' => [],
            ])
            ->setRaidAchievements([
                '15041' => ['4583' => '11/02/2020'],
            ])
            ->setScrapedAt(new \DateTimeImmutable('2026-08-23T10:15:00+00:00'));
        $uwuRank = (new UwuLogRank())
            ->setName('Understyx')
            ->setRealm('Icecrown')
            ->setSpec('3')
            ->setOverallRank(1525)
            ->setPayload(['overallPoints' => 87.25])
            ->setScrapedAt(new \DateTimeImmutable('2026-08-23T10:16:00+00:00'));

        $formatter = new CharacterApiFormatter(new CharacterStatCalculator(), new ArmoryScraperService());
        $payload = $formatter->format($snapshot, $uwuRank);

        self::assertSame('2026-08-23T10:15:00+00:00', $payload['updatedAt']);
        self::assertSame([
            'name' => 'Understyx',
            'realm' => 'Icecrown',
            'level' => 80,
            'gender' => 'Male',
            'race' => 'Human',
            'class' => 'Death Knight',
            'guild' => 'Example Guild',
            'gearScore' => 6123,
            'averageItemLevel' => 271.4,
        ], $payload['character']);
        self::assertSame([
            'itemId' => 50735,
            'transmogId' => 1234,
            'enchantId' => 3795,
            'gemId1' => 40111,
            'gemId2' => 40112,
            'gemId3' => null,
        ], $payload['items'][0]);
        self::assertSame([
            ['professionName' => 'Jewelcrafting', 'skill' => 450, 'maxSkill' => 450],
            ['professionName' => 'Cooking', 'skill' => 410, 'maxSkill' => null],
        ], $payload['professions']);
        self::assertSame([
            'spec1' => ['name' => 'Unholy', 'talentString' => '012345'],
            'spec2' => ['name' => 'Frost', 'talentString' => '543210'],
        ], $payload['talents']);
        self::assertSame(10.0, $payload['stats']['spec1']['hit']['gearPercent']['melee']);
        self::assertSame(12.5, $payload['stats']['spec1']['hit']['gearPercent']['spell']);
        self::assertSame(3.0, $payload['stats']['spec1']['hit']['talentPercent']['Spells']);
        self::assertSame(10.0, $payload['stats']['spec1']['expertise']['gearPoints']);
        self::assertSame(2.5, $payload['stats']['spec1']['expertise']['gearReductionPercent']);
        self::assertSame(1, $payload['achievements']['earnedCount']);
        self::assertSame('/api/character/Understyx/Icecrown/achievements', $payload['achievements']['endpoint']);
        self::assertSame([
            'overallRank' => 1525,
            'overallPoints' => 87.25,
            'specId' => '3',
            'specName' => 'Unholy',
            'updatedAt' => '2026-08-23T10:16:00+00:00',
        ], $payload['uwuLogs']);

        $details = $formatter->formatDetailedStats($snapshot);
        self::assertSame(1, $details['stats']['spec1']['loadout']);
        self::assertSame(175, $details['stats']['spec1']['primary']['strength']);
        self::assertSame(328, $details['stats']['spec1']['ratings']['melee_hit_rating']['rating']);
        self::assertSame(10.0, $details['stats']['spec1']['ratings']['melee_hit_rating']['percent']);
        self::assertArrayNotHasKey('flatTalentPercent', $details['stats']['spec1']['ratings']['melee_hit_rating']);
        self::assertSame(250, $details['stats']['spec1']['attackPower']);
        self::assertSame(150, $details['stats']['spec1']['spellPower']);
        self::assertSame(['spells' => 3.0], $details['stats']['spec1']['talentHitPercent']);
        self::assertArrayNotHasKey('gearBreakdown', $details['stats']['spec1']);

        $achievements = $formatter->formatAchievements($snapshot);
        self::assertTrue($achievements['available']);
        self::assertFalse($achievements['complete']);
        self::assertSame(1, $achievements['earnedCount']);
        self::assertSame('ICC + RS', $achievements['groups'][0]['title']);
    }
}
