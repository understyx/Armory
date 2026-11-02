<?php

namespace App\Service;

use App\Entity\CharacterSnapshot;
use App\Message\EnrichItemTooltipMessage;
use App\Message\EnrichSpellTooltipMessage;
use App\Repository\TooltipEnrichmentRepository;
use App\Repository\WowItemRepository;
use App\Repository\WowSpellTooltipRepository;
use Symfony\Component\Messenger\MessageBusInterface;

class CharacterTooltipEnrichmentQueue
{
    public function __construct(
        private readonly WowSpellTooltipRepository $spellTooltipRepository,
        private readonly WowItemRepository $wowItemRepository,
        private readonly TooltipEnrichmentRepository $itemTooltipRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function queueAfterRefresh(CharacterSnapshot $snapshot): int
    {
        $queued = $this->queueSpellIds($this->spellIds($snapshot->getTalentTreesData() ?? []));
        $queued += $this->queueGlyphItems($snapshot->getGlyphs());

        return $queued;
    }

    /** @param int[] $spellIds */
    private function queueSpellIds(array $spellIds): int
    {
        $queued = 0;
        foreach ($this->spellTooltipRepository->findQueueableSpellIds($spellIds) as $spellId) {
            $spellId = (int) $spellId;
            // Mark first: a fast worker's saved tooltip must not be replaced by the queued row.
            $this->spellTooltipRepository->markQueued($spellId);
            DeduplicatingMessageDispatcher::dispatch($this->messageBus, new EnrichSpellTooltipMessage($spellId));
            $queued++;
        }

        return $queued;
    }

    /** @param array<string, mixed> $glyphs */
    private function queueGlyphItems(array $glyphs): int
    {
        $names = [];
        foreach ($glyphs as $specGlyphs) {
            if (!is_array($specGlyphs)) {
                continue;
            }
            foreach (['Major Glyphs', 'Minor Glyphs'] as $kind) {
                foreach (is_array($specGlyphs[$kind] ?? null) ? $specGlyphs[$kind] : [] as $name) {
                    if (is_string($name) && trim($name) !== '') {
                        $names[] = trim($name);
                    }
                }
            }
        }
        $idsByName = $this->wowItemRepository->findGlyphItemIdsByNames($names);
        $items = $this->wowItemRepository->findByItemIds(array_values(array_unique($idsByName)));
        if ($items === []) {
            return 0;
        }

        $spellsByItem = [];
        $setIdsByItem = [];
        foreach ($items as $itemId => $item) {
            $tooltip = $item->getTooltipData() ?? [];
            $spells = array_filter(
                $tooltip['spells'] ?? [],
                static fn (mixed $spell): bool => is_array($spell) && (int) ($spell['id'] ?? 0) > 0,
            );
            if ($spells === []) {
                continue;
            }
            $spellsByItem[$itemId] = $spells;
            $setIdsByItem[$itemId] = (int) ($tooltip['item_set_id'] ?? 0);
        }
        if ($spellsByItem === []) {
            return 0;
        }

        $details = $this->itemTooltipRepository->findForItems(array_keys($spellsByItem), $setIdsByItem);
        $queued = 0;
        foreach (array_keys($spellsByItem) as $itemId) {
            $itemDetails = $details[$itemId] ?? ['effects' => [], 'checked' => false];
            if ($itemDetails['checked'] || $itemDetails['effects'] !== [] || !$this->itemTooltipRepository->shouldQueue((int) $itemId)) {
                continue;
            }

            DeduplicatingMessageDispatcher::dispatch(
                $this->messageBus,
                new EnrichItemTooltipMessage((int) $itemId, $setIdsByItem[$itemId] ?? 0),
            );
            $this->itemTooltipRepository->markQueued((int) $itemId);
            $queued++;
        }

        return $queued;
    }

    /** @param array<mixed> $data @return int[] */
    private function spellIds(array $data): array
    {
        $ids = [];
        $walk = static function (array $value) use (&$walk, &$ids): void {
            if (isset($value['spellId']) && is_scalar($value['spellId']) && (int) $value['spellId'] > 0) {
                $ids[(int) $value['spellId']] = true;
            }
            foreach ($value as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($data);

        return array_keys($ids);
    }
}
