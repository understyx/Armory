<?php

namespace App\Repository;

use App\Entity\WowItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WowItem>
 */
class WowItemRepository extends ServiceEntityRepository
{
    private array $cache = [];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WowItem::class);
    }

    public function findByItemId(int $id): ?WowItem
    {
        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }

        $item = $this->find($id);
        $this->cache[$id] = $item;

        return $item;
    }

    /**
     * @param int[] $ids
     * @return array<int, WowItem> Indexed by item_id
     */
    public function findByItemIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return [];
        }

        $missingIds = [];
        $result = [];

        foreach ($ids as $id) {
            if (array_key_exists($id, $this->cache)) {
                if ($this->cache[$id] !== null) {
                    $result[$id] = $this->cache[$id];
                }
            } else {
                $missingIds[] = $id;
            }
        }

        if (!empty($missingIds)) {
            /** @var WowItem[] $fetchedItems */
            $fetchedItems = $this->createQueryBuilder('w')
                ->where('w.itemId IN (:ids)')
                ->setParameter('ids', $missingIds)
                ->getQuery()
                ->getResult();

            $fetchedMap = [];
            foreach ($fetchedItems as $item) {
                $fetchedMap[$item->getItemId()] = $item;
            }

            foreach ($missingIds as $id) {
                $item = $fetchedMap[$id] ?? null;
                $this->cache[$id] = $item;
                if ($item !== null) {
                    $result[$id] = $item;
                }
            }
        }

        return $result;
    }

    /**
     * Resolve stored glyph names to their lowest class-16 item ID.
     *
     * @param string[] $names
     * @return array<string, int>
     */
    public function findGlyphItemIdsByNames(array $names): array
    {
        $names = array_values(array_unique(array_filter(
            array_map(static fn (mixed $name): string => trim((string) $name), $names),
            static fn (string $name): bool => $name !== '',
        )));
        if ($names === []) {
            return [];
        }

        $items = $this->createQueryBuilder('w')
            ->where('w.class = :itemClass')
            ->andWhere('w.name IN (:names)')
            ->setParameter('itemClass', 16)
            ->setParameter('names', $names)
            ->orderBy('w.itemId', 'ASC')
            ->getQuery()
            ->getResult();

        $wanted = array_fill_keys($names, true);
        $resolved = [];
        foreach ($items as $item) {
            $name = $item->getName();
            if ($name !== null && isset($wanted[$name]) && !isset($resolved[$name])) {
                $resolved[$name] = (int) $item->getItemId();
            }
        }

        return $resolved;
    }
}
