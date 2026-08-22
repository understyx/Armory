<?php

namespace App\Tests\Service;

use App\Entity\CharacterSnapshot;
use App\Service\CharacterApiFormatter;
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
            ]])
            ->setProfessions(['Jewelcrafting (450 / 450)', 'Cooking (410)'])
            ->setSpecializations(['Unholy (0 / 17 / 54)', 'Frost (0 / 53 / 18)'])
            ->setTalentStrings(['012345', '543210'])
            ->setScrapedAt(new \DateTimeImmutable('2026-08-23T10:15:00+00:00'));

        $payload = (new CharacterApiFormatter())->format($snapshot);

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
    }
}
