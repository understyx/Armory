<?php

namespace App\Service;

use App\Repository\WowItemRepository;
use App\Repository\WowSpellTooltipRepository;

final class CharacterPageTooltipService
{
    public function __construct(
        private readonly WowItemRepository $wowItemRepository,
        private readonly ItemDatabaseService $itemDatabaseService,
        private readonly ItemTooltipService $itemTooltipService,
        private readonly WowSpellTooltipRepository $spellTooltipRepository,
    ) {
    }

    /**
     * @param array<string, array<string, mixed>> $tooltips
     * @param array<int, array<string, mixed>> $parsedSpecs
     * @param array<string, mixed> $glyphs
     * @return array<string, string> glyph name to tooltip key
     */
    public function addCustomTooltips(array &$tooltips, array &$parsedSpecs, array $glyphs): array
    {
        $glyphNames = [];
        foreach ($glyphs as $specGlyphs) {
            if (!is_array($specGlyphs)) {
                continue;
            }
            foreach (['Major Glyphs', 'Minor Glyphs'] as $kind) {
                foreach (is_array($specGlyphs[$kind] ?? null) ? $specGlyphs[$kind] : [] as $name) {
                    if (is_string($name) && trim($name) !== '') {
                        $glyphNames[] = trim($name);
                    }
                }
            }
        }
        $glyphIds = $this->wowItemRepository->findGlyphItemIdsByNames($glyphNames);
        $glyphItems = $glyphIds !== []
            ? $this->itemDatabaseService->getItemsBulk(array_values(array_unique($glyphIds)))
            : [];
        $glyphTooltipKeys = [];
        foreach ($glyphIds as $name => $itemId) {
            $item = $glyphItems[$itemId] ?? null;
            if ($item === null) {
                continue;
            }

            $item['id'] = $itemId;
            [$item['icon_url'], $item['icon_fallback_url']] = $this->iconUrls($item['icon'] ?? null);
            $tooltip = $this->itemTooltipService->build($item) ?? [
                'id' => $itemId,
                'name' => (string) ($item['name'] ?? $name),
                'quality' => (int) ($item['quality'] ?? 1),
                'icon_url' => $item['icon_url'],
                'icon_fallback_url' => $item['icon_fallback_url'],
            ];

            $key = 'item-'.$itemId;
            $tooltips[$key] = $tooltip;
            $glyphTooltipKeys[$name] = $key;
        }

        $spellIds = [];
        foreach ($parsedSpecs as $spec) {
            foreach ($spec['trees'] ?? [] as $tree) {
                foreach ($tree['tiers'] ?? [] as $tier) {
                    foreach ($tier as $talent) {
                        if (!is_array($talent)) {
                            continue;
                        }
                        $spellId = (int) ($talent['spellId'] ?? 0);
                        if ($spellId > 0) {
                            $spellIds[] = $spellId;
                        }
                    }
                }
            }
        }
        $spellTooltips = $this->spellTooltipRepository->findForSpellIds(array_values(array_unique($spellIds)));

        foreach ($parsedSpecs as &$spec) {
            if (!is_array($spec['trees'] ?? null)) {
                continue;
            }
            foreach ($spec['trees'] as &$tree) {
                if (!is_array($tree['tiers'] ?? null)) {
                    continue;
                }
                foreach ($tree['tiers'] as &$tier) {
                    foreach ($tier as &$talent) {
                        if (!is_array($talent)) {
                            continue;
                        }
                        $spellId = (int) ($talent['spellId'] ?? 0);
                        if ($spellId <= 0) {
                            continue;
                        }

                        $pointsText = (string) ($talent['pointsText'] ?? '0/0');
                        $pointKey = trim((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $pointsText), '-');
                        $key = sprintf('spell-%d-%s', $spellId, $pointKey !== '' ? $pointKey : 'rank');
                        $stored = $spellTooltips[$spellId] ?? null;
                        $name = trim((string) ($stored['name'] ?? ''));
                        if ($name !== '') {
                            $talent['name'] = $name;
                        }

                        $tooltips[$key] = [
                            'name' => $name !== '' ? $name : 'Talent',
                            'quality' => 1,
                            'icon_url' => $talent['iconUrl'] ?? null,
                            'icon_fallback_url' => $talent['iconFallbackUrl'] ?? null,
                            'spell_rank' => 'Rank '.$pointsText,
                            'spell_description' => (string) ($stored['description'] ?? ''),
                        ];
                        $talent['tooltip_key'] = $key;
                    }
                    unset($talent);
                }
                unset($tier);
            }
            unset($tree);
        }
        unset($spec);

        return $glyphTooltipKeys;
    }

    /** @return array{?string, ?string} */
    private function iconUrls(?string $icon): array
    {
        if ($icon === null || trim($icon) === '') {
            return [null, null];
        }

        $clean = strtolower(pathinfo($icon, PATHINFO_FILENAME));
        if (!preg_match('/\A[a-z0-9][a-z0-9_-]{0,127}\z/', $clean)) {
            return [null, null];
        }

        $encoded = rawurlencode($clean);

        return [
            'https://wow.zamimg.com/images/wow/icons/large/'.$encoded.'.jpg',
            '/wow-icons/large/'.$encoded.'.jpg',
        ];
    }
}
