<?php

namespace App\Tests\Controller;

use App\Controller\CharacterViewController;
use App\Entity\CharacterSnapshot;
use App\Entity\UwuLogRank;
use App\Repository\CharacterSnapshotRepository;
use App\Repository\UwuLogRankRepository;
use App\Service\ArmoryScraperService;
use App\Service\CharacterUpdateThrottle;
use App\Service\CharacterUpdateThrottleDecision;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

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
            ->method('findByNameAndRealm')
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
        );
        $controller->setContainer($container);

        $response = $controller->viewCharacter('Understyx', 'Icecrown');

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('Understyx', $response->getContent());
        $this->assertStringContainsString('6000', $response->getContent());
        $this->assertStringContainsString('id="stats-tab"', $response->getContent());
        $this->assertStringContainsString('Stats at level 80', $response->getContent());
        $this->assertStringContainsString('Class at 80', $response->getContent());
        $this->assertStringContainsString('class="calculated-stat-total">175', $response->getContent());
        $this->assertStringNotContainsString('Level growth', $response->getContent());
        $this->assertStringContainsString('Best Uwu-logs Parse', $response->getContent());
        $this->assertStringContainsString('data-points="98" data-rank="1525" style="color: #ff3c00">98.00', $response->getContent());
        $this->assertStringContainsString('id="header-uwu-rank" class="stat-rank">#1,525', $response->getContent());
        $this->assertStringContainsString('id="header-uwu-spec" class="stat-meta">Unholy', $response->getContent());
        $this->assertStringContainsString('href="/guilds/Cadence/Icecrown"', $response->getContent());
        $this->assertStringContainsString('Level 80 Male Human', $response->getContent());
        $this->assertStringContainsString('Interactive 3D model of Understyx, male', $response->getContent());
        $this->assertStringContainsString('class="character-search character-page-search"', $response->getContent());
        $this->assertStringContainsString('action="/characters"', $response->getContent());
        $this->assertStringContainsString('<option value="Icecrown" selected>', $response->getContent());
        $this->assertStringContainsString('<option value="Onyxia">', $response->getContent());
        $this->assertStringNotContainsString('<option value="Frostmourne">', $response->getContent());
        $this->assertStringContainsString('Get transmog', $response->getContent());
        $this->assertStringContainsString('https://wotlk.cavernoftime.com/item=60001', $response->getContent());
        $this->assertStringContainsString('Crown of Purple Testing', $response->getContent());
        $this->assertStringContainsString('Fetch rankings from Uwu-logs', $response->getContent());
        $this->assertStringContainsString('/characters/Understyx/Icecrown/uwu-logs', $response->getContent());
        $this->assertStringContainsString('Rankings are fetched only when you request them.', $response->getContent());
        $this->assertStringContainsString("Hasn't been updated in 2 days and could be out of date.", $response->getContent());
        $this->assertStringNotContainsString('Outdated', $response->getContent());
        $this->assertStringContainsString('Show all 11 matches', $response->getContent());
        $this->assertSame(1, substr_count($response->getContent(), 'class="match-history-extra" hidden'));
        $this->assertLessThan(
            strpos($response->getContent(), 'Specialization & Talent Trees'),
            strpos($response->getContent(), 'Professions')
        );
    }

    public function testViewCharacterRendersSimpleNotFoundPage(): void
    {
        $snapshotRepo = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepo->expects($this->once())
            ->method('findByNameAndRealm')
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
            ->method('findByNameAndRealm')
            ->willReturn($existing);
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
        $snapshotRepo->expects(self::never())->method('findByNameAndRealm');
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
}
