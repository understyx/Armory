<?php

namespace App\Tests\Repository;

use App\Entity\UwuLogRank;
use App\Repository\UwuLogRankRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class UwuLogRankBatchRepositoryTest extends TestCase
{
    public function testBestRanksAreResolvedForRequestedCharacterRealmPairsInOneBatch(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for the in-memory repository integration test.');
        }

        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $entityManager = new EntityManager($connection, $config);
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(UwuLogRank::class)]);
        foreach ([
            ['Understyx', 'Icecrown', '1', 60.0],
            ['Understyx', 'Icecrown', '2', 72.0],
            ['Other', 'Icecrown', '3', 99.0],
            ['Understyx', 'Lordaeron', '3', 88.0],
        ] as [$name, $realm, $spec, $points]) {
            $entityManager->persist((new UwuLogRank())
                ->setName($name)->setRealm($realm)->setSpec($spec)
                ->setPayload(['overallPoints' => $points, 'classId' => 6])
                ->setScrapedAt(new \DateTimeImmutable()));
        }
        $entityManager->flush();
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);
        $repository = new UwuLogRankRepository($registry);

        $ranks = $repository->findBestForCharactersAndRealms([
            ['name' => 'understyx', 'realm' => 'icecrown'],
        ]);

        self::assertSame(72.0, $ranks['understyx'."\0".'icecrown']->getOverallPoints());
        self::assertSame('2', $ranks['understyx'."\0".'icecrown']->getSpec());
        self::assertCount(1, $ranks);

        $connection->close();
    }
}
