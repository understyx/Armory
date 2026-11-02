<?php

namespace App\Tests\Repository;

use App\Repository\UwuLogRankRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class UwuLogRankRepositoryTest extends TestCase
{
    public function testGuildRankLookupFetchesOnlyRankSummaryFieldsInOneBatch(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects(self::once())->method('fetchAllAssociative')->willReturn([
            ['name' => 'Imtilted', 'spec' => '1', 'overall_rank' => 70, 'overall_points' => '82.50'],
            ['name' => 'Imtilted', 'spec' => '2', 'overall_rank' => 42, 'overall_points' => '85.00'],
            ['name' => 'Secondchar', 'spec' => '3', 'overall_rank' => 0, 'overall_points' => '0.00'],
        ]);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('executeQuery')
            ->with(
                self::callback(static fn(string $sql): bool => str_contains($sql, 'JSON_EXTRACT(payload')
                    && !str_contains($sql, 'SELECT *')),
                [['Imtilted', 'Secondchar'], 'Icecrown'],
                self::anything(),
            )
            ->willReturn($result);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $repository = new class($entityManager) extends UwuLogRankRepository {
            public function __construct(private readonly EntityManagerInterface $entityManager)
            {
            }

            protected function getEntityManager(): EntityManagerInterface
            {
                return $this->entityManager;
            }
        };

        $ranks = $repository->findBestForCharacters(['Imtilted', 'Secondchar'], 'Icecrown');

        self::assertSame(85.0, $ranks['imtilted']->getOverallPoints());
        self::assertSame(42, $ranks['imtilted']->getOverallRank());
        self::assertSame('Protection', $ranks['imtilted']->specName('Paladin'));
        self::assertSame('#a335ee', $ranks['imtilted']->getScoreColor());
        self::assertSame(0.0, $ranks['secondchar']->getOverallPoints());
    }
}
