<?php

namespace App\Tests\Repository;

use App\Entity\CharacterSnapshot;
use App\Repository\CharacterSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class CharacterSnapshotRepositoryTest extends TestCase
{
    public function testRecentlyViewedQuerySelectsOnlyHomepageFields(): void
    {
        $query = $this->createMock(Query::class);
        $query->expects(self::once())->method('getScalarResult')->willReturn([
            ['name' => 'Understyx', 'realm' => 'Icecrown'],
        ]);
        $builder = $this->createMock(QueryBuilder::class);
        $builder->expects(self::once())->method('select')->with('c.name AS name, c.realm AS realm')->willReturnSelf();
        $builder->expects(self::once())->method('orderBy')->with('c.scrapedAt', 'DESC')->willReturnSelf();
        $builder->expects(self::once())->method('setMaxResults')->with(10)->willReturnSelf();
        $builder->expects(self::once())->method('getQuery')->willReturn($query);
        $repository = new class($builder) extends CharacterSnapshotRepository {
            public function __construct(private readonly QueryBuilder $builder)
            {
            }

            public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
            {
                return $this->builder;
            }
        };

        self::assertSame([
            ['name' => 'Understyx', 'realm' => 'Icecrown'],
        ], $repository->findRecentlyViewed(10));
    }

    public function testSnapshotPageAndApiProjectionsSelectOnlyRenderedData(): void
    {
        $projections = [
            'findViewByNameAndRealm' => 'PARTIAL c.{id, name, realm, level, race, class, guild, gearScore, avgIlvl, professions, specializations, equippedItems, characterModel, talentStrings, talentTreesData, glyphs, enchantsStatus, gemsStatus, killStats, pvpStats, matchHistory, scrapedAt}',
            'findStatsByNameAndRealm' => 'PARTIAL c.{id, name, realm, level, race, class, equippedItems, talentStrings, talentTreesData, specializations, scrapedAt}',
            'findApiByNameAndRealm' => 'PARTIAL c.{id, name, realm, level, race, class, guild, gearScore, avgIlvl, professions, specializations, equippedItems, characterModel, talentStrings, talentTreesData, raidAchievements, scrapedAt}',
            'findAchievementsByNameAndRealm' => 'PARTIAL c.{id, name, realm, raidAchievements, scrapedAt}',
        ];

        foreach ($projections as $method => $selection) {
            $query = $this->createMock(Query::class);
            $query->expects(self::once())->method('getOneOrNullResult')->willReturn(null);
            $builder = $this->createMock(QueryBuilder::class);
            $builder->expects(self::once())->method('select')->with($selection)->willReturnSelf();
            $builder->method('where')->willReturnSelf();
            $builder->method('andWhere')->willReturnSelf();
            $builder->method('setParameter')->willReturnSelf();
            $builder->expects(self::once())->method('getQuery')->willReturn($query);
            $repository = new class($builder) extends CharacterSnapshotRepository {
                public function __construct(private readonly QueryBuilder $builder)
                {
                }

                public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
                {
                    return $this->builder;
                }
            };

            self::assertNull($repository->{$method}('Understyx', 'Icecrown'));
        }
    }

    public function testUpsertRecordsTheCheckWhenScrapedDataIsUnchanged(): void
    {
        $storedAt = new \DateTimeImmutable('2026-08-20 10:00:00');
        $checkedAt = new \DateTimeImmutable('2026-08-20 11:00:00');
        $existing = $this->createSnapshot($storedAt);
        $scraped = $this->createSnapshot($checkedAt);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $repository = $this->createRepository($existing, $entityManager);

        self::assertFalse($repository->upsert($scraped));
        self::assertSame($checkedAt, $existing->getScrapedAt());
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
