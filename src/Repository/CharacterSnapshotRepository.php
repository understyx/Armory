<?php

namespace App\Repository;

use App\Entity\CharacterSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CharacterSnapshot>
 */
class CharacterSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CharacterSnapshot::class);
    }

    public function findByNameAndRealm(string $name, string $realm): ?CharacterSnapshot
    {
        return $this->createQueryBuilder('c')
            ->where('c.name = :name')
            ->andWhere('c.realm = :realm')
            ->setParameter('name', trim($name))
            ->setParameter('realm', trim($realm))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<array{name: string, realm: string}> */
    public function findRecentlyViewed(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.name AS name, c.realm AS realm')
            ->orderBy('c.scrapedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();
    }

    public function existsByNameAndRealm(string $name, string $realm): bool
    {
        return $this->createQueryBuilder('c')
            ->select('c.id')
            ->where('c.name = :name')
            ->andWhere('c.realm = :realm')
            ->setParameter('name', trim($name))
            ->setParameter('realm', trim($realm))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    /**
     * Returns a partial entity; do not follow this with a full lookup in the same
     * EntityManager because the identity map will return the partial instance.
     */
    public function findViewByNameAndRealm(string $name, string $realm): ?CharacterSnapshot
    {
        return $this->createQueryBuilder('c')
            ->select('PARTIAL c.{id, name, realm, level, race, class, guild, gearScore, avgIlvl, professions, specializations, equippedItems, characterModel, talentStrings, talentTreesData, glyphs, enchantsStatus, gemsStatus, killStats, pvpStats, matchHistory, scrapedAt}')
            ->where('c.name = :name')
            ->andWhere('c.realm = :realm')
            ->setParameter('name', trim($name))
            ->setParameter('realm', trim($realm))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns a partial entity; do not follow this with a full lookup in the same
     * EntityManager because the identity map will return the partial instance.
     */
    public function findStatsByNameAndRealm(string $name, string $realm): ?CharacterSnapshot
    {
        return $this->createQueryBuilder('c')
            ->select('PARTIAL c.{id, name, realm, level, race, class, equippedItems, talentStrings, talentTreesData, specializations, scrapedAt}')
            ->where('c.name = :name')
            ->andWhere('c.realm = :realm')
            ->setParameter('name', trim($name))
            ->setParameter('realm', trim($realm))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns a partial entity; do not follow this with a full lookup in the same
     * EntityManager because the identity map will return the partial instance.
     */
    public function findApiByNameAndRealm(string $name, string $realm): ?CharacterSnapshot
    {
        return $this->createQueryBuilder('c')
            ->select('PARTIAL c.{id, name, realm, level, race, class, guild, gearScore, avgIlvl, professions, specializations, equippedItems, characterModel, talentStrings, talentTreesData, raidAchievements, scrapedAt}')
            ->where('c.name = :name')
            ->andWhere('c.realm = :realm')
            ->setParameter('name', trim($name))
            ->setParameter('realm', trim($realm))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns a partial entity; do not follow this with a full lookup in the same
     * EntityManager because the identity map will return the partial instance.
     */
    public function findAchievementsByNameAndRealm(string $name, string $realm): ?CharacterSnapshot
    {
        return $this->createQueryBuilder('c')
            ->select('PARTIAL c.{id, name, realm, raidAchievements, scrapedAt}')
            ->where('c.name = :name')
            ->andWhere('c.realm = :realm')
            ->setParameter('name', trim($name))
            ->setParameter('realm', trim($realm))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Persists a snapshot and reports whether the stored character data changed.
     *
     * The scrape timestamp is deliberately ignored when comparing snapshots. This
     * prevents an unchanged scrape from looking like a successful data refresh.
     */
    public function upsert(CharacterSnapshot $snapshot): bool
    {
        $em = $this->getEntityManager();
        $existing = $this->findByNameAndRealm($snapshot->getName(), $snapshot->getRealm());

        if ($existing !== null) {
            if ($this->hasSameData($existing, $snapshot)) {
                return false;
            }

            $existing->setLevel($snapshot->getLevel());
            $existing->setRace($snapshot->getRace());
            $existing->setClass($snapshot->getClass());
            $existing->setGuild($snapshot->getGuild());
            $existing->setGearScore($snapshot->getGearScore());
            $existing->setAvgIlvl($snapshot->getAvgIlvl());
            $existing->setProfessions($snapshot->getProfessions());
            $existing->setSpecializations($snapshot->getSpecializations());
            $existing->setEquippedItems($snapshot->getEquippedItems());
            $existing->setCharacterModel($snapshot->getCharacterModel());
            $existing->setTalentStrings($snapshot->getTalentStrings());
            $existing->setTalentTreesData($snapshot->getTalentTreesData());
            $existing->setGlyphs($snapshot->getGlyphs());
            $existing->setEnchantsStatus($snapshot->getEnchantsStatus());
            $existing->setGemsStatus($snapshot->getGemsStatus());
            $existing->setKillStats($snapshot->getKillStats());
            $existing->setPvpStats($snapshot->getPvpStats());
            $existing->setMatchHistory($snapshot->getMatchHistory());
            $existing->setScrapedAt($snapshot->getScrapedAt() ?? new \DateTimeImmutable());
        } else {
            if ($snapshot->getScrapedAt() === null) {
                $snapshot->setScrapedAt(new \DateTimeImmutable());
            }
            $em->persist($snapshot);
        }

        $em->flush();

        return true;
    }

    private function hasSameData(CharacterSnapshot $existing, CharacterSnapshot $snapshot): bool
    {
        return $existing->getLevel() === $snapshot->getLevel()
            && $existing->getRace() === $snapshot->getRace()
            && $existing->getClass() === $snapshot->getClass()
            && $existing->getGuild() === $snapshot->getGuild()
            && $existing->getGearScore() === $snapshot->getGearScore()
            && $existing->getAvgIlvl() === $snapshot->getAvgIlvl()
            && $existing->getProfessions() === $snapshot->getProfessions()
            && $existing->getSpecializations() === $snapshot->getSpecializations()
            && $existing->getEquippedItems() === $snapshot->getEquippedItems()
            && $existing->getCharacterModel() === $snapshot->getCharacterModel()
            && $existing->getTalentStrings() === $snapshot->getTalentStrings()
            && $existing->getTalentTreesData() === $snapshot->getTalentTreesData()
            && $existing->getGlyphs() === $snapshot->getGlyphs()
            && $existing->getEnchantsStatus() === $snapshot->getEnchantsStatus()
            && $existing->getGemsStatus() === $snapshot->getGemsStatus()
            && $existing->getKillStats() === $snapshot->getKillStats()
            && $existing->getPvpStats() === $snapshot->getPvpStats()
            && $existing->getMatchHistory() === $snapshot->getMatchHistory();
    }

    public function save(CharacterSnapshot $snapshot): void
    {
        $this->getEntityManager()->persist($snapshot);
        $this->getEntityManager()->flush();
    }

    public function purgeOlderThan(\DateTimeImmutable $cutoff): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.scrapedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
