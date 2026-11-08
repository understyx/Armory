<?php

namespace App\Tests\Repository;

use App\Entity\CharacterClaim;
use App\Entity\User;
use App\Repository\CharacterClaimRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class CharacterClaimRepositoryTest extends TestCase
{
    public function testOwnershipTransferUsesAnAtomicUniqueKeyUpsert(): void
    {
        $user = (new User())->setDiscordId('new-owner');
        $id = new \ReflectionProperty($user, 'id');
        $id->setValue($user, 42);
        $verifiedAt = new \DateTimeImmutable('2026-11-09T12:00:00+00:00');

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), verified_at = VALUES(verified_at)'),
                ['userId' => 42, 'name' => 'Testchar', 'realm' => 'Icecrown', 'verifiedAt' => $verifiedAt],
                [
                    'userId' => Types::INTEGER,
                    'name' => Types::STRING,
                    'realm' => Types::STRING,
                    'verifiedAt' => Types::DATETIME_IMMUTABLE,
                ],
            )
            ->willReturn(1);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->with(CharacterClaim::class)->willReturn(new ClassMetadata(CharacterClaim::class));
        $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(CharacterClaim::class)->willReturn($entityManager);
        $repository = new CharacterClaimRepository($registry);

        $repository->transferOwnership($user, 'Testchar', 'Icecrown', $verifiedAt);
    }
}
