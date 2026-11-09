<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;

class CharacterCommentRateLimiter
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** Call within a transaction so the user row lock covers the subsequent insert. */
    public function secondsUntilAllowed(User $user, \DateTimeImmutable $now): int
    {
        $userId = $user->getId() ?? throw new \LogicException('A persisted user is required to comment.');
        $this->connection->fetchOne('SELECT id FROM `user` WHERE id = :userId FOR UPDATE', ['userId' => $userId]);
        $row = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS total, MAX(created_at) AS latest, MIN(created_at) AS oldest FROM character_comments WHERE user_id = :userId AND created_at >= :dayStart',
            [
                'userId' => $userId,
                'dayStart' => $now->modify('-1 day')->format('Y-m-d H:i:s'),
            ],
        );
        if ($row === false) {
            return 0;
        }

        $wait = 0;
        if ($row['latest'] !== null) {
            $nextAllowed = (new \DateTimeImmutable((string) $row['latest']))->modify('+30 seconds');
            $wait = max($wait, $nextAllowed->getTimestamp() - $now->getTimestamp());
        }
        if ((int) $row['total'] >= 30 && $row['oldest'] !== null) {
            $dailyLimitExpires = (new \DateTimeImmutable((string) $row['oldest']))->modify('+1 day');
            $wait = max($wait, $dailyLimitExpires->getTimestamp() - $now->getTimestamp());
        }

        return max(0, $wait);
    }
}
