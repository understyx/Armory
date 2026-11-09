<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findLatestByDiscordUsername(string $username): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.discordUsername) = LOWER(:username)')
            ->setParameter('username', trim($username))
            ->orderBy('u.lastLoginAt', 'DESC')
            ->addOrderBy('u.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
