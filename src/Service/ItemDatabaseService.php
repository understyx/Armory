<?php

namespace App\Service;

use App\Entity\WowItem;
use App\Repository\WowItemRepository;

class ItemDatabaseService
{
    public function __construct(
        private readonly WowItemRepository $wowItemRepository,
        private readonly ?ItemIconResolverService $iconResolverService = null
    ) {
    }

    public function getItem(int $itemId): ?array
    {
        $item = $this->wowItemRepository->findByItemId($itemId);
        if ($item === null) {
            return null;
        }

        $formatted = $this->formatItem($item);
        if (empty($formatted['icon']) && $this->iconResolverService !== null) {
            $resolvedIcon = $this->iconResolverService->resolveIcon($itemId);
            if ($resolvedIcon !== null) {
                $formatted['icon'] = $resolvedIcon;
            }
        }

        return $formatted;
    }

    /**
     * @param int[] $itemIds
     * @return array<int, array>
     */
    public function getItemsBulk(array $itemIds): array
    {
        $items = $this->wowItemRepository->findByItemIds($itemIds);
        $result = [];
        $missingIconIds = [];

        foreach ($items as $id => $item) {
            $formatted = $this->formatItem($item);
            $result[$id] = $formatted;
            if (empty($formatted['icon'])) {
                $missingIconIds[] = $id;
            }
        }

        if (!empty($missingIconIds) && $this->iconResolverService !== null) {
            $resolvedIcons = $this->iconResolverService->resolveIconsBulk($missingIconIds);
            foreach ($resolvedIcons as $id => $icon) {
                if ($icon !== null && isset($result[$id])) {
                    $result[$id]['icon'] = $icon;
                }
            }
        }

        return $result;
    }

    private function formatItem(WowItem $item): array
    {
        return [
            'name' => $item->getName(),
            'ilvl' => $item->getItemLevel() ?? 0,
            'quality' => $item->getQuality() ?? 0,
            'type' => $item->getType() ?? 0,
            'requires' => (string) ($item->getRequires() ?? 0),
            'class' => $item->getClass() ?? 0,
            'subclass' => $item->getSubclass() ?? 0,
            'gem_slots' => $item->getGemSlots() ?? 0,
            'gs' => (float) ($item->getGearScore() ?? 0),
            'icon' => $item->getIcon(),
        ];
    }
}
