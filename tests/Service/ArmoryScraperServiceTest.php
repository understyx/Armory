<?php

namespace App\Tests\Service;

use App\Enum\ItemTypes;
use App\Service\ArmoryScraperService;
use App\Service\ItemDatabaseService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ArmoryScraperServiceTest extends TestCase
{
    public function testCheckEnchantsIgnoresOffhandItemsButStillRequiresShieldEnchants(): void
    {
        $itemDatabase = $this->createMock(ItemDatabaseService::class);
        $itemDatabase->method('getItem')
            ->willReturnMap([
                [50005, ['name' => 'Talisman of Testing', 'type' => ItemTypes::OFF_HAND->value]],
                [50006, ['name' => 'Shield of Testing', 'type' => ItemTypes::SHIELD->value]],
            ]);

        $service = new ArmoryScraperService($itemDatabase);
        $status = $service->checkEnchants([
            ['id' => 50005, 'enchant' => null],
            ['id' => 50006, 'enchant' => null],
        ], 'Paladin', []);

        $this->assertSame('Enchants missing from: Shield of Testing ❌', $status);
    }

    public function testIsCloudflareBlock(): void
    {
        $service = new ArmoryScraperService();

        $this->assertTrue($service->isCloudflareBlock('<html><body>Error 1015: Rate limited</body></html>'));
        $this->assertTrue($service->isCloudflareBlock('<html><body>Cloudflare Access denied Ray ID: 87123681723</body></html>'));
        $this->assertTrue($service->isCloudflareBlock('You are being rate limited'));
        $this->assertFalse($service->isCloudflareBlock('<html><body><div class="character-details">Understyx</div></body></html>'));
    }

    public function testFetchWithRetryRetriesOnRateLimitAndSucceeds(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        $response429 = $this->createMock(ResponseInterface::class);
        $response429->method('getStatusCode')->willReturn(429);
        $response429->method('getContent')->willReturn('Error 1015');

        $response200 = $this->createMock(ResponseInterface::class);
        $response200->method('getStatusCode')->willReturn(200);
        $response200->method('getContent')->willReturn('<html><body><div class="character-details">Understyx</div></body></html>');

        $httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($response429, $response200);

        $service = new ArmoryScraperService(null, null, $httpClient);

        $sleptDelays = [];
        $result = $service->fetchWithRetry(
            'https://armory.warmane.com/character/Understyx/Icecrown/summary',
            [0, 0],
            function (int $delay) use (&$sleptDelays) {
                $sleptDelays[] = $delay;
            }
        );

        $this->assertNotNull($result);
        $this->assertStringContainsString('Understyx', $result);
        $this->assertCount(1, $sleptDelays);
    }

    public function testFetchAchievementCategoryPostsCategoryAndReturnsFragment(): void
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://armory.warmane.com/character/Puredecay/Icecrown/achievements', $url);
            self::assertStringContainsString('category=15041', $options['body']);

            return new MockResponse(json_encode(['content' => '<div class="achievement"></div>']));
        });

        $service = new ArmoryScraperService(null, null, $httpClient);

        self::assertSame(
            '<div class="achievement"></div>',
            $service->fetchAchievementCategoryHtml('puredecay', 'icecrown', 15041),
        );
    }

    public function testFetchAchievementCategoryRetriesUntilItSucceeds(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Error 1015', ['http_code' => 429]),
            new MockResponse(json_encode(['content' => '<div id="success"></div>'])),
        ]);
        $sleptDelays = [];

        $result = (new ArmoryScraperService(null, null, $httpClient))->fetchAchievementCategoryHtml(
            'Puredecay',
            'Icecrown',
            15041,
            [0],
            static function (int $delay) use (&$sleptDelays): void {
                $sleptDelays[] = $delay;
            },
        );

        self::assertSame('<div id="success"></div>', $result);
        self::assertSame([0], $sleptDelays);
    }

    public function testExtractRaidAchievementsKeepsOnlyConfiguredProgressionEntries(): void
    {
        $html = <<<'HTML'
            <div class="achievement" id="ach4531">
                <div class="points"><div>10</div></div>
                <div class="icon"><img src="http://cdn.warmane.com/wotlk/icons/large/lower-spire.jpg"></div>
                <div class="title">Storming the Citadel (10 player)</div>
                <div class="description">Defeat the first four bosses.</div>
                <div class="date">Earned 07/14/2018</div>
            </div>
            <div class="achievement locked" id="ach4583">
                <div class="points"><div>10</div></div>
                <div class="title">Bane of the Fallen King</div>
                <div class="description">Defeat the Lich King on Heroic.</div>
            </div>
            <div class="achievement" id="ach4534">
                <div class="title">Boned (10 player)</div>
            </div>
            HTML;

        $result = (new ArmoryScraperService())->extractRaidAchievements($html, 15041);

        self::assertSame(10, $result['raidSize']);
        self::assertSame(15041, $result['category']);
        self::assertCount(2, $result['achievements']);
        self::assertSame('icc_rs', $result['achievements'][0]['group']);
        self::assertSame('Icecrown Citadel', $result['achievements'][0]['raid']);
        self::assertSame('Lower Spire', $result['achievements'][0]['section']);
        self::assertSame('https://cdn.warmane.com/wotlk/icons/large/lower-spire.jpg', $result['achievements'][0]['iconUrl']);
        self::assertTrue($result['achievements'][0]['earned']);
        self::assertSame('07/14/2018', $result['achievements'][0]['earnedDate']);
        self::assertSame('heroic', $result['achievements'][1]['difficulty']);
        self::assertFalse($result['achievements'][1]['earned']);
        self::assertNull($result['achievements'][1]['earnedDate']);
    }

    public function testGroupRaidAchievementsHidesNormalClearWhenHeroicIsEarned(): void
    {
        $base = [
            'group' => 'icc_rs',
            'raid' => 'Icecrown Citadel',
            'section' => 'Lower Spire',
            'sort' => 10,
            'title' => 'Clear',
            'description' => '',
            'points' => 10,
            'iconUrl' => null,
            'earnedDate' => null,
        ];
        $results = [[
            'raidSize' => 10,
            'category' => 15041,
            'achievements' => [
                $base + ['id' => 4531, 'difficulty' => 'normal', 'earned' => true],
                $base + ['id' => 4628, 'difficulty' => 'heroic', 'earned' => true],
                array_merge($base, ['id' => 4528, 'section' => 'The Plagueworks', 'sort' => 20, 'difficulty' => 'normal', 'earned' => true]),
                array_merge($base, ['id' => 4629, 'section' => 'The Plagueworks', 'sort' => 20, 'difficulty' => 'heroic', 'earned' => false]),
            ],
        ]];

        $groups = (new ArmoryScraperService())->groupRaidAchievements($results);
        $iccTen = $groups[0]['raidSizes'][0]['achievements'];

        self::assertSame(['ICC + RS', 'ToGC', 'Ulduar', 'Naxx + EoE + OS'], array_column($groups, 'title'));
        self::assertSame([4628, 4528, 4629], array_column($iccTen, 'id'));
        self::assertNotContains(4531, array_column($iccTen, 'id'));
    }

    public function testExtractRaidAchievementsIncludesTogcTributesAndExcludesOnyxia(): void
    {
        $togcHtml = <<<'HTML'
            <div class="achievement" id="ach3918"><div class="title">Call of the Grand Crusade (10 player)</div><div class="description">Heroic clear.</div></div>
            <div class="achievement" id="ach3808"><div class="title">A Tribute to Skill (10 player)</div><div class="description">25 attempts.</div></div>
            <div class="achievement" id="ach3809"><div class="title">A Tribute to Mad Skill (10 player)</div><div class="description">45 attempts.</div></div>
            <div class="achievement locked" id="ach3810"><div class="title">A Tribute to Insanity (10 player)</div><div class="description">50 attempts.</div></div>
            HTML;
        $raidHtml = <<<'HTML'
            <div class="achievement" id="ach4396"><div class="title">Onyxia's Lair (10 player)</div><div class="description">Defeat Onyxia.</div></div>
            <div class="achievement" id="ach4817"><div class="title">The Twilight Destroyer (10 player)</div><div class="description">Defeat Halion.</div></div>
            HTML;

        $service = new ArmoryScraperService();
        $togc = $service->extractRaidAchievements($togcHtml, 15001);
        $raid = $service->extractRaidAchievements($raidHtml, 14922);

        self::assertSame([3918, 3808, 3809, 3810], array_column($togc['achievements'], 'id'));
        self::assertSame([4817], array_column($raid['achievements'], 'id'));
    }

    public function testExtractRaidAchievementsIncludesAloneInTheDarkness(): void
    {
        $html = <<<'HTML'
            <div class="achievement" id="ach3158"><div class="title">One Light in the Darkness (10 player)</div><div class="description">Use one keeper.</div></div>
            <div class="achievement locked" id="ach3159"><div class="title">Alone in the Darkness (10 player)</div><div class="description">Use no keepers.</div></div>
            HTML;

        $result = (new ArmoryScraperService())->extractRaidAchievements($html, 14961);

        self::assertSame([3159], array_column($result['achievements'], 'id'));
        self::assertSame('hard-mode', $result['achievements'][0]['difficulty']);
        self::assertSame('Alone in the Darkness (10 player)', $result['achievements'][0]['title']);
    }

    public function testBuildRaidAchievementsFromCacheRestoresOnlyEarnedIdsAndDates(): void
    {
        $result = (new ArmoryScraperService())->buildRaidAchievementsFromCache([
            '3918' => '07/14/2020',
            '3810' => '08/01/2020',
        ], 15001);
        $byId = array_column($result['achievements'], null, 'id');

        self::assertFalse($byId[3917]['earned']);
        self::assertTrue($byId[3918]['earned']);
        self::assertSame('07/14/2020', $byId[3918]['earnedDate']);
        self::assertTrue($byId[3810]['earned']);
        self::assertSame('08/01/2020', $byId[3810]['earnedDate']);
    }

    public function testExtractGuildSummaryParsesRosterAndMetadata(): void
    {
        $html = <<<'HTML'
            <div id="guild-sheet">
                <div class="information">
                    <div class="name">Cadence</div>
                    <div class="level-faction-realm">Horde Guild, Icecrown, 197 members<br>480 PVE Points</div>
                </div>
                <table id="data-table">
                    <thead><tr><th>Name</th><th>Race</th><th>Class</th><th>Faction</th><th>Level</th><th>Rank</th><th>Achievements Points</th><th>Professions</th></tr></thead>
                    <tbody id="data-table-list"><tr>
                        <td><a href="/character/Imtilted/Icecrown/profile">Imtilted</a></td>
                        <td><img alt="Blood Elf"></td><td><img alt="Paladin"></td><td><img alt="Horde"></td>
                        <td>80</td><td>Officer</td><td>1,695</td><td><img alt="Engineering"><img alt="Jewelcrafting"></td>
                    </tr></tbody>
                </table>
            </div>
            HTML;

        $service = new ArmoryScraperService();
        $summary = $service->extractGuildSummary($html);

        self::assertSame('Cadence', $summary['name']);
        self::assertSame('Horde', $summary['faction']);
        self::assertSame(197, $summary['memberCount']);
        self::assertSame(480, $summary['pvePoints']);
        self::assertSame('Imtilted', $summary['members'][0]['name']);
        self::assertSame('Paladin', $summary['members'][0]['class']);
        self::assertSame(1695, $summary['members'][0]['achievementPoints']);
        self::assertSame(['Engineering', 'Jewelcrafting'], $summary['members'][0]['professions']);
    }

    public function testCalculateGearScoreDefaultArguments(): void
    {
        $service = new ArmoryScraperService();
        $equippedItems = [
            ['id' => 12345, 'name' => 'Test Item'],
        ];

        // Should not throw ArgumentCountError when passed only 1 argument
        $gearScore = $service->calculateGearScore($equippedItems);
        $this->assertIsInt($gearScore);
    }

    public function testCalculateAvgIlvlDefaultArguments(): void
    {
        $service = new ArmoryScraperService();
        $equippedItems = [
            ['id' => 12345, 'name' => 'Test Item'],
        ];

        $avgIlvl = $service->calculateAvgIlvl($equippedItems);
        $this->assertIsFloat($avgIlvl);
    }

    public function testCalculateGearScoreAveragesTwoHandersForTitansGrip(): void
    {
        $service = new ArmoryScraperService();
        $equippedItems = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];
        $itemData = [
            1 => ['type' => ItemTypes::HEAD->value, 'gs' => 100],
            2 => ['type' => ItemTypes::WEAPON_2H->value, 'gs' => 300],
            3 => ['type' => ItemTypes::WEAPON_2H->value, 'gs' => 340],
        ];

        $this->assertSame(420, $service->calculateGearScore($equippedItems, $itemData));
    }

    public function testCalculateGearScoreSumsOrdinaryMainhandAndOffhandWeapons(): void
    {
        $service = new ArmoryScraperService();
        $equippedItems = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];
        $itemData = [
            1 => ['type' => ItemTypes::HEAD->value, 'gs' => 100],
            2 => ['type' => ItemTypes::WEAPON_MAINHAND->value, 'gs' => 300],
            3 => ['type' => ItemTypes::WEAPON_OFFHAND->value, 'gs' => 340],
        ];

        $this->assertSame(740, $service->calculateGearScore($equippedItems, $itemData));
    }

    public function testCalculateGearScoreFloorsTitansGripHalfPointLikeAddon(): void
    {
        $service = new ArmoryScraperService();
        $equippedItems = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];
        $itemData = [
            1 => ['type' => ItemTypes::HEAD->value, 'gs' => 100],
            2 => ['type' => ItemTypes::WEAPON_2H->value, 'gs' => 300],
            3 => ['type' => ItemTypes::WEAPON_2H->value, 'gs' => 341],
        ];

        $this->assertSame(420, $service->calculateGearScore($equippedItems, $itemData));
    }

    public function testCalculateGearScoreMatchesTfWarriorAddonResult(): void
    {
        $service = new ArmoryScraperService();
        $items = [
            [ItemTypes::HEAD->value, 531],
            [ItemTypes::NECK->value, 310],
            [ItemTypes::SHOULDER->value, 398],
            [ItemTypes::BACK->value, 290],
            [ItemTypes::CHEST->value, 531],
            [ItemTypes::WRIST->value, 310],
            [ItemTypes::GLOVES->value, 398],
            [ItemTypes::WAIST->value, 398],
            [ItemTypes::LEGS->value, 531],
            [ItemTypes::FEET->value, 413],
            [ItemTypes::RING->value, 298],
            [ItemTypes::RING->value, 298],
            [ItemTypes::TRINKET->value, 310],
            [ItemTypes::TRINKET->value, 298],
            [ItemTypes::WEAPON_2H->value, 1433],
            [ItemTypes::WEAPON_2H->value, 1103],
            [ItemTypes::RANGED->value, 174],
        ];
        $equippedItems = [];
        $itemData = [];

        foreach ($items as $index => [$type, $gearScore]) {
            $itemId = $index + 1;
            $equippedItems[] = ['id' => $itemId];
            $itemData[$itemId] = ['type' => $type, 'gs' => $gearScore];
        }

        $this->assertSame(6756, $service->calculateGearScore($equippedItems, $itemData));
    }

    public function testCalculateAvgIlvlAveragesTwoEquippedWeaponsAsOneSlot(): void
    {
        $service = new ArmoryScraperService();
        $equippedItems = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];
        $itemData = [
            1 => ['type' => ItemTypes::HEAD->value, 'ilvl' => 200],
            2 => ['type' => ItemTypes::WEAPON_MAINHAND->value, 'ilvl' => 264],
            3 => ['type' => ItemTypes::WEAPON_OFFHAND->value, 'ilvl' => 284],
        ];

        $this->assertSame(237.0, $service->calculateAvgIlvl($equippedItems, $itemData));
    }

    public function testExtractProfessions(): void
    {
        $service = new ArmoryScraperService();
        $html = '<div class="profskills">
            <div class="stub"><div class="text">Blacksmithing<span class="value">402 / 450</span></div></div>
            <div class="stub"><div class="text">Jewelcrafting<span class="value">394 / 450</span></div></div>
        </div>';

        $professions = $service->extractProfessions($html);
        $this->assertSame(['Blacksmithing (402 / 450)', 'Jewelcrafting (394 / 450)'], $professions);
    }

    public function testExtractSpecializations(): void
    {
        $service = new ArmoryScraperService();
        $html = '<div class="specialization">
            <div class="stub"><div class="text">Arms<span class="value">57 / 14 / 0</span></div></div>
            <div class="stub"><div class="text">Arms<span class="value">51 / 7 / 13</span></div></div>
        </div>';

        $specs = $service->extractSpecializations($html);
        $this->assertSame(['Arms (57 / 14 / 0)', 'Arms (51 / 7 / 13)'], $specs);
    }

    public function testExtractEquippedItemsData(): void
    {
        $service = new ArmoryScraperService();
        $html = '<div>
            <a href="http://wotlk.cavernoftime.com/item=51543" rel="item=51543&amp;ench=3795&amp;gems=3628:3530:0">Item Link</a>
        </div>';

        $items = $service->extractEquippedItemsData($html);
        $this->assertCount(1, $items);
        $this->assertSame(51543, $items[0]['id']);
        $this->assertSame(3795, $items[0]['enchant']);
        $this->assertSame([3628, 3530], $items[0]['gems']);
    }

    public function testExtractEquippedItemsDataPreservesEmptySocketBeforeGem(): void
    {
        $service = new ArmoryScraperService();
        $html = '<a rel="item=50001&amp;gems=0:3375:0">Item Link</a>';

        $items = $service->extractEquippedItemsData($html);

        $this->assertSame([0, 3375], $items[0]['gems']);
    }

    public function testCheckGemsTreatsPreservedZeroAsAnEmptySocket(): void
    {
        $itemDatabase = $this->createMock(\App\Service\ItemDatabaseService::class);
        $itemDatabase->method('getItem')->with(50001)->willReturn([
            'type' => ItemTypes::HEAD->value,
            'name' => 'Two Socket Helm',
            'gem_slots' => 2,
        ]);

        $service = new ArmoryScraperService($itemDatabase);

        $this->assertSame(
            'Gems missing from: Two Socket Helm ❌',
            $service->checkGems([['id' => 50001, 'gems' => [0, 3375]]])
        );
    }

    public function testCheckGemsReportsAnInactiveMetaGem(): void
    {
        $itemDatabase = $this->createMock(\App\Service\ItemDatabaseService::class);
        $itemDatabase->method('getItem')->with(50001)->willReturn([
            'type' => ItemTypes::HEAD->value,
            'name' => 'Meta Helm',
            'gem_slots' => 1,
        ]);

        $service = new ArmoryScraperService($itemDatabase);

        $this->assertSame(
            'Chaotic Skyflare Diamond inactive (requires 3 red; equipped: 0 red, 0 yellow, 0 blue) ❌',
            $service->checkGems([['id' => 50001, 'gems' => [3621]]])
        );
    }

    public function testCheckGemsAcceptsAnActiveMetaGem(): void
    {
        $itemDatabase = $this->createMock(\App\Service\ItemDatabaseService::class);
        $itemDatabase->method('getItem')->willReturnCallback(static fn (int $itemId): array => [
            'type' => $itemId === 50001 ? ItemTypes::HEAD->value : ItemTypes::RING->value,
            'name' => 'Gemmed Item',
            'gem_slots' => $itemId === 50001 ? 1 : 3,
        ]);

        $service = new ArmoryScraperService($itemDatabase);

        $this->assertSame(
            'All applicable items are gemmed! ✅',
            $service->checkGems([
                ['id' => 50001, 'gems' => [3621]],
                ['id' => 50002, 'gems' => [3477, 3464, 3371]],
            ])
        );
    }

    public function testExtractCharacterModelData(): void
    {
        $service = new ArmoryScraperService();
        $html = <<<'HTML'
            <script>
                var charactermodel = {
                    sk: 7,
                    ha: 2,
                    hc: 3,
                    fa: 4,
                    fh: 5,
                    items: [[1,63830],[3,64706],[13,30606],[23,53563]],
                    models: {
                        type: ModelViewer.Wow.Types.CHARACTER,
                        id: 'bloodelfmale'
                    }
                };
            </script>
            HTML;

        $this->assertSame([
            'race' => 10,
            'gender' => 0,
            'skin' => 7,
            'face' => 4,
            'hairStyle' => 2,
            'hairColor' => 3,
            'facialStyle' => 5,
            'items' => [[1, 63830], [3, 64706], [21, 30606], [22, 53563]],
        ], $service->extractCharacterModelData($html));
    }

    public function testExtractCharacterModelDataPreservesFemaleGender(): void
    {
        $service = new ArmoryScraperService();
        $html = <<<'HTML'
            <script>
                var charactermodel = {
                    sk: 2,
                    models: {
                        type: ModelViewer.Wow.Types.CHARACTER,
                        id: 'draeneifemale'
                    }
                };
            </script>
            HTML;

        $model = $service->extractCharacterModelData($html);

        $this->assertNotNull($model);
        $this->assertSame(11, $model['race']);
        $this->assertSame(1, $model['gender']);
    }

    public function testExtractCharacterModelDataReturnsNullWithoutModelConfig(): void
    {
        $service = new ArmoryScraperService();

        $this->assertNull($service->extractCharacterModelData('<html><body>No model</body></html>'));
    }

    public function testExtractGlyphs(): void
    {
        $service = new ArmoryScraperService();
        $html = '<div class="character-glyphs">
            <div data-glyphs="0">
                <div class="glyph major"><a href="#">Glyph of Rending</a></div>
                <div class="glyph minor"><a href="#">Glyph of Bloodrage</a></div>
            </div>
        </div>';

        $glyphs = $service->extractGlyphs($html);
        $this->assertArrayHasKey('0', $glyphs);
        $this->assertSame(['Glyph of Rending'], $glyphs['0']['Major Glyphs']);
        $this->assertSame(['Glyph of Bloodrage'], $glyphs['0']['Minor Glyphs']);
    }

    public function testExtractTalentPointsString(): void
    {
        $service = new ArmoryScraperService();
        $talentDivs = implode('', array_fill(0, 71, '<div class="talent-points max">5/5</div>'));
        $html = '<div class="talents-container" data-id="0">' . $talentDivs . '</div>';

        $talentStrings = $service->extractTalentPointsString($html, 'Warrior');
        $this->assertArrayHasKey('0', $talentStrings);
        $this->assertSame(str_repeat('5', 71), $talentStrings['0']);
    }

    public function testExtractTalentTrees(): void
    {
        $service = new ArmoryScraperService();
        $html = '<div class="talents-container" data-id="0">
            <div class="talent-frame">
                <div class="talent-tree-info" style="background: url(//cdn.warmane.com/wotlk/icons/small/spell_holy_holybolt.jpg) 0 0 no-repeat;">
                    <span>Discipline</span> <span>18</span>
                </div>
                <div class="tier">
                    <a href="//wotlk.cavernoftime.com/spell=20266" class="talent col1" style="background:url(//cdn.warmane.com/wotlk/icons/medium/spell_holy_powerwordshield.jpg);">
                        <div class="talent-points max">5/5</div>
                    </a>
                </div>
            </div>
        </div>';

        $treesData = $service->extractTalentTrees($html);
        $this->assertArrayHasKey('0', $treesData);
        $this->assertCount(1, $treesData['0']);
        $this->assertSame('Discipline', $treesData['0'][0]['name']);
        $this->assertSame(18, $treesData['0'][0]['points']);
        $this->assertCount(1, $treesData['0'][0]['tiers']);

        $talent = $treesData['0'][0]['tiers'][0][1];
        $this->assertNotNull($talent);
        $this->assertSame('20266', $talent['spellId']);
        $this->assertSame('5/5', $talent['pointsText']);
        $this->assertSame('max', $talent['status']);
    }

    public function testExtractPvpSummary(): void
    {
        $service = new ArmoryScraperService();
        $html = '<div class="pvpbasic">
            <div class="stub"><div class="text">Total Kills <span class="value">2178</span></div></div>
            <div class="stub"><div class="text">Kills Today <span class="value">5</span></div></div>
        </div>
        <div class="stub">
            <div class="rank">1</div>
            <div class="text">2v2 team <a href="/team/zzx/Icecrown/summary">zzx</a> <span class="value">2029 rating</span></div>
        </div>';

        $pvp = $service->extractPvpSummary($html);
        $this->assertSame(2178, $pvp['totalKills']);
        $this->assertSame(5, $pvp['killsToday']);
        $this->assertCount(1, $pvp['arenaTeams']);
        $this->assertSame('2v2', $pvp['arenaTeams'][0]['bracket']);
        $this->assertSame('zzx', $pvp['arenaTeams'][0]['name']);
        $this->assertSame(2029, $pvp['arenaTeams'][0]['rating']);
        $this->assertSame(1, $pvp['arenaTeams'][0]['rank']);
    }

    public function testExtractMatchHistory(): void
    {
        $service = new ArmoryScraperService();
        $html = '<table id="data-table-history">
            <tbody>
                <tr>
                    <td>12345</td>
                    <td><a href="/team/zzx/Icecrown/summary">zzx</a></td>
                    <td class="dt-center win">Win</td>
                    <td class="dt-center">2029 (+12)</td>
                    <td class="dt-center">2026-08-15 14:20:00</td>
                    <td class="dt-center">02:45</td>
                    <td class="dt-center">Nagrand Arena</td>
                    <td class="dt-center viewdetails" data-gameid="12345">Details</td>
                </tr>
            </tbody>
        </table>';

        $matches = $service->extractMatchHistory($html);
        $this->assertCount(1, $matches);
        $this->assertSame('12345', $matches[0]['gameId']);
        $this->assertSame('zzx', $matches[0]['team']);
        $this->assertSame('Win', $matches[0]['outcome']);
        $this->assertSame('2029 (+12)', $matches[0]['ratingChange']);
        $this->assertSame('Nagrand Arena', $matches[0]['map']);
    }
}
