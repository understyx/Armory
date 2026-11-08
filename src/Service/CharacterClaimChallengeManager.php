<?php

namespace App\Service;

use App\Entity\CharacterClaimChallenge;
use App\Entity\User;
use App\Repository\CharacterClaimChallengeRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CharacterClaimChallengeManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CharacterClaimChallengeRepository $challengeRepository,
        private Connection $connection,
    ) {
    }

    public function start(User $user, string $name, string $realm, ?\DateTimeImmutable $now = null): ?CharacterClaimChallenge
    {
        $now ??= new \DateTimeImmutable();
        $userId = $user->getId();
        if ($userId === null) {
            throw new \LogicException('A persisted user is required to start a character link challenge.');
        }

        $this->connection->beginTransaction();
        try {
            $this->connection->executeQuery('SELECT id FROM `user` WHERE id = ? FOR UPDATE', [$userId])->fetchOne();
            $cutoff = $now->modify('-1 hour');
            $this->connection->executeStatement(
                'DELETE FROM character_claim_attempts WHERE user_id = ? AND created_at <= ?',
                [$userId, $cutoff->format('Y-m-d H:i:s')],
            );
            $attempts = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM character_claim_attempts WHERE user_id = ? AND created_at > ?',
                [$userId, $cutoff->format('Y-m-d H:i:s')],
            );
            if ($attempts >= 10) {
                $this->connection->commit();

                return null;
            }

            $oldChallenge = $this->challengeRepository->findCurrentForUser($user);
            if ($oldChallenge !== null) {
                $this->entityManager->remove($oldChallenge);
                $this->entityManager->flush();
            }

            $challenge = new CharacterClaimChallenge($user, $name, $realm, $now);
            $this->entityManager->persist($challenge);
            $this->entityManager->flush();
            $this->connection->insert('character_claim_attempts', [
                'user_id' => $userId,
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
            $this->connection->commit();

            return $challenge;
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
