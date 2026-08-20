<?php

namespace App\Tests\Service;

use App\Service\ArmoryScraperService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ArmoryScraperServiceTest extends TestCase
{
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
            'gender' => 1,
            'skin' => 7,
            'face' => 4,
            'hairStyle' => 2,
            'hairColor' => 3,
            'facialStyle' => 5,
            'items' => [[1, 63830], [3, 64706], [21, 30606], [22, 53563]],
        ], $service->extractCharacterModelData($html));
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
