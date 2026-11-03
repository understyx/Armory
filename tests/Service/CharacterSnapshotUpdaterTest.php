<?php

namespace App\Tests\Service;

use App\Entity\CharacterSnapshot;
use App\Repository\CharacterSnapshotRepository;
use App\Service\ArmoryScraperService;
use App\Service\CharacterRefreshResult;
use App\Service\CharacterSnapshotUpdater;
use App\Service\CharacterTooltipEnrichmentQueue;
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

        $tooltipQueue = $this->createMock(CharacterTooltipEnrichmentQueue::class);
        $tooltipQueue->expects(self::once())
            ->method('queueAfterRefresh')
            ->with(self::callback(static fn ($snapshot): bool => $snapshot->getTalentTreesData() === []));

        $result = (new CharacterSnapshotUpdater($scraper, $repository, $tooltipQueue))
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

    #[\PHPUnit\Framework\Attributes\DataProvider('missingPageProvider')]
    public function testItKeepsSavedSectionsForMissingPagesAndSavesTheRest(string $missingPage, callable $saved): void
    {
        $existing = (new CharacterSnapshot())->setName('Understyx')->setRealm('Icecrown')
            ->setTalentTreesData(['saved-trees'])
            ->setTalentStrings(['0' => 'saved-string'])
            ->setGlyphs(['0' => ['Major Glyphs' => ['Saved']]])
            ->setKillStats(['saved-kills'])
            ->setMatchHistory([['gameId' => 'saved']]);
        $stored = null;
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->method('findByNameAndRealm')->willReturn($existing);
        $repository->expects(self::once())->method('upsert')->willReturnCallback(
            static function (CharacterSnapshot $snapshot) use (&$stored): bool {
                $stored = $snapshot;

                return true;
            },
        );
        $scraper = $this->createMock(ArmoryScraperService::class);
        $scraper->method('fetchArmoryHtml')->willReturnCallback(
            static fn (string $name, string $realm, string $page): ?string => $page === $missingPage ? null : '<html>page</html>',
        );
        $scraper->method('checkCharacterExists')->willReturn(true);
        $scraper->method('extractEquippedItemsData')->willReturn([['id' => 1]]);

        $result = (new CharacterSnapshotUpdater($scraper, $repository))->refresh('Understyx', 'Icecrown');

        self::assertSame(CharacterRefreshResult::PARTIAL, $result->status);
        self::assertSame([['id' => 1]], $stored->getEquippedItems());
        $saved($stored);
    }

    /** @return iterable<string, array{string, callable}> */
    public static function missingPageProvider(): iterable
    {
        yield 'talents' => ['talents', static function (CharacterSnapshot $stored): void {
            self::assertSame(['saved-trees'], $stored->getTalentTreesData());
            self::assertSame(['0' => 'saved-string'], $stored->getTalentStrings());
            self::assertSame(['0' => ['Major Glyphs' => ['Saved']]], $stored->getGlyphs());
        }];
        yield 'kills' => ['kills', static function (CharacterSnapshot $stored): void {
            self::assertSame(['saved-kills'], $stored->getKillStats());
        }];
        yield 'match history' => ['match-history', static function (CharacterSnapshot $stored): void {
            self::assertSame([['gameId' => 'saved']], $stored->getMatchHistory());
        }];
    }

}
