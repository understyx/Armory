<?php

namespace App\Tests\Service;

use App\Repository\CharacterSnapshotRepository;
use App\Service\ArmoryScraperService;
use App\Service\CharacterRefreshResult;
use App\Service\CharacterSnapshotUpdater;
use PHPUnit\Framework\TestCase;

class CharacterSnapshotUpdaterTest extends TestCase
{
    public function testItStoresAParsedCharacterSnapshot(): void
    {
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->method('findByNameAndRealm')->willReturn(null);
        $repository->expects(self::once())
            ->method('upsert')
            ->with(self::callback(static fn($snapshot): bool => $snapshot->getName() === 'Understyx'
                && $snapshot->getRealm() === 'Icecrown'
                && $snapshot->getLevel() === 80
                && $snapshot->getEquippedItems() === [['id' => 50735]]))
            ->willReturn(true);

        $scraper = $this->createMock(ArmoryScraperService::class);
        $scraper->method('fetchArmoryHtml')
            ->willReturnCallback(static fn(string $name, string $realm, string $type): ?string => match ($type) {
                'summary' => '<html>profile</html>',
                'talents' => '<html>talents</html>',
                default => null,
            });
        $scraper->method('checkCharacterExists')->willReturn(true);
        $scraper->method('extractLevelRaceClass')->willReturn([
            'level' => 80,
            'race' => 'Human',
            'class' => 'Death Knight',
        ]);
        $scraper->method('extractEquippedItemsData')->willReturn([['id' => 50735]]);
        $scraper->method('calculateGearScore')->willReturn(6000);
        $scraper->method('calculateAvgIlvl')->willReturn(264.5);

        $result = (new CharacterSnapshotUpdater($scraper, $repository))
            ->refresh('Understyx', 'Icecrown');

        self::assertSame(CharacterRefreshResult::UPDATED, $result->status);
        self::assertNotNull($result->snapshot);
    }

    public function testItReportsAnUnavailableSourceWithoutOverwritingCachedData(): void
    {
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->method('findByNameAndRealm')->willReturn(null);
        $repository->expects(self::never())->method('upsert');
        $scraper = $this->createMock(ArmoryScraperService::class);
        $scraper->method('fetchArmoryHtml')->willReturn(null);

        $result = (new CharacterSnapshotUpdater($scraper, $repository))
            ->refresh('Understyx', 'Icecrown');

        self::assertSame(CharacterRefreshResult::SOURCE_UNAVAILABLE, $result->status);
        self::assertNull($result->snapshot);
    }
}
