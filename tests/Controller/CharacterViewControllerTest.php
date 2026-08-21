<?php

namespace App\Tests\Controller;

use App\Controller\CharacterViewController;
use App\Entity\CharacterSnapshot;
use App\Repository\CharacterSnapshotRepository;
use App\Service\ArmoryScraperService;
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
        $snapshot->setGearScore(6000);
        $snapshot->setAvgIlvl(264.5);
        $snapshot->setProfessions(['Jewelcrafting (450)']);
        $snapshot->setSpecializations(['Unholy']);
        $snapshot->setEquippedItems([]);
        $snapshot->setCharacterModel([
            'race' => 1,
            'gender' => 1,
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
        $snapshot->setScrapedAt(new \DateTimeImmutable());

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
        $itemDbService->method('getItemsBulk')->willReturn([]);
        $paperdollService = new \App\Service\PaperdollService($itemDbService);

        $container = self::getContainer();
        $twig = $container->get('twig');

        $controller = new CharacterViewController($scraperService, $snapshotRepo, $talentTreeService, $paperdollService, $logger);
        $controller->setContainer($container);

        $response = $controller->viewCharacter('Understyx', 'Icecrown');

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('Understyx', $response->getContent());
        $this->assertStringContainsString('6000', $response->getContent());
        $this->assertStringContainsString('Fetch rankings from Uwu-logs', $response->getContent());
        $this->assertStringContainsString('/characters/Understyx/Icecrown/uwu-logs', $response->getContent());
        $this->assertStringContainsString('Rankings are fetched only when you request them.', $response->getContent());
        $this->assertStringContainsString('Show all 11 matches', $response->getContent());
        $this->assertSame(1, substr_count($response->getContent(), 'class="match-history-extra" hidden'));
        $this->assertLessThan(
            strpos($response->getContent(), 'Player vs Player & Arena Teams'),
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
            $logger
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
        $controller = new CharacterViewController(
            $scraperService,
            $snapshotRepo,
            new \App\Service\TalentTreeService(),
            $paperdollService,
            $this->createMock(LoggerInterface::class)
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
}
