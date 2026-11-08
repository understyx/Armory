<?php

namespace App\Repository;

use App\Entity\CharacterClaimChallenge;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CharacterClaimChallenge> */
class CharacterClaimChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CharacterClaimChallenge::class);
    }

    public function findCurrentForUser(User $user): ?CharacterClaimChallenge
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function expireIfNeeded(int $id, \DateTimeImmutable $now): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            "UPDATE character_claim_challenges SET state = 'expired', scan_lease_until = NULL, updated_at = :now, state_revision = state_revision + 1 WHERE id = :id AND expires_at <= :now AND state NOT IN ('verified', 'not_found', 'no_items', 'expired')",
            ['id' => $id, 'now' => $now],
            ['id' => Types::INTEGER, 'now' => Types::DATETIME_IMMUTABLE],
        );
    }

    public function tryAcquireScan(int $id, string $phase, \DateTimeImmutable $now): bool
    {
        $state = match ($phase) {
            'baseline' => CharacterClaimChallenge::SCANNING_BASELINE,
            'verify' => CharacterClaimChallenge::SCANNING_VERIFY,
            default => null,
        };
        if ($state === null) {
            return false;
        }

        $leaseUntil = $now->modify('+2 minutes');
        $updated = $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE character_claim_challenges SET scan_lease_until = :leaseUntil WHERE id = :id AND state = :state AND expires_at > :now AND (scan_lease_until IS NULL OR scan_lease_until < :now)',
            ['id' => $id, 'state' => $state, 'now' => $now, 'leaseUntil' => $leaseUntil],
            [
                'id' => Types::INTEGER,
                'state' => Types::STRING,
                'now' => Types::DATETIME_IMMUTABLE,
                'leaseUntil' => Types::DATETIME_IMMUTABLE,
            ],
        );

        return $updated === 1;
    }
}
