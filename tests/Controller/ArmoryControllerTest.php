<?php

namespace App\Tests\Controller;

use App\Controller\ArmoryController;
use App\Entity\CharacterSnapshot;
use App\Repository\CharacterSnapshotRepository;
use App\Service\ArmoryScraperService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ArmoryControllerTest extends TestCase
{
    public function testForcedRefreshReportsUnchangedAndReturnsStoredTimestamp(): void
    {
        $storedAt = new \DateTimeImmutable('2026-08-20 10:00:00+00:00');
        $existing = $this->createSnapshot($storedAt);

        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->expects(self::once())
            ->method('findByNameAndRealm')
            ->with('Understyx', 'Icecrown')
            ->willReturn($existing);
        $repository->expects(self::once())
            ->method('upsert')
            ->willReturn(false);

        $scraper = $this->createMock(ArmoryScraperService::class);
        $scraper->method('fetchArmoryHtml')
            ->willReturnCallback(static fn(string $character, string $realm, string $type): ?string => match ($type) {
                'summary' => '<html>profile</html>',
                'talents' => '',
                default => null,
            });
        $scraper->method('checkCharacterExists')->willReturn(true);
        $scraper->method('extractLevelRaceClass')->willReturn([
            'level' => 80,
            'race' => 'Human',
            'class' => 'Death Knight',
        ]);

        $response = (new ArmoryController($scraper, $repository))->scrapeArmory(
            'Understyx',
            'Icecrown',
            new Request(['refresh' => '1'])
        );
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('unchanged', $payload['status']);
        self::assertFalse($payload['updated']);
        self::assertSame('Female', $payload['gender']);
        self::assertSame($storedAt->format(\DateTimeInterface::ATOM), $payload['scrapedAt']);
        self::assertSame('No new character data was available. The saved data was not changed.', $payload['warning']);
    }

    private function createSnapshot(\DateTimeImmutable $scrapedAt): CharacterSnapshot
    {
        return (new CharacterSnapshot())
            ->setName('Understyx')
            ->setRealm('Icecrown')
            ->setLevel(80)
            ->setRace('Human')
            ->setClass('Death Knight')
            ->setGearScore(0)
            ->setAvgIlvl(0.0)
            ->setProfessions([])
            ->setSpecializations([])
            ->setEquippedItems([])
            ->setCharacterModel([
                'race' => 1,
                'gender' => 1,
                'items' => [],
            ])
            ->setTalentStrings([])
            ->setGlyphs([])
            ->setEnchantsStatus('')
            ->setGemsStatus('')
            ->setKillStats([])
            ->setScrapedAt($scrapedAt);
    }
}
