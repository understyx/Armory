<?php

namespace App\Repository;

use App\Entity\CharacterClaim;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CharacterClaim> */
class CharacterClaimRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CharacterClaim::class);
    }

    /** @return list<CharacterClaim> */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['name' => 'ASC', 'realm' => 'ASC']);
    }

    public function transferOwnership(User $user, string $name, string $realm, \DateTimeImmutable $verifiedAt): void
    {
        $userId = $user->getId() ?? throw new \LogicException('A persisted user is required to own a character claim.');
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO character_claims (user_id, name, realm, verified_at) VALUES (:userId, :name, :realm, :verifiedAt) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), verified_at = VALUES(verified_at)',
            ['userId' => $userId, 'name' => $name, 'realm' => $realm, 'verifiedAt' => $verifiedAt],
            [
                'userId' => Types::INTEGER,
                'name' => Types::STRING,
                'realm' => Types::STRING,
                'verifiedAt' => Types::DATETIME_IMMUTABLE,
            ],
        );
    }
}
