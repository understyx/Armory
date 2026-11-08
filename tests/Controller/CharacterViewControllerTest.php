<?php

namespace App\Tests\Controller;

use App\Controller\CharacterViewController;
use App\Entity\CharacterSnapshot;
use App\Entity\UwuLogRank;
use App\Message\RefreshCharacterSnapshotMessage;
use App\Message\RefreshUwuRankMessage;
use App\Repository\CharacterSnapshotRepository;
use App\Repository\UwuLogRankRepository;
use App\Service\ArmoryScraperService;
use App\Service\CharacterRefreshResult;
use App\Service\CharacterRefreshTracker;
use App\Service\CharacterSnapshotUpdater;
use App\Service\CharacterUpdateThrottle;
use App\Service\CharacterUpdateThrottleDecision;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class CharacterViewControllerTest extends KernelTestCase
{
    public function testViewCharacterUsesDatabaseSnapshotWhenPresent(): void
    {
        $snapshot = new CharacterSnapshot();
        $snapshot->setName('Understyx');
        $snapshot->setRealm('Icecrown');
        $snapshot->setLevel(80);
        $snapshot->setRace('Human');
        $snapshot->setClass('Death Knight');
        $snapshot->setGuild('Cadence');
        $snapshot->setGearScore(6000);
        $snapshot->setAvgIlvl(264.5);
        $snapshot->setProfessions(['Jewelcrafting (450)']);
        $snapshot->setSpecializations(['Unholy']);
        $snapshot->setEquippedItems([
            ['id' => 50001, 'transmog' => 60001],
            ['id' => 50002, 'class' => 2, 'type' => 17, 'tooltip' => ['stats' => []]],
        ]);
        $snapshot->setCharacterModel([
            'race' => 1,
            'gender' => 0,
            'skin' => 0,
            'face' => 0,
            'hairStyle' => 0,
            'hairColor' => 0,
            'facialStyle' => 0,
            'items' => [],
        ]);
        $snapshot->setTalentStrings(['51 / 20 / 0']);
        $snapshot->setTalentTreesData(['0' => [[
            'name' => 'Unholy',
            'points' => 71,
            'tiers' => [[[
                'spellId' => 990001,
                'pointsText' => '2/3',
                'href' => 'https://wotlk.evowow.com/?spell=990001',
                'status' => 'active',
                'iconUrl' => null,
            ]]],
        ]]]);
        $snapshot->setGlyphs([]);
        $snapshot->setEnchantsStatus('All items enchanted');
        $snapshot->setGemsStatus('All gem slots filled');
        $snapshot->setKillStats([]);
        $snapshot->setPvpStats(['totalKills' => 0, 'killsToday' => 0, 'arenaTeams' => []]);
        $snapshot->setMatchHistory(array_map(
            static fn(int $matchNumber): array => [
                'outcome' => 'Win',
                'ratingChange' => '+10',
                'team' => 'Test Team',
                'teamUrl' => null,
                'map' => 'Nagrand Arena',
                'duration' => '01:00',
                'startTime' => sprintf('Match %d', $matchNumber),
                'gameId' => (string) $matchNumber,
            ],
            range(1, 11)
        ));
        $snapshot->setScrapedAt(new \DateTimeImmutable('-2 days'));

        $snapshotRepo = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepo->expects($this->once())
            ->method('findViewByNameAndRealm')
            ->with('Understyx', 'Icecrown')
            ->willReturn($snapshot);
        $snapshotRepo->expects($this->once())
            ->method('findStatsByNameAndRealm')
            ->with('Understyx', 'Icecrown')
            ->willReturn($snapshot);

        $scraperService = $this->createMock(ArmoryScraperService::class);
        // Scraper should NEVER be called if DB snapshot exists
        $scraperService->expects($this->never())->method('fetchArmoryHtml');

        $logger = $this->createMock(LoggerInterface::class);
        $talentTreeService = new \App\Service\TalentTreeService();
        $itemDbService = $this->createMock(\App\Service\ItemDatabaseService::class);
        $itemDbService->method('getItemsBulk')->willReturn([
            50001 => [
                'name' => 'Equipped Helm',
                'quality' => 4,
                'type' => \App\Enum\ItemTypes::HEAD->value,
            ],
            60001 => [
                'name' => 'Crown of Purple Testing',
                'quality' => 4,
                'type' => \App\Enum\ItemTypes::HEAD->value,
            ],
        ]);
        $paperdollService = new \App\Service\PaperdollService($itemDbService);
        $uwuRank = (new UwuLogRank())
            ->setName('Understyx')->setRealm('Icecrown')->setSpec('3')->setOverallRank(1525)
            ->setPayload(['overallPoints' => 98.0])->setScrapedAt(new \DateTimeImmutable());
        $uwuRankRepository = $this->createMock(UwuLogRankRepository::class);
        $uwuRankRepository->expects(self::once())->method('findBest')->with('Understyx', 'Icecrown')->willReturn($uwuRank);

        $container = self::getContainer();
        $twig = $container->get('twig');

        $controller = new CharacterViewController(
            $scraperService,
            $snapshotRepo,
            $talentTreeService,
            $paperdollService,
            $logger,
            $this->createMock(CharacterUpdateThrottle::class),
            $uwuRankRepository,
            new \App\Service\CharacterStatCalculator(),
            null,
            null,
            new \App\Service\CharacterHitExpertiseService(new \App\Service\CharacterStatCalculator()),
        );
        $controller->setContainer($container);

        $response = $controller->viewCharacter('Understyx', 'Icecrown');

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('Understyx', $response->getContent());
        $this->assertStringContainsString(
            'Party buffs are excluded; +3% on casters is the Improved Faerie Fire or Misery raid debuff.',
            $response->getContent(),
        );
        $this->assertStringContainsString('6000', $response->getContent());
        $this->assertStringContainsString('id="stats-tab"', $response->getContent());
        $this->assertStringContainsString('id="achievements-tab"', $response->getContent());
        $this->assertStringContainsString('data-achievements-url="/characters/Understyx/Icecrown/achievements"', $response->getContent());
        $this->assertStringContainsString('data-stats-url="/characters/Understyx/Icecrown/stats-panel"', $response->getContent());
        $this->assertStringContainsString('Loading character stats', $response->getContent());
        $this->assertStringNotContainsString('Stats at level 80', $response->getContent());
        $this->assertStringNotContainsString('Level growth', $response->getContent());
        $this->assertStringContainsString('Uwu-logs overall points', $response->getContent());
        $this->assertStringContainsString('data-points="98" data-rank="1525" style="color: #ff3c00">98.00', $response->getContent());
        $this->assertStringContainsString('id="header-uwu-rank" class="stat-rank">#1,525', $response->getContent());
        $this->assertStringContainsString('id="header-uwu-spec" class="stat-meta">Unholy', $response->getContent());
        $this->assertStringContainsString('href="/guilds/Cadence/Icecrown"', $response->getContent());
        $this->assertStringContainsString('Level 80 Male', $response->getContent());
        $this->assertStringContainsString('Human <span aria-hidden="true">·</span>', $response->getContent());
        $this->assertStringContainsString('Interactive 3D model of Understyx, male', $response->getContent());
        $this->assertStringContainsString('id="model-viewer-enabled" type="checkbox" role="switch"', $response->getContent());
        $this->assertStringContainsString('<script type="module" src="/assets/model-viewer-', $response->getContent());
        $this->assertStringNotContainsString('<script src="https://wow.zamimg.com/modelviewer/', $response->getContent());
        $this->assertStringContainsString('class="character-search site-header-search"', $response->getContent());
        $this->assertStringContainsString('action="/characters"', $response->getContent());
        $this->assertStringContainsString('<option value="Icecrown" selected>', $response->getContent());
        $this->assertStringContainsString('<option value="Onyxia">', $response->getContent());
        $this->assertStringNotContainsString('<option value="Frostmourne">', $response->getContent());
        $this->assertStringContainsString('Get transmog', $response->getContent());
        $this->assertStringContainsString('https://wotlk.evowow.com/?item=60001', $response->getContent());
        $this->assertStringContainsString('<script type="module" src="/assets/item-tooltip-', $response->getContent());
        $this->assertStringNotContainsString('https://cdn.cavernoftime.com/api/tooltip.js', $response->getContent());
        $this->assertStringNotContainsString('rel="item=', $response->getContent());
        $this->assertStringContainsString('Crown of Purple Testing', $response->getContent());
        $this->assertStringContainsString('Fetch rankings from Uwu-logs', $response->getContent());
        $this->assertStringContainsString('/characters/Understyx/Icecrown/uwu-logs', $response->getContent());
        $this->assertStringContainsString('Rankings are fetched only when you request them.', $response->getContent());
        $this->assertStringContainsString('May be out of date', $response->getContent());
        $this->assertStringNotContainsString('Outdated', $response->getContent());
        $this->assertStringContainsString('Show all 11 matches', $response->getContent());
        $this->assertSame(1, substr_count($response->getContent(), 'class="match-history-extra" hidden'));
        $this->assertLessThan(
            strpos($response->getContent(), 'Specialization & Talent Trees'),
            strpos($response->getContent(), 'Professions')
        );

        $statsResponse = $controller->viewCharacterStatsPanel('Understyx', 'Icecrown');
        $this->assertSame(Response::HTTP_OK, $statsResponse->getStatusCode());
        $this->assertStringContainsString('Stats at level 80', $statsResponse->getContent());
        $this->assertStringContainsString('Only Hit and Expertise are currently reliable', $statsResponse->getContent());
        $this->assertStringContainsString('href="/characters/Understyx/Icecrown/stats"', $statsResponse->getContent());
        $this->assertStringContainsString('Class at 80', $statsResponse->getContent());
        $this->assertStringContainsString('class="calculated-stat-total">175', $statsResponse->getContent());
    }

    public function testAchievementsEndpointRendersLazyRaidGroupTabsWithoutFetchingWarmane(): void
    {
        $scraperService = $this->createMock(ArmoryScraperService::class);
        $scraperService->expects(self::never())->method('fetchAchievementCategoryHtml');

        $controller = new CharacterViewController(
            $scraperService,
            $this->createMock(CharacterSnapshotRepository::class),
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CharacterUpdateThrottle::class),
        );
        $controller->setContainer(self::getContainer());

        $fallback = $controller->viewCharacterAchievements('Puredecay', 'Icecrown');
        self::assertStringContainsString('class="character-search site-header-search"', $fallback->getContent());
        self::assertStringContainsString('View achievements on Warmane', $fallback->getContent());

        $request = Request::create('/characters/Puredecay/Icecrown/achievements');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $response = $controller->viewCharacterAchievements('Puredecay', 'Icecrown', $request);
        $content = $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('ICC + RS', $content);
        self::assertStringContainsString('ToGC', $content);
        self::assertStringContainsString('Ulduar', $content);
        self::assertStringContainsString('Naxx + EoE + OS', $content);
        self::assertStringContainsString('/characters/Puredecay/Icecrown/achievements/icc-rs', $content);
        self::assertStringContainsString('/characters/Puredecay/Icecrown/achievements/togc', $content);
        self::assertStringNotContainsString('Bane of the Fallen King', $content);
        self::assertStringContainsString('https://armory.warmane.com/character/Puredecay/Icecrown/achievements', $content);
    }

    public function testMissingCharacterQueuesOneBackgroundRefreshAndRendersLoadingPage(): void
    {
        $snapshotRepository = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepository->expects(self::once())
            ->method('findViewByNameAndRealm')
            ->with('Understyx', 'Icecrown')
            ->willReturn(null);

        $scraper = $this->createMock(ArmoryScraperService::class);
        $scraper->expects(self::never())->method('fetchArmoryHtml');
        $throttle = $this->createMock(CharacterUpdateThrottle::class);
        $throttle->expects(self::once())
            ->method('claim')
            ->with('Understyx', 'Icecrown')
            ->willReturn(new CharacterUpdateThrottleDecision(true, new \DateTimeImmutable('+5 minutes')));
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $message): bool => $message instanceof RefreshCharacterSnapshotMessage
                && $message->characterName === 'Understyx'
                && $message->realmName === 'Icecrown'), self::callback(static fn (array $stamps): bool => $stamps[0] instanceof DeduplicateStamp))
            ->willReturnCallback(static fn (object $message, array $stamps): Envelope => new Envelope($message, $stamps));

        $controller = new CharacterViewController(
            $scraper,
            $snapshotRepository,
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $throttle,
            null,
            null,
            $bus,
        );
        $controller->setContainer(self::getContainer());

        $response = $controller->viewCharacter('Understyx', 'Icecrown');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('Preparing character', $response->getContent());
        self::assertStringContainsString('data-is-queued="1"', $response->getContent());
        self::assertStringContainsString('/characters/Understyx/Icecrown/load-status', $response->getContent());
        self::assertStringContainsString('/characters/Understyx/Icecrown/load', $response->getContent());
    }

    public function testLoadCharacterReturnsReadyWhenSnapshotAlreadyExists(): void
    {
        $snapshot = (new CharacterSnapshot())
            ->setName('Understyx')
            ->setRealm('Icecrown');

        $snapshotRepository = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepository->expects(self::once())
            ->method('existsByNameAndRealm')
            ->with('Understyx', 'Icecrown')
            ->willReturn(true);

        $controller = new CharacterViewController(
            $this->createMock(ArmoryScraperService::class),
            $snapshotRepository,
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CharacterUpdateThrottle::class),
        );
        $controller->setContainer(self::getContainer());

        $response = $controller->loadCharacter('Understyx', 'Icecrown');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertTrue($data['ready']);
        self::assertSame('ready', $data['status']);
        self::assertSame('/characters/Understyx/Icecrown', $data['characterUrl']);
    }

    public function testLoadCharacterScrapesAndReturnsReadyWhenSnapshotMissing(): void
    {
        $snapshot = (new CharacterSnapshot())
            ->setName('Newchar')
            ->setRealm('Icecrown');

        $snapshotRepository = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepository->expects(self::once())
            ->method('existsByNameAndRealm')
            ->with('Newchar', 'Icecrown')
            ->willReturn(false);

        $updater = $this->createMock(CharacterSnapshotUpdater::class);
        $updater->expects(self::once())
            ->method('refresh')
            ->with('Newchar', 'Icecrown')
            ->willReturn(new CharacterRefreshResult(CharacterRefreshResult::UPDATED, $snapshot));

        $controller = new CharacterViewController(
            $this->createMock(ArmoryScraperService::class),
            $snapshotRepository,
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CharacterUpdateThrottle::class),
            null,
            null,
            null,
            $updater,
        );
        $controller->setContainer(self::getContainer());

        $response = $controller->loadCharacter('Newchar', 'Icecrown');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertTrue($data['ready']);
        self::assertSame('ready', $data['status']);
        self::assertSame('/characters/Newchar/Icecrown', $data['characterUrl']);
    }

    public function testLoadCharacterReturnsNotFoundWhenCharacterDoesNotExist(): void
    {
        $snapshotRepository = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepository->expects(self::once())
            ->method('existsByNameAndRealm')
            ->with('Missing', 'Icecrown')
            ->willReturn(false);

        $updater = $this->createMock(CharacterSnapshotUpdater::class);
        $updater->expects(self::once())
            ->method('refresh')
            ->with('Missing', 'Icecrown')
            ->willReturn(new CharacterRefreshResult(CharacterRefreshResult::NOT_FOUND));

        $controller = new CharacterViewController(
            $this->createMock(ArmoryScraperService::class),
            $snapshotRepository,
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CharacterUpdateThrottle::class),
            null,
            null,
            null,
            $updater,
        );
        $controller->setContainer(self::getContainer());

        $response = $controller->loadCharacter('Missing', 'Icecrown');
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertFalse($data['ready']);
        self::assertSame('not_found', $data['status']);
        self::assertStringContainsString("We couldn't find Missing on Icecrown", $data['message']);
    }

    public function testLoadCharacterReturnsUnavailableWhenWarmaneIsDown(): void
    {
        $snapshotRepository = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepository->expects(self::once())
            ->method('existsByNameAndRealm')
            ->with('Timeoutchar', 'Icecrown')
            ->willReturn(false);

        $updater = $this->createMock(CharacterSnapshotUpdater::class);
        $updater->expects(self::once())
            ->method('refresh')
            ->with('Timeoutchar', 'Icecrown')
            ->willReturn(new CharacterRefreshResult(CharacterRefreshResult::SOURCE_UNAVAILABLE));

        $controller = new CharacterViewController(
            $this->createMock(ArmoryScraperService::class),
            $snapshotRepository,
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CharacterUpdateThrottle::class),
            null,
            null,
            null,
            $updater,
        );
        $controller->setContainer(self::getContainer());

        $response = $controller->loadCharacter('Timeoutchar', 'Icecrown');
        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertFalse($data['ready']);
        self::assertSame('unavailable', $data['status']);
        self::assertStringContainsString('Warmane armory is currently unreachable', $data['message']);
    }

    public function testLoadStatusIncludesCharacterUrlWhenReady(): void
    {
        $snapshot = (new CharacterSnapshot())
            ->setName('Understyx')
            ->setRealm('Icecrown');

        $snapshotRepository = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepository->expects(self::once())
            ->method('existsByNameAndRealm')
            ->with('Understyx', 'Icecrown')
            ->willReturn(true);

        $controller = new CharacterViewController(
            $this->createMock(ArmoryScraperService::class),
            $snapshotRepository,
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CharacterUpdateThrottle::class),
        );
        $controller->setContainer(self::getContainer());

        $response = $controller->loadStatus('Understyx', 'Icecrown');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertTrue($data['ready']);
        self::assertSame('/characters/Understyx/Icecrown', $data['characterUrl']);
    }

    public function testCachedCharacterWithoutModelNeverTriggersLiveBackfill(): void
    {
        $snapshot = (new CharacterSnapshot())
            ->setName('Understyx')
            ->setRealm('Icecrown')
            ->setLevel(80)
            ->setRace('Human')
            ->setClass('Death Knight')
            ->setGearScore(6000)
            ->setAvgIlvl(264.5)
            ->setProfessions([])
            ->setSpecializations([])
            ->setEquippedItems([])
            ->setCharacterModel(null)
            ->setTalentStrings([])
            ->setGlyphs([])
            ->setEnchantsStatus('')
            ->setGemsStatus('')
            ->setKillStats([])
            ->setScrapedAt(new \DateTimeImmutable());

        $snapshotRepository = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepository->method('findViewByNameAndRealm')->willReturn($snapshot);
        $snapshotRepository->expects(self::never())->method('save');
        $scraper = $this->createMock(ArmoryScraperService::class);
        $scraper->expects(self::never())->method('fetchArmoryHtml');

        $controller = new CharacterViewController(
            $scraper,
            $snapshotRepository,
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CharacterUpdateThrottle::class),
        );
        $controller->setContainer(self::getContainer());

        $response = $controller->viewCharacter('Understyx', 'Icecrown');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('Refresh character data to load the 3D model.', $response->getContent());
    }

    public function testIccAchievementGroupFetchesOnlyItsCategoriesAndRendersResult(): void
    {
        $snapshot = (new CharacterSnapshot())
            ->setName('Puredecay')
            ->setRealm('Icecrown');
        $snapshotRepository = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepository->expects(self::once())
            ->method('findAchievementsByNameAndRealm')
            ->with('Puredecay', 'Icecrown')
            ->willReturn($snapshot);
        $snapshotRepository->expects(self::once())
            ->method('save')
            ->with($snapshot);

        $scraperService = $this->createMock(ArmoryScraperService::class);
        $scraperService->expects(self::exactly(4))
            ->method('fetchAchievementCategoryHtml')
            ->willReturnCallback(static function (string $character, string $realm, int $category): string {
                self::assertSame('Puredecay', $character);
                self::assertSame('Icecrown', $realm);
                self::assertContains($category, [15041, 15042, 14922, 14923]);

                return sprintf('<div>%d</div>', $category);
            });
        $scraperService->expects(self::exactly(4))
            ->method('extractRaidAchievements')
            ->willReturnCallback(static fn(string $html, int $category): array => [
                'raidSize' => in_array($category, [15042, 14923], true) ? 25 : 10,
                'category' => $category,
                'achievements' => $category === 15041 ? [[
                    'id' => 4583,
                    'earned' => true,
                    'earnedDate' => '11/02/2020',
                ]] : [],
            ]);
        $scraperService->expects(self::once())
            ->method('groupRaidAchievements')
            ->willReturn([
                [
                    'key' => 'icc_rs',
                    'title' => 'ICC + RS',
                    'raidSizes' => [
                        ['raidSize' => 10, 'achievements' => [[
                            'id' => 4583,
                            'raid' => 'Icecrown Citadel',
                            'section' => 'The Lich King',
                            'difficulty' => 'heroic',
                            'title' => 'Bane of the Fallen King',
                            'description' => 'Defeat the Lich King on Heroic.',
                            'iconUrl' => null,
                            'earned' => true,
                            'earnedDate' => '11/02/2020',
                        ]]],
                        ['raidSize' => 25, 'achievements' => []],
                    ],
                ],
            ]);

        $controller = new CharacterViewController(
            $scraperService,
            $snapshotRepository,
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CharacterUpdateThrottle::class),
        );
        $controller->setContainer(self::getContainer());

        $response = $controller->viewCharacterAchievementGroup('Puredecay', 'Icecrown', 'icc-rs');
        $content = $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('10-player', $content);
        self::assertStringContainsString('25-player', $content);
        self::assertStringContainsString('Bane of the Fallen King', $content);
        self::assertStringContainsString('✓ Earned', $content);
        self::assertSame('11/02/2020', $snapshot->getRaidAchievements()['15041']['4583']);
    }

    public function testAchievementGroupFallsBackToSavedIdsAndDatesWhenWarmaneFails(): void
    {
        $snapshot = (new CharacterSnapshot())
            ->setName('Puredecay')
            ->setRealm('Icecrown')
            ->setRaidAchievements([
                '15001' => ['3918' => '07/14/2020', '3810' => '08/01/2020'],
                '15002' => ['3812' => '07/15/2020'],
            ]);
        $snapshotRepository = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepository->expects(self::once())->method('findAchievementsByNameAndRealm')->willReturn($snapshot);
        $snapshotRepository->expects(self::never())->method('save');

        $realScraper = new ArmoryScraperService();
        $scraperService = $this->createMock(ArmoryScraperService::class);
        $scraperService->expects(self::exactly(2))->method('fetchAchievementCategoryHtml')->willReturn(null);
        $scraperService->expects(self::exactly(2))
            ->method('buildRaidAchievementsFromCache')
            ->willReturnCallback(static fn(array $cached, int $category): array => $realScraper->buildRaidAchievementsFromCache($cached, $category));
        $scraperService->expects(self::once())
            ->method('groupRaidAchievements')
            ->willReturnCallback(static fn(array $results): array => $realScraper->groupRaidAchievements($results));

        $controller = new CharacterViewController(
            $scraperService,
            $snapshotRepository,
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CharacterUpdateThrottle::class),
        );
        $controller->setContainer(self::getContainer());

        $response = $controller->viewCharacterAchievementGroup('Puredecay', 'Icecrown', 'togc');
        $content = $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('08/01/2020', $content);
        self::assertStringContainsString('Tribute to Insanity', $content);
        self::assertStringContainsString('✓ Earned', $content);
    }

    public function testStatsDebugPageShowsEveryCalculationSource(): void
    {
        $snapshot = (new CharacterSnapshot())
            ->setName('Understyx')
            ->setRealm('Icecrown')
            ->setLevel(80)
            ->setRace('Human')
            ->setClass('Death Knight')
            ->setGearScore(0)
            ->setAvgIlvl(0.0)
            ->setProfessions([])
            ->setSpecializations(['Unholy (0 / 0 / 2)'])
            ->setEquippedItems([['id' => 50001]])
            ->setTalentStrings(['0'])
            ->setTalentTreesData(['0' => [[
                'name' => 'Unholy',
                'points' => 2,
                'tiers' => [[['spellId' => 48979, 'pointsText' => '2/3']]],
            ]]])
            ->setGlyphs([])
            ->setEnchantsStatus('')
            ->setGemsStatus('')
            ->setKillStats([])
            ->setScrapedAt(new \DateTimeImmutable('2026-08-24 10:00:00+00:00'));

        $snapshotRepo = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepo->expects(self::once())
            ->method('findStatsByNameAndRealm')
            ->with('Understyx', 'Icecrown')
            ->willReturn($snapshot);
        $scraperService = $this->createMock(ArmoryScraperService::class);
        $scraperService->expects(self::never())->method('fetchArmoryHtml');
        $itemDbService = $this->createMock(\App\Service\ItemDatabaseService::class);
        $itemDbService->method('getItemsBulk')->willReturn([50001 => [
            'name' => 'Expertise Test Helm',
            'quality' => 4,
            'type' => \App\Enum\ItemTypes::HEAD->value,
            'tooltip' => [
                'stats' => [
                    ['type' => 4, 'value' => 50],
                    ['type' => 37, 'value' => 82],
                ],
                'armor' => 1000,
            ],
        ]]);

        $controller = new CharacterViewController(
            $scraperService,
            $snapshotRepo,
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($itemDbService),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CharacterUpdateThrottle::class),
            null,
            new \App\Service\CharacterStatCalculator(),
        );
        $controller->setContainer(self::getContainer());

        $response = $controller->viewCharacterStats('Understyx', 'Icecrown');
        $content = $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('Stat calculation breakdown', $content);
        self::assertStringContainsString('Only Hit and Expertise are currently reliable', $content);
        self::assertStringContainsString('Equipped item contributions', $content);
        self::assertStringContainsString('Expertise Test Helm', $content);
        self::assertStringContainsString('Raw item stat mapping', $content);
        self::assertStringContainsString('Expertise', $content);
        self::assertStringContainsString('10.00', $content);
        self::assertStringContainsString('Ravenous Dead', $content);
        self::assertStringContainsString('+2% Strength', $content);
        self::assertStringContainsString('href="/characters/Understyx/Icecrown"', $content);
    }

    public function testViewCharacterRendersSimpleNotFoundPage(): void
    {
        $snapshotRepo = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepo->expects($this->once())
            ->method('findViewByNameAndRealm')
            ->with('Missing', 'Icecrown')
            ->willReturn(null);

        $scraperService = $this->createMock(ArmoryScraperService::class);
        $scraperService->expects($this->once())
            ->method('fetchArmoryHtml')
            ->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $talentTreeService = new \App\Service\TalentTreeService();
        $itemDbService = $this->createMock(\App\Service\ItemDatabaseService::class);
        $paperdollService = new \App\Service\PaperdollService($itemDbService);

        $controller = new CharacterViewController(
            $scraperService,
            $snapshotRepo,
            $talentTreeService,
            $paperdollService,
            $logger,
            $this->createMock(CharacterUpdateThrottle::class),
        );
        $controller->setContainer(self::getContainer());

        $response = $controller->viewCharacter('Missing', 'Icecrown');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('Character not found', $response->getContent());
        $this->assertStringNotContainsString('Exception', $response->getContent());
    }

    public function testRefreshDoesNotReportSuccessWhenScrapedDataIsUnchanged(): void
    {
        $existing = new CharacterSnapshot();
        $existing->setName('Understyx');
        $existing->setRealm('Icecrown');
        $existing->setLevel(80);
        $existing->setRace('Human');
        $existing->setClass('Death Knight');
        $existing->setGearScore(0);
        $existing->setAvgIlvl(0.0);
        $existing->setProfessions([]);
        $existing->setSpecializations([]);
        $existing->setEquippedItems([]);
        $existing->setTalentStrings([]);
        $existing->setGlyphs([]);
        $existing->setEnchantsStatus('');
        $existing->setGemsStatus('');
        $existing->setKillStats([]);
        $existing->setScrapedAt(new \DateTimeImmutable('2026-08-20 10:00:00'));

        $snapshotRepo = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepo->expects($this->once())
            ->method('existsByNameAndRealm')
            ->willReturn(true);
        $snapshotRepo->expects($this->once())
            ->method('upsert')
            ->willReturn(false);

        $scraperService = $this->createMock(ArmoryScraperService::class);
        $scraperService->method('fetchArmoryHtml')
            ->willReturnCallback(static fn(string $character, string $realm, string $type): ?string => match ($type) {
                'summary' => '<html>profile</html>',
                'talents' => '',
                default => null,
            });
        $scraperService->method('checkCharacterExists')->willReturn(true);
        $scraperService->method('extractLevelRaceClass')->willReturn([
            'level' => 80,
            'race' => 'Human',
            'class' => 'Death Knight',
        ]);

        $itemDbService = $this->createMock(\App\Service\ItemDatabaseService::class);
        $paperdollService = new \App\Service\PaperdollService($itemDbService);
        $updateThrottle = $this->createMock(CharacterUpdateThrottle::class);
        $updateThrottle->expects(self::once())
            ->method('claim')
            ->with('Understyx', 'Icecrown')
            ->willReturn(new CharacterUpdateThrottleDecision(true, new \DateTimeImmutable('+5 minutes')));
        $controller = new CharacterViewController(
            $scraperService,
            $snapshotRepo,
            new \App\Service\TalentTreeService(),
            $paperdollService,
            $this->createMock(LoggerInterface::class),
            $updateThrottle,
        );

        $container = self::getContainer();
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $container->get('request_stack')->push($request);
        $controller->setContainer($container);

        try {
            $response = $controller->refreshCharacter('Understyx', 'Icecrown');
        } finally {
            $container->get('request_stack')->pop();
        }

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame(
            ['No new character data was available. The saved data was not changed.'],
            $session->getFlashBag()->peek('warning')
        );
        self::assertSame([], $session->getFlashBag()->peek('success'));
    }

    public function testRefreshIsRejectedDuringFiveMinuteCooldown(): void
    {
        $snapshotRepo = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepo->expects(self::never())->method('existsByNameAndRealm');
        $scraperService = $this->createMock(ArmoryScraperService::class);
        $scraperService->expects(self::never())->method('fetchArmoryHtml');
        $updateThrottle = $this->createMock(CharacterUpdateThrottle::class);
        $updateThrottle->expects(self::once())
            ->method('claim')
            ->with('Understyx', 'Icecrown')
            ->willReturn(new CharacterUpdateThrottleDecision(false, new \DateTimeImmutable('+2 minutes')));

        $paperdollService = new \App\Service\PaperdollService(
            $this->createMock(\App\Service\ItemDatabaseService::class),
        );
        $controller = new CharacterViewController(
            $scraperService,
            $snapshotRepo,
            new \App\Service\TalentTreeService(),
            $paperdollService,
            $this->createMock(LoggerInterface::class),
            $updateThrottle,
        );

        $container = self::getContainer();
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $container->get('request_stack')->push($request);
        $controller->setContainer($container);

        try {
            $response = $controller->refreshCharacter('Understyx', 'Icecrown');
        } finally {
            $container->get('request_stack')->pop();
        }

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame(
            ['Character data can only be refreshed once every five minutes. Try again in about 2 minutes.'],
            $session->getFlashBag()->peek('warning'),
        );
    }

    public function testRefreshStatusReturnsIdleWithoutCaching(): void
    {
        $tracker = $this->createMock(CharacterRefreshTracker::class);
        $tracker->expects(self::once())->method('status')->with('Missing', 'Icecrown')->willReturn([
            'state' => 'idle', 'position' => null, 'etaSeconds' => null,
        ]);
        $controller = new CharacterViewController(
            $this->createMock(ArmoryScraperService::class),
            $this->createMock(CharacterSnapshotRepository::class),
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CharacterUpdateThrottle::class),
            refreshTracker: $tracker,
        );

        $response = $controller->refreshStatus('Missing', 'Icecrown');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame([
            'state' => 'idle', 'position' => null, 'etaSeconds' => null,
        ], json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testJsonRefreshMarksQueuedBeforeDispatchAndReturnsEta(): void
    {
        $markedQueued = false;
        $tracker = $this->createMock(CharacterRefreshTracker::class);
        $tracker->expects(self::once())->method('markQueued')->willReturnCallback(static function () use (&$markedQueued): void {
            $markedQueued = true;
        });
        $tracker->method('status')->willReturn(['state' => 'queued', 'position' => 2, 'etaSeconds' => 40]);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $message, array $stamps) use (&$markedQueued): Envelope {
                self::assertTrue($markedQueued, 'The queued state must be saved before dispatch.');
                self::assertInstanceOf(RefreshCharacterSnapshotMessage::class, $message);
                self::assertInstanceOf(DeduplicateStamp::class, $stamps[0]);

                return new Envelope($message, $stamps);
            },
        );
        $throttle = $this->createMock(CharacterUpdateThrottle::class);
        $throttle->method('claim')->willReturn(new CharacterUpdateThrottleDecision(true, new \DateTimeImmutable('+5 minutes')));
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->method('existsByNameAndRealm')->willReturn(true);
        $controller = new CharacterViewController(
            $this->createMock(ArmoryScraperService::class),
            $repository,
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $throttle,
            messageBus: $bus,
            refreshTracker: $tracker,
        );
        $controller->setContainer(self::getContainer());
        $request = new Request(server: ['HTTP_ACCEPT' => 'application/json']);

        $response = $controller->refreshCharacter('Understyx', 'Icecrown', $request);
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertSame('queued', $payload['state']);
        self::assertSame(2, $payload['position']);
        self::assertSame(40, $payload['etaSeconds']);
        self::assertArrayHasKey('retryAfterSeconds', $payload);
    }

    public function testAcceptedRefreshQueuesTheCachedBestUwuSpec(): void
    {
        $bestRank = (new UwuLogRank())->setSpec('3');
        $rankRepository = $this->createMock(UwuLogRankRepository::class);
        $rankRepository->expects(self::once())->method('findBest')->with('Understyx', 'Icecrown')->willReturn($bestRank);
        $dispatched = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))->method('dispatch')->willReturnCallback(
            static function (object $message, array $stamps) use (&$dispatched): Envelope {
                $dispatched[] = [$message, $stamps];

                return new Envelope($message, $stamps);
            },
        );
        $controller = $this->queuedRefreshController($rankRepository, $bus);

        $response = $controller->refreshCharacter('Understyx', 'Icecrown', new Request(server: ['HTTP_ACCEPT' => 'application/json']));

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertInstanceOf(RefreshCharacterSnapshotMessage::class, $dispatched[0][0]);
        self::assertInstanceOf(RefreshUwuRankMessage::class, $dispatched[1][0]);
        self::assertSame('3', $dispatched[1][0]->spec);
        self::assertTrue($dispatched[1][0]->forceRefresh);
        self::assertContainsOnlyInstancesOf(DeduplicateStamp::class, array_filter(
            $dispatched[1][1], static fn (object $stamp): bool => $stamp instanceof DeduplicateStamp,
        ));
    }

    public function testAcceptedRefreshQueuesAllSpecsWhenNoUwuRankIsCached(): void
    {
        $rankRepository = $this->createMock(UwuLogRankRepository::class);
        $rankRepository->expects(self::once())->method('findBest')->with('Understyx', 'Icecrown')->willReturn(null);
        $dispatched = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(4))->method('dispatch')->willReturnCallback(
            static function (object $message, array $stamps) use (&$dispatched): Envelope {
                $dispatched[] = [$message, $stamps];

                return new Envelope($message, $stamps);
            },
        );
        $controller = $this->queuedRefreshController($rankRepository, $bus);

        $response = $controller->refreshCharacter('Understyx', 'Icecrown', new Request(server: ['HTTP_ACCEPT' => 'application/json']));

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertSame(['1', '2', '3'], array_map(
            static fn (array $entry): string => $entry[0]->spec,
            array_slice($dispatched, 1),
        ));
        self::assertSame([5000, 10000, 15000], array_map(
            static function (array $entry): int {
                foreach ($entry[1] as $stamp) {
                    if ($stamp instanceof DelayStamp) {
                        return $stamp->getDelay();
                    }
                }

                self::fail('The queued Uwu spec needs a spacing delay.');
            },
            array_slice($dispatched, 1),
        ));
        foreach (array_slice($dispatched, 1) as [$message]) {
            self::assertInstanceOf(RefreshUwuRankMessage::class, $message);
            self::assertTrue($message->forceRefresh);
        }
    }

    public function testUwuDispatchFailureDoesNotFailTheCharacterRefreshResponse(): void
    {
        $rankRepository = $this->createMock(UwuLogRankRepository::class);
        $rankRepository->method('findBest')->willReturn(null);
        $bus = $this->createMock(MessageBusInterface::class);
        $dispatchCount = 0;
        $bus->expects(self::exactly(2))->method('dispatch')->willReturnCallback(
            static function (object $message, array $stamps) use (&$dispatchCount): Envelope {
                if (++$dispatchCount === 2) {
                    throw new \RuntimeException('Uwu queue unavailable');
                }

                return new Envelope($message, $stamps);
            },
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'Unable to queue Uwu-logs rank refresh after character refresh.',
            self::arrayHasKey('exception'),
        );
        $controller = $this->queuedRefreshController($rankRepository, $bus, $logger);

        $response = $controller->refreshCharacter('Understyx', 'Icecrown', new Request(server: ['HTTP_ACCEPT' => 'application/json']));

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
    }

    public function testJsonRefreshReturnsThrottledWhenNoJobIsActive(): void
    {
        $tracker = $this->createMock(CharacterRefreshTracker::class);
        $tracker->method('status')->willReturn(['state' => 'idle', 'position' => null, 'etaSeconds' => null]);
        $rankRepository = $this->createMock(UwuLogRankRepository::class);
        $rankRepository->expects(self::never())->method('findBest');
        $throttle = $this->createMock(CharacterUpdateThrottle::class);
        $throttle->method('claim')->willReturn(new CharacterUpdateThrottleDecision(false, new \DateTimeImmutable('+2 minutes')));
        $controller = new CharacterViewController(
            $this->createMock(ArmoryScraperService::class),
            $this->createMock(CharacterSnapshotRepository::class),
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $throttle,
            refreshTracker: $tracker,
        );

        $response = $controller->refreshCharacter('Understyx', 'Icecrown', new Request(server: ['HTTP_ACCEPT' => 'application/json']));
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertSame('throttled', $payload['state']);
        self::assertGreaterThan(0, $payload['retryAfterSeconds']);
    }

    public function testJsonRefreshReturnsAnActiveJobWhenThrottled(): void
    {
        $tracker = $this->createMock(CharacterRefreshTracker::class);
        $tracker->method('status')->willReturn(['state' => 'running', 'position' => 1, 'etaSeconds' => 20]);
        $throttle = $this->createMock(CharacterUpdateThrottle::class);
        $throttle->method('claim')->willReturn(new CharacterUpdateThrottleDecision(false, new \DateTimeImmutable('+2 minutes')));
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        $controller = new CharacterViewController(
            $this->createMock(ArmoryScraperService::class),
            $this->createMock(CharacterSnapshotRepository::class),
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $throttle,
            uwuRankRepository: $rankRepository,
            messageBus: $bus,
            refreshTracker: $tracker,
        );

        $response = $controller->refreshCharacter('Understyx', 'Icecrown', new Request(server: ['HTTP_ACCEPT' => 'application/json']));
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('running', $payload['state']);
        self::assertSame(1, $payload['position']);
    }

    public function testNonJsonQueuedRefreshFlashIncludesARoughEta(): void
    {
        $tracker = $this->createMock(CharacterRefreshTracker::class);
        $tracker->method('status')->willReturn(['state' => 'queued', 'position' => 1, 'etaSeconds' => 20]);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static fn (object $message, array $stamps): Envelope => new Envelope($message, $stamps));
        $throttle = $this->createMock(CharacterUpdateThrottle::class);
        $throttle->method('claim')->willReturn(new CharacterUpdateThrottleDecision(true, new \DateTimeImmutable('+5 minutes')));
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->method('existsByNameAndRealm')->willReturn(true);
        $controller = new CharacterViewController(
            $this->createMock(ArmoryScraperService::class),
            $repository,
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $this->createMock(LoggerInterface::class),
            $throttle,
            messageBus: $bus,
            refreshTracker: $tracker,
        );
        $container = self::getContainer();
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $container->get('request_stack')->push($request);
        $controller->setContainer($container);

        try {
            $response = $controller->refreshCharacter('Understyx', 'Icecrown', $request);
        } finally {
            $container->get('request_stack')->pop();
        }

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame(
            ['Character refresh queued. Showing saved data while it updates. Estimated wait: less than a minute.'],
            $session->getFlashBag()->peek('success'),
        );
    }

    private function queuedRefreshController(
        UwuLogRankRepository $rankRepository,
        MessageBusInterface $bus,
        ?LoggerInterface $logger = null,
    ): CharacterViewController {
        $throttle = $this->createMock(CharacterUpdateThrottle::class);
        $throttle->method('claim')->willReturn(new CharacterUpdateThrottleDecision(true, new \DateTimeImmutable('+5 minutes')));
        $snapshotRepository = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepository->method('existsByNameAndRealm')->willReturn(true);

        return new CharacterViewController(
            $this->createMock(ArmoryScraperService::class),
            $snapshotRepository,
            new \App\Service\TalentTreeService(),
            new \App\Service\PaperdollService($this->createMock(\App\Service\ItemDatabaseService::class)),
            $logger ?? $this->createMock(LoggerInterface::class),
            $throttle,
            uwuRankRepository: $rankRepository,
            messageBus: $bus,
        );
    }
}
