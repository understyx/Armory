<?php

namespace App\Tests\Service;

use App\Service\TalentTreeService;
use PHPUnit\Framework\TestCase;

class TalentTreeServiceTest extends TestCase
{
    public function testIsWotlkServerWithWotlkRealms(): void
    {
        $service = new TalentTreeService();

        $this->assertTrue($service->isWotlkServer('Icecrown'));
        $this->assertTrue($service->isWotlkServer('lordaeron'));
        $this->assertTrue($service->isWotlkServer('Frostmourne'));
        $this->assertTrue($service->isWotlkServer('Blackrock'));
    }

    public function testIsWotlkServerWithGlyphs(): void
    {
        $service = new TalentTreeService();
        $glyphs = [
            '0' => [
                'Major Glyphs' => ['Glyph of Seal of Vengeance'],
                'Minor Glyphs' => ['Glyph of Lay on Hands'],
            ]
        ];

        $this->assertTrue($service->isWotlkServer('UnknownRealm', $glyphs));
    }

    public function testIsWotlkServerReturnsFalseForOtherRealmsWithoutGlyphs(): void
    {
        $service = new TalentTreeService();

        $this->assertFalse($service->isWotlkServer('OnyxiaVanilla', []));
    }

    public function testParseTalentTreesForPaladin(): void
    {
        $service = new TalentTreeService();
        $specializations = ['Protection (51/5/15)', 'Holy (51/20/0)'];
        $talentStrings = ['0' => '505000111', '1' => '000555'];

        $parsed = $service->parseTalentTrees('Paladin', $specializations, $talentStrings);

        $this->assertCount(2, $parsed);

        $primary = $parsed[0];
        $this->assertEquals('Protection', $primary['specName']);
        $this->assertEquals('51 / 5 / 15', $primary['pointsSummary']);
        $this->assertEquals(71, $primary['totalPoints']);
        $this->assertCount(3, $primary['trees']);
        $this->assertEquals('Holy', $primary['trees'][0]['name']);
        $this->assertEquals(51, $primary['trees'][0]['points']);
        $this->assertEquals('Protection', $primary['trees'][1]['name']);
        $this->assertEquals(5, $primary['trees'][1]['points']);
        $this->assertEquals('Retribution', $primary['trees'][2]['name']);
        $this->assertEquals(15, $primary['trees'][2]['points']);

        $secondary = $parsed[1];
        $this->assertEquals('Holy', $secondary['specName']);
        $this->assertEquals('51 / 20 / 0', $secondary['pointsSummary']);
        $this->assertEquals(71, $secondary['totalPoints']);
    }

    public function testParseTalentTreesWithTalentTreesData(): void
    {
        $service = new TalentTreeService();
        $talentTreesData = [
            '0' => [
                ['name' => 'Discipline', 'points' => 18, 'tiers' => []],
                ['name' => 'Holy', 'points' => 2, 'tiers' => []],
                ['name' => 'Shadow', 'points' => 51, 'tiers' => []],
            ]
        ];

        $parsed = $service->parseTalentTrees('Priest', [], [], $talentTreesData);

        $this->assertCount(1, $parsed);
        $this->assertEquals('Shadow', $parsed[0]['specName']);
        $this->assertEquals('18 / 2 / 51', $parsed[0]['pointsSummary']);
        $this->assertEquals(71, $parsed[0]['totalPoints']);
        $this->assertCount(3, $parsed[0]['trees']);
    }

    public function testWarmaneTalentIconsReceiveLocalFallbackUrls(): void
    {
        $service = new TalentTreeService();
        $talentTreesData = [
            '0' => [[
                'name' => 'Holy',
                'points' => 51,
                'iconUrl' => 'https://cdn.warmane.com/wotlk/icons/small/spell_holy_holybolt.jpg',
                'tiers' => [[[
                    'iconUrl' => 'https://cdn.warmane.com/wotlk/icons/medium/spell_holy_powerwordshield.jpg',
                ]]],
            ]],
        ];

        $parsed = $service->parseTalentTrees('Paladin', [], [], $talentTreesData);
        $tree = $parsed[0]['trees'][0];

        self::assertSame(
            'https://cdn.warmane.com/wotlk/icons/small/spell_holy_holybolt.jpg',
            $tree['iconUrl']
        );
        self::assertSame('/wow-icons/large/spell_holy_holybolt.jpg', $tree['iconFallbackUrl']);
        self::assertSame(
            '/wow-icons/large/spell_holy_powerwordshield.jpg',
            $tree['tiers'][0][0]['iconFallbackUrl']
        );
    }
}
