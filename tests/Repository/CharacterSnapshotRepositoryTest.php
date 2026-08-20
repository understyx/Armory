<?php

namespace App\Tests\Repository;

use App\Entity\CharacterSnapshot;
use App\Repository\CharacterSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CharacterSnapshotRepositoryTest extends TestCase
{
    public function testUpsertDoesNotAdvanceTimestampWhenScrapedDataIsUnchanged(): void
    {
        $storedAt = new \DateTimeImmutable('2026-08-20 10:00:00');
        $existing = $this->createSnapshot($storedAt);
        $scraped = $this->createSnapshot(new \DateTimeImmutable('2026-08-20 11:00:00'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $repository = $this->createRepository($existing, $entityManager);

        self::assertFalse($repository->upsert($scraped));
        self::assertSame($storedAt, $existing->getScrapedAt());
    }

    public function testUpsertPersistsPvpAndMatchHistoryChanges(): void
    {
        $existing = $this->createSnapshot(new \DateTimeImmutable('2026-08-20 10:00:00'));
        $scrapedAt = new \DateTimeImmutable('2026-08-20 11:00:00');
        $scraped = $this->createSnapshot($scrapedAt);
        $scraped->setPvpStats(['totalKills' => 43]);
        $scraped->setMatchHistory([['id' => 'new-match']]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $repository = $this->createRepository($existing, $entityManager);

        self::assertTrue($repository->upsert($scraped));
        self::assertSame(['totalKills' => 43], $existing->getPvpStats());
        self::assertSame([['id' => 'new-match']], $existing->getMatchHistory());
        self::assertSame($scrapedAt, $existing->getScrapedAt());
    }

    private function createRepository(
        CharacterSnapshot $existing,
        EntityManagerInterface $entityManager
    ): CharacterSnapshotRepository {
        return new class($existing, $entityManager) extends CharacterSnapshotRepository {
            public function __construct(
                private readonly CharacterSnapshot $existing,
                private readonly EntityManagerInterface $entityManager
            ) {
            }

            public function findByNameAndRealm(string $name, string $realm): ?CharacterSnapshot
            {
                return $this->existing;
            }

            protected function getEntityManager(): EntityManagerInterface
            {
                return $this->entityManager;
            }
        };
    }

    private function createSnapshot(\DateTimeImmutable $scrapedAt): CharacterSnapshot
    {
        return (new CharacterSnapshot())
            ->setName('Understyx')
            ->setRealm('Icecrown')
            ->setLevel(80)
            ->setRace('Human')
            ->setClass('Death Knight')
            ->setGuild('Guild')
            ->setGearScore(6000)
            ->setAvgIlvl(264.5)
            ->setProfessions(['Jewelcrafting (450)'])
            ->setSpecializations(['Unholy'])
            ->setEquippedItems([['id' => 50735]])
            ->setTalentStrings(['51 / 20 / 0'])
            ->setTalentTreesData([['tree' => 'Unholy']])
            ->setGlyphs(['0' => ['Major Glyphs' => ['Glyph']]])
            ->setEnchantsStatus('All items enchanted')
            ->setGemsStatus('All gem slots filled')
            ->setKillStats(['icc' => 12])
            ->setPvpStats(['totalKills' => 42])
            ->setMatchHistory([['id' => 'old-match']])
            ->setScrapedAt($scrapedAt);
    }
}
