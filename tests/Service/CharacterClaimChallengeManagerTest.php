<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\CharacterClaimChallengeRepository;
use App\Service\CharacterClaimChallengeManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CharacterClaimChallengeManagerTest extends TestCase
{
    public function testEleventhChallengeInTheRollingHourIsRejected(): void
    {
        $user = (new User())->setDiscordId('123');
        $id = new \ReflectionProperty($user, 'id');
        $id->setValue($user, 42);
        $now = new \DateTimeImmutable('2026-11-09T12:00:00+00:00');

        $queryResult = $this->createMock(Result::class);
        $queryResult->expects(self::once())->method('fetchOne');
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())
            ->method('executeQuery')
            ->with('SELECT id FROM `user` WHERE id = ? FOR UPDATE', [42])
            ->willReturn($queryResult);
        $connection->expects(self::once())
            ->method('executeStatement')
            ->with('DELETE FROM character_claim_attempts WHERE user_id = ? AND created_at <= ?', [42, '2026-11-09 11:00:00']);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->with('SELECT COUNT(*) FROM character_claim_attempts WHERE user_id = ? AND created_at > ?', [42, '2026-11-09 11:00:00'])
            ->willReturn('10');
        $connection->expects(self::once())->method('commit');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $challengeRepository = $this->createMock(CharacterClaimChallengeRepository::class);
        $challengeRepository->expects(self::never())->method('findCurrentForUser');
        $manager = new CharacterClaimChallengeManager($entityManager, $challengeRepository, $connection);

        self::assertNull($manager->start($user, 'Testchar', 'Icecrown', $now));
    }
}
