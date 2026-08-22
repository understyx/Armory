<?php

namespace App\Repository;

use App\Entity\UwuLogRank;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<UwuLogRank> */
class UwuLogRankRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UwuLogRank::class);
    }

    public function upsertResult(string $name, string $realm, string $spec, array $payload): UwuLogRank
    {
        $stored = $this->findOneBy([
            'name' => ucfirst(trim($name)),
            'realm' => ucfirst(trim($realm)),
            'spec' => $spec,
        ]) ?? (new UwuLogRank())->setName($name)->setRealm($realm)->setSpec($spec);

        $stored->setOverallRank((int) ($payload['overallRank'] ?? 0))
            ->setPayload($payload)
            ->setScrapedAt(new \DateTimeImmutable());
        $this->getEntityManager()->persist($stored);
        $this->getEntityManager()->flush();

        return $stored;
    }

    public function findCached(string $name, string $realm, string $spec, \DateTimeImmutable $cutoff): ?UwuLogRank
    {
        return $this->createQueryBuilder('r')
            ->where('LOWER(r.name) = LOWER(:name)')
            ->andWhere('LOWER(r.realm) = LOWER(:realm)')
            ->andWhere('r.spec = :spec')
            ->andWhere('r.scrapedAt >= :cutoff')
            ->setParameter('name', trim($name))
            ->setParameter('realm', trim($realm))
            ->setParameter('spec', $spec)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findBest(string $name, string $realm): ?UwuLogRank
    {
        return $this->createQueryBuilder('r')
            ->where('LOWER(r.name) = LOWER(:name)')
            ->andWhere('LOWER(r.realm) = LOWER(:realm)')
            ->andWhere('r.overallRank > 0')
            ->setParameter('name', trim($name))
            ->setParameter('realm', trim($realm))
            ->orderBy('r.overallRank', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @param list<string> $names @return array<string, UwuLogRank> */
    public function findBestForCharacters(array $names, string $realm): array
    {
        if ($names === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('r')
            ->where('LOWER(r.name) IN (:names)')
            ->andWhere('LOWER(r.realm) = LOWER(:realm)')
            ->andWhere('r.overallRank > 0')
            ->setParameter('names', array_values(array_unique(array_map(static fn(string $name): string => strtolower($name), $names))))
            ->setParameter('realm', trim($realm))
            ->orderBy('r.overallRank', 'ASC')
            ->getQuery()
            ->getResult();

        $best = [];
        foreach ($rows as $rank) {
            $key = strtolower((string) $rank->getName());
            $best[$key] ??= $rank;
        }

        return $best;
    }
}
