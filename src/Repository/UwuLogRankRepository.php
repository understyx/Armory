<?php

namespace App\Repository;

use App\Entity\GuildRankSummary;
use App\Entity\UwuLogRank;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Platforms\SQLitePlatform;

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
            ->where('r.name = :name')
            ->andWhere('r.realm = :realm')
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
        $rows = $this->createQueryBuilder('r')
            ->where('r.name = :name')
            ->andWhere('r.realm = :realm')
            ->setParameter('name', trim($name))
            ->setParameter('realm', trim($realm))
            ->getQuery()
            ->getResult();

        $best = null;
        foreach ($rows as $rank) {
            if ($rank->isBetterThan($best)) {
                $best = $rank;
            }
        }

        return $best;
    }

    /**
     * @param list<array{name: string, realm: string}> $characters
     * @return array<string, UwuLogRank>
     */
    public function findBestForCharactersAndRealms(array $characters): array
    {
        if ($characters === []) {
            return [];
        }

        $requested = [];
        $names = [];
        $realms = [];
        foreach ($characters as $character) {
            $name = ucfirst(trim($character['name']));
            $realm = ucfirst(trim($character['realm']));
            $requested[self::characterKey($name, $realm)] = true;
            $names[$name] = $name;
            $realms[$realm] = $realm;
        }

        $rows = $this->createQueryBuilder('r')
            ->where('r.name IN (:names)')
            ->andWhere('r.realm IN (:realms)')
            ->setParameter('names', array_values($names))
            ->setParameter('realms', array_values($realms))
            ->getQuery()
            ->getResult();

        $best = [];
        foreach ($rows as $rank) {
            $key = self::characterKey((string) $rank->getName(), (string) $rank->getRealm());
            if (!isset($requested[$key])) {
                continue;
            }

            if (!isset($best[$key]) || $rank->isBetterThan($best[$key])) {
                $best[$key] = $rank;
            }
        }

        return $best;
    }

    /** @param list<string> $names @return array<string, GuildRankSummary> */
    public function findBestForCharacters(array $names, string $realm): array
    {
        if ($names === []) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $overallPointsExpression = $connection->getDatabasePlatform() instanceof SQLitePlatform
            ? "json_extract(payload, '$.overallPoints')"
            : "CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.overallPoints')) AS DECIMAL(10, 2))";
        $rows = $connection->executeQuery(
            <<<SQL
                SELECT name, spec, overall_rank,
                       {$overallPointsExpression} AS overall_points
                FROM uwu_log_ranks
                WHERE name IN (?) AND realm = ?
                SQL,
            [array_values(array_unique($names)), trim($realm)],
            [ArrayParameterType::STRING, \PDO::PARAM_STR],
        )->fetchAllAssociative();

        $best = [];
        foreach ($rows as $row) {
            $key = strtolower((string) $row['name']);
            $points = (float) ($row['overall_points'] ?? 0);
            $rank = (int) $row['overall_rank'];
            $current = $best[$key] ?? null;
            if ($current === null
                || $points > $current->getOverallPoints()
                || ($points === $current->getOverallPoints() && $rank > 0
                    && ($current->getOverallRank() === 0 || $rank < $current->getOverallRank()))) {
                $best[$key] = new GuildRankSummary($points, $rank, (string) $row['spec']);
            }
        }

        return $best;
    }

    private static function characterKey(string $name, string $realm): string
    {
        return strtolower(trim($name))."\0".strtolower(trim($realm));
    }
}
