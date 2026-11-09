<?php

namespace App\Tests\Repository;

use App\Entity\CharacterSnapshot;
use App\Repository\CharacterSnapshotRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class CharacterSnapshotRepositorySqliteTest extends TestCase
{
    private ?EntityManager $entityManager = null;
    private ?CharacterSnapshotRepository $repository = null;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for the in-memory repository integration test.');
        }

        $config = ORMSetup::createAttributeMetadataConfig([
            dirname(__DIR__, 2).'/src/Entity',
        ], true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config);
        $this->entityManager = new EntityManager($connection, $config);
        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(CharacterSnapshot::class),
        ]);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->repository = new CharacterSnapshotRepository($registry);
    }

    protected function tearDown(): void
    {
        $this->entityManager?->getConnection()->close();
    }

    public function testProjectionFindersAndRecentlyUpdatedQueryAgainstSqlite(): void
    {
        $snapshot = $this->createSnapshot('Understyx', new \DateTimeImmutable('2026-11-01 10:00:00'));
        $older = $this->createSnapshot('Older', new \DateTimeImmutable('2026-10-30 10:00:00'));
        $this->entityManager->persist($snapshot);
        $this->entityManager->persist($older);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $view = $this->repository->findViewByNameAndRealm('Understyx', 'Icecrown');
        self::assertInstanceOf(CharacterSnapshot::class, $view);
        self::assertSame(6000, $view->getGearScore());
        self::assertNull($view->getRaidAchievements(), 'The character page projection omits raid achievements.');

        $this->entityManager->clear();
        $stats = $this->repository->findStatsByNameAndRealm('Understyx', 'Icecrown');
        self::assertInstanceOf(CharacterSnapshot::class, $stats);
        self::assertSame([['id' => 50735]], $stats->getEquippedItems());
        self::assertNull($stats->getEnchantsStatus(), 'The stats projection omits status text.');

        $this->entityManager->clear();
        $api = $this->repository->findApiByNameAndRealm('Understyx', 'Icecrown');
        self::assertInstanceOf(CharacterSnapshot::class, $api);
        self::assertSame(['gender' => 0], $api->getCharacterModel());
        self::assertSame(['icc' => ['normal' => 'earned']], $api->getRaidAchievements());
        self::assertNull($api->getPvpStats(), 'The API projection omits PvP stats.');

        $this->entityManager->clear();
        $achievements = $this->repository->findAchievementsByNameAndRealm('Understyx', 'Icecrown');
        self::assertInstanceOf(CharacterSnapshot::class, $achievements);
        self::assertSame(['icc' => ['normal' => 'earned']], $achievements->getRaidAchievements());
        self::assertSame([], $achievements->getKillStats(), 'The achievements projection omits kill stats.');

        self::assertTrue($this->repository->existsByNameAndRealm('Understyx', 'Icecrown'));
        self::assertFalse($this->repository->existsByNameAndRealm('Missing', 'Icecrown'));
        self::assertSame([
            ['name' => 'Understyx', 'realm' => 'Icecrown'],
            ['name' => 'Older', 'realm' => 'Icecrown'],
        ], $this->repository->findRecentlyViewed(2));

        self::assertSame([
            'class' => 'Death Knight',
            'level' => 80,
            'gearScore' => 6000,
        ], $this->repository->findDetailsForCharacters([
            ['name' => 'understyx', 'realm' => 'icecrown'],
        ])['understyx'."\0".'icecrown']);
    }

    public function testFlushingChangedFieldOnPartialSnapshotPreservesUnloadedColumns(): void
    {
        $snapshot = $this->createSnapshot('Understyx', new \DateTimeImmutable('2026-11-01 10:00:00'));
        $this->entityManager->persist($snapshot);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $partial = $this->repository->findStatsByNameAndRealm('Understyx', 'Icecrown');
        self::assertInstanceOf(CharacterSnapshot::class, $partial);
        $partial->setLevel(79);
        $this->entityManager->flush();

        $this->entityManager->clear();
        $stored = $this->repository->findByNameAndRealm('Understyx', 'Icecrown');
        self::assertInstanceOf(CharacterSnapshot::class, $stored);
        self::assertSame(79, $stored->getLevel());
        self::assertSame(['icc' => ['normal' => 'earned']], $stored->getRaidAchievements());
        self::assertSame(['totalKills' => 42], $stored->getPvpStats());
        self::assertSame([['id' => 'match-1']], $stored->getMatchHistory());
        self::assertSame('Enchant status retained', $stored->getEnchantsStatus());
    }

    private function createSnapshot(string $name, \DateTimeImmutable $scrapedAt): CharacterSnapshot
    {
        return (new CharacterSnapshot())
            ->setName($name)
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
            ->setCharacterModel(['gender' => 0])
            ->setTalentStrings(['51 / 20 / 0'])
            ->setTalentTreesData([['tree' => 'Unholy']])
            ->setGlyphs(['0' => ['Major Glyphs' => ['Glyph']]])
            ->setEnchantsStatus('Enchant status retained')
            ->setGemsStatus('Gem status retained')
            ->setKillStats(['icc' => 12])
            ->setPvpStats(['totalKills' => 42])
            ->setMatchHistory([['id' => 'match-1']])
            ->setRaidAchievements(['icc' => ['normal' => 'earned']])
            ->setScrapedAt($scrapedAt);
    }
}
