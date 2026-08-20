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
}
