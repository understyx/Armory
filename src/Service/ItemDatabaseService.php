<?php

namespace App\Service;

use App\Entity\WowItem;
use App\Message\EnrichItemTooltipMessage;
use App\Repository\TooltipEnrichmentRepository;
use App\Repository\WowItemRepository;
use Symfony\Component\Messenger\MessageBusInterface;

class ItemDatabaseService
{
    public function __construct(
        private readonly WowItemRepository $wowItemRepository,
        private readonly ?ItemIconResolverService $iconResolverService = null,
        private readonly ?TooltipEnrichmentRepository $tooltipEnrichmentRepository = null,
        private readonly ?MessageBusInterface $messageBus = null,
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

        $items = [$itemId => $formatted];
        $this->applyTooltipEnrichment($items);

        return $items[$itemId];
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

        $this->applyTooltipEnrichment($result);

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
            'tooltip' => $item->getTooltipData(),
            'source_build' => $item->getSourceBuild(),
            'gear_score_source' => $item->getGearScoreSource(),
            'gear_score_version' => $item->getGearScoreVersion(),
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function applyTooltipEnrichment(array &$items): void
    {
        if ($items === [] || $this->tooltipEnrichmentRepository === null) {
            return;
        }

        $setIdsByItem = [];
        foreach ($items as $itemId => $item) {
            $setIdsByItem[$itemId] = (int) ($item['tooltip']['item_set_id'] ?? 0);
        }
        $enrichment = $this->tooltipEnrichmentRepository->findForItems(array_keys($items), $setIdsByItem);

        foreach ($items as $itemId => &$item) {
            $details = $enrichment[$itemId] ?? ['effects' => [], 'set' => null, 'checked' => false];
            $item['external_effects'] = $details['effects'];
            $item['item_set_details'] = $details['set'];

            $hasSpells = array_filter(
                $item['tooltip']['spells'] ?? [],
                static fn (array $spell): bool => (int) ($spell['id'] ?? 0) > 0
            ) !== [];
            $setId = $setIdsByItem[$itemId] ?? 0;
            $needsEffects = $hasSpells && $details['effects'] === [];
            $needsSet = $setId > 0 && $details['set'] === null;
            if (
                !$details['checked']
                && ($needsEffects || $needsSet)
                && $this->messageBus !== null
                && $this->tooltipEnrichmentRepository->shouldQueue($itemId)
            ) {
                $this->messageBus->dispatch(new EnrichItemTooltipMessage($itemId, $setId));
                $this->tooltipEnrichmentRepository->markQueued($itemId);
            }
        }
        unset($item);
    }
}
