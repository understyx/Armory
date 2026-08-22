<?php

namespace App\Repository;

use App\Entity\GuildSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GuildSnapshot> */
class GuildSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GuildSnapshot::class);
    }

    public function findByNameAndRealm(string $name, string $realm): ?GuildSnapshot
    {
        return $this->createQueryBuilder('g')
            ->where('LOWER(g.name) = LOWER(:name)')
            ->andWhere('LOWER(g.realm) = LOWER(:realm)')
            ->setParameter('name', trim($name))
            ->setParameter('realm', trim($realm))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function upsert(GuildSnapshot $snapshot): bool
    {
        $existing = $this->findByNameAndRealm((string) $snapshot->getName(), (string) $snapshot->getRealm());
        if ($existing !== null) {
            if ($existing->getFaction() === $snapshot->getFaction()
                && $existing->getMemberCount() === $snapshot->getMemberCount()
                && $existing->getPvePoints() === $snapshot->getPvePoints()
                && $existing->getMembers() === $snapshot->getMembers()) {
                return false;
            }

            $existing->setFaction($snapshot->getFaction())
                ->setMemberCount($snapshot->getMemberCount())
                ->setPvePoints($snapshot->getPvePoints())
                ->setMembers($snapshot->getMembers())
                ->setScrapedAt($snapshot->getScrapedAt() ?? new \DateTimeImmutable());
        } else {
            $this->getEntityManager()->persist($snapshot);
        }

        $this->getEntityManager()->flush();

        return true;
    }
}
