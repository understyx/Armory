<?php

namespace App\Service;

use App\Enum\ItemTypes;

class PaperdollService
{
    public function __construct(
        private readonly ItemDatabaseService $itemDatabaseService,
        private readonly ?ItemTooltipService $itemTooltipService = null,
    ) {
    }

    /**
     * Maps a raw list of equipped items into standard Paperdoll slots.
     * Returns an array with 'slots' (keyed by slot name) and 'enrichedItems'.
     */
    public function buildPaperdollSlots(
        array $equippedItems,
        ?string $characterClass = null,
        bool $includeTooltips = true,
    ): array
    {
        $canDualWieldTwoHandedWeapons = strcasecmp($characterClass ?? '', 'Warrior') === 0;
        $itemIds = array_values(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, array_column($equippedItems, 'id')),
            static fn (int $id): bool => $id > 0
        ));
        $transmogIds = array_values(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, array_column($equippedItems, 'transmog')),
            static fn (int $id): bool => $id > 0
        ));

        $gemDetailsByItem = [];
        $gemItemIds = [];
        foreach ($equippedItems as $index => $item) {
            $gemDetailsByItem[$index] = [];
            foreach ($item['gems'] ?? [] as $gemEnchantId) {
                $gemEnchantId = (int) $gemEnchantId;
                $gem = $gemEnchantId > 0 ? $this->resolveGemFromEnchantId($gemEnchantId) : null;
                $gemDetailsByItem[$index][] = $gem;
                if ($includeTooltips && $this->itemTooltipService !== null && (int) ($gem['id'] ?? 0) > 0) {
                    $gemItemIds[] = (int) $gem['id'];
                }
            }
        }

        // Resolve gems from enchant IDs first, then fetch all related item data together.
        $lookupIds = array_values(array_unique([...$itemIds, ...$transmogIds, ...$gemItemIds]));
        $dbItems = $lookupIds !== [] ? $this->itemDatabaseService->getItemsBulk($lookupIds) : [];

        $enrichedItems = [];
        foreach ($equippedItems as $index => $item) {
            $id = isset($item['id']) ? (int)$item['id'] : null;
            $dbData = ($id && isset($dbItems[$id])) ? $dbItems[$id] : [];

            $enchantId = $item['enchant'] ?? null;
            $enchantName = null;
            if ($enchantId) {
                $enchantName = EnchantDatabase::ENCHANTS[$enchantId] ?? "Enchant #{$enchantId}";
            }

            $rawGems = $item['gems'] ?? [];
            $gemDetails = $gemDetailsByItem[$index] ?? [];
            foreach ($gemDetails as &$gem) {
                if ($gem !== null) {
                    $gem['tooltip_key'] = (int) ($gem['id'] ?? 0) > 0
                        ? 'item-'.(int) $gem['id']
                        : 'item-0-'.(int) ($gem['enchant_id'] ?? 0);
                }
            }
            unset($gem);

            $transmogId = (int) ($item['transmog'] ?? 0);
            $transmogData = $transmogId > 0 ? ($dbItems[$transmogId] ?? []) : [];

            $enrichedItems[] = [
                'id' => $id,
                'tooltip_key' => $id !== null ? $id . '-' . $index : null,
                'name' => $item['name'] ?? ($dbData['name'] ?? ($id ? "Item #{$id}" : "Unknown Item")),
                'quality' => $item['quality'] ?? ($dbData['quality'] ?? 1),
                'type' => $item['type'] ?? ($dbData['type'] ?? null),
                'class' => $item['class'] ?? ($dbData['class'] ?? null),
                'subclass' => $item['subclass'] ?? ($dbData['subclass'] ?? null),
                'icon' => $item['icon'] ?? ($dbData['icon'] ?? null),
                'ilvl' => $item['ilvl'] ?? ($dbData['ilvl'] ?? 0),
                'gs' => $item['gs'] ?? ($dbData['gs'] ?? 0),
                'enchant' => $enchantId,
                'enchant_name' => $enchantName,
                'gems' => $rawGems,
                'gem_details' => $gemDetails,
                'transmog' => $transmogId > 0 ? $transmogId : null,
                'transmog_item' => $transmogId > 0 ? [
                    'id' => $transmogId,
                    'name' => $transmogData['name'] ?? "Item #{$transmogId}",
                    'quality' => (int) ($transmogData['quality'] ?? 4),
                ] : null,
                'tooltip' => $item['tooltip'] ?? ($dbData['tooltip'] ?? null),
                'external_effects' => $item['external_effects'] ?? ($dbData['external_effects'] ?? []),
                'item_set_details' => $item['item_set_details'] ?? ($dbData['item_set_details'] ?? null),
                'orig_index' => $index,
            ];
        }

        foreach ($enrichedItems as &$enrichedItem) {
            $enrichedItem['icon_url'] = $this->externalIconUrl($enrichedItem['icon'] ?? null);
            $enrichedItem['icon_fallback_url'] = $this->localIconUrl($enrichedItem['icon'] ?? null);
        }
        unset($enrichedItem);

        $setCounts = [];
        $equippedItemIds = [];
        $equippedItemNames = [];
        foreach ($enrichedItems as $enrichedItem) {
            if ((int) ($enrichedItem['id'] ?? 0) > 0) {
                $equippedItemIds[] = (int) $enrichedItem['id'];
            }
            if (trim((string) ($enrichedItem['name'] ?? '')) !== '') {
                $equippedItemNames[] = (string) $enrichedItem['name'];
            }
            $setId = (int) ($enrichedItem['tooltip']['item_set_id'] ?? 0);
            if ($setId > 0) {
                $setCounts[$setId] = ($setCounts[$setId] ?? 0) + 1;
            }
        }

        $itemTooltips = [];
        if ($includeTooltips && $this->itemTooltipService !== null) {
            $equippedSetSlots = $this->addSetMemberTypes($enrichedItems, $dbItems);
            foreach ($enrichedItems as &$enrichedItem) {
                $tooltip = $this->itemTooltipService->build(
                    $enrichedItem,
                    $setCounts,
                    $equippedItemIds,
                    $equippedItemNames,
                    $equippedSetSlots,
                );
                if ($tooltip !== null && $enrichedItem['tooltip_key'] !== null) {
                    $enrichedItem['display_tooltip'] = $tooltip;
                    $itemTooltips[$enrichedItem['tooltip_key']] = $tooltip;
                }
            }
            unset($enrichedItem);

            foreach ($enrichedItems as $enrichedItem) {
                foreach ($enrichedItem['gem_details'] as $gem) {
                    if ($gem === null || isset($itemTooltips[$gem['tooltip_key']])) {
                        continue;
                    }

                    $itemId = (int) ($gem['id'] ?? 0);
                    $gemItem = $itemId > 0 ? ($dbItems[$itemId] ?? null) : null;
                    $tooltip = null;
                    if ($gemItem !== null) {
                        $gemItem['id'] = $itemId;
                        $gemItem['icon_url'] = $this->externalIconUrl($gemItem['icon'] ?? null);
                        $gemItem['icon_fallback_url'] = $this->localIconUrl($gemItem['icon'] ?? null);
                        $tooltip = $this->itemTooltipService->build($gemItem);
                    }

                    $tooltip ??= [
                        'id' => $itemId,
                        'name' => (string) $gem['name'],
                        'quality' => (int) $gem['quality'],
                        'icon_url' => $gem['icon_url'] ?? null,
                        'icon_fallback_url' => $gem['icon_fallback_url'] ?? null,
                    ];
                    $tooltip['gem_effect'] = (string) $gem['effect'];
                    $itemTooltips[$gem['tooltip_key']] = $tooltip;
                }
            }
        }

        $slots = [
            'head'      => ['name' => 'Head', 'types' => [ItemTypes::HEAD->value], 'fallback' => '🪖', 'should_have_enchant' => true, 'item' => null],
            'neck'      => ['name' => 'Neck', 'types' => [ItemTypes::NECK->value], 'fallback' => '📿', 'should_have_enchant' => false, 'item' => null],
            'shoulder'  => ['name' => 'Shoulders', 'types' => [ItemTypes::SHOULDER->value], 'fallback' => '🛡️', 'should_have_enchant' => true, 'item' => null],
            'back'      => ['name' => 'Back', 'types' => [ItemTypes::BACK->value], 'fallback' => '🧥', 'should_have_enchant' => true, 'item' => null],
            'chest'     => ['name' => 'Chest', 'types' => [ItemTypes::CHEST->value, ItemTypes::ROBE->value], 'fallback' => '🥋', 'should_have_enchant' => true, 'item' => null],
            'shirt'     => ['name' => 'Shirt', 'types' => [ItemTypes::SHIRT->value], 'fallback' => '👕', 'should_have_enchant' => false, 'item' => null],
            'tabard'    => ['name' => 'Tabard', 'types' => [ItemTypes::TABARD->value], 'fallback' => '🚩', 'should_have_enchant' => false, 'item' => null],
            'wrist'     => ['name' => 'Wrist', 'types' => [ItemTypes::WRIST->value], 'fallback' => '⌚', 'should_have_enchant' => true, 'item' => null],

            'hands'     => ['name' => 'Hands', 'types' => [ItemTypes::GLOVES->value], 'fallback' => '🧤', 'should_have_enchant' => true, 'item' => null],
            'waist'     => ['name' => 'Waist', 'types' => [ItemTypes::WAIST->value], 'fallback' => '🥋', 'should_have_enchant' => false, 'item' => null],
            'legs'      => ['name' => 'Legs', 'types' => [ItemTypes::LEGS->value], 'fallback' => '👖', 'should_have_enchant' => true, 'item' => null],
            'feet'      => ['name' => 'Feet', 'types' => [ItemTypes::FEET->value], 'fallback' => '🥾', 'should_have_enchant' => true, 'item' => null],
            'finger1'   => ['name' => 'Ring 1', 'types' => [ItemTypes::RING->value], 'fallback' => '💍', 'should_have_enchant' => false, 'item' => null],
            'finger2'   => ['name' => 'Ring 2', 'types' => [ItemTypes::RING->value], 'fallback' => '💍', 'should_have_enchant' => false, 'item' => null],
            'trinket1'  => ['name' => 'Trinket 1', 'types' => [ItemTypes::TRINKET->value], 'fallback' => '🔮', 'should_have_enchant' => false, 'item' => null],
            'trinket2'  => ['name' => 'Trinket 2', 'types' => [ItemTypes::TRINKET->value], 'fallback' => '🔮', 'should_have_enchant' => false, 'item' => null],

            'mainhand'  => ['name' => 'Main Hand', 'types' => [ItemTypes::WEAPON_1H->value, ItemTypes::WEAPON_2H->value, ItemTypes::WEAPON_MAINHAND->value], 'fallback' => '⚔️', 'should_have_enchant' => true, 'item' => null],
            'offhand'   => ['name' => 'Off Hand', 'types' => [ItemTypes::WEAPON_1H->value, ItemTypes::WEAPON_OFFHAND->value, ItemTypes::SHIELD->value, ItemTypes::OFF_HAND->value], 'fallback' => '🛡️', 'should_have_enchant' => true, 'item' => null],
            'ranged'    => ['name' => 'Ranged / Relic', 'types' => [ItemTypes::BOW->value, ItemTypes::THROWN->value, ItemTypes::RANGED->value, ItemTypes::RELIC->value], 'fallback' => '🏹', 'should_have_enchant' => false, 'item' => null],
        ];

        $assignedIndices = [];

        // First Pass: Primary matching by exact ItemTypes enum value
        foreach ($enrichedItems as $idx => $item) {
            $type = $item['type'] !== null ? (int)$item['type'] : null;
            if ($type === null) {
                continue;
            }

            if ($type === ItemTypes::HEAD->value && $slots['head']['item'] === null) {
                $slots['head']['item'] = $item; $assignedIndices[$idx] = true;
            } elseif ($type === ItemTypes::NECK->value && $slots['neck']['item'] === null) {
                $slots['neck']['item'] = $item; $assignedIndices[$idx] = true;
            } elseif ($type === ItemTypes::SHOULDER->value && $slots['shoulder']['item'] === null) {
                $slots['shoulder']['item'] = $item; $assignedIndices[$idx] = true;
            } elseif ($type === ItemTypes::BACK->value && $slots['back']['item'] === null) {
                $slots['back']['item'] = $item; $assignedIndices[$idx] = true;
            } elseif (($type === ItemTypes::CHEST->value || $type === ItemTypes::ROBE->value) && $slots['chest']['item'] === null) {
                $slots['chest']['item'] = $item; $assignedIndices[$idx] = true;
            } elseif ($type === ItemTypes::SHIRT->value && $slots['shirt']['item'] === null) {
                $slots['shirt']['item'] = $item; $assignedIndices[$idx] = true;
            } elseif ($type === ItemTypes::TABARD->value && $slots['tabard']['item'] === null) {
                $slots['tabard']['item'] = $item; $assignedIndices[$idx] = true;
            } elseif ($type === ItemTypes::WRIST->value && $slots['wrist']['item'] === null) {
                $slots['wrist']['item'] = $item; $assignedIndices[$idx] = true;
            } elseif ($type === ItemTypes::GLOVES->value && $slots['hands']['item'] === null) {
                $slots['hands']['item'] = $item; $assignedIndices[$idx] = true;
            } elseif ($type === ItemTypes::WAIST->value && $slots['waist']['item'] === null) {
                $slots['waist']['item'] = $item; $assignedIndices[$idx] = true;
            } elseif ($type === ItemTypes::LEGS->value && $slots['legs']['item'] === null) {
                $slots['legs']['item'] = $item; $assignedIndices[$idx] = true;
            } elseif ($type === ItemTypes::FEET->value && $slots['feet']['item'] === null) {
                $slots['feet']['item'] = $item; $assignedIndices[$idx] = true;
            } elseif ($type === ItemTypes::RING->value) {
                if ($slots['finger1']['item'] === null) {
                    $slots['finger1']['item'] = $item; $assignedIndices[$idx] = true;
                } elseif ($slots['finger2']['item'] === null) {
                    $slots['finger2']['item'] = $item; $assignedIndices[$idx] = true;
                }
            } elseif ($type === ItemTypes::TRINKET->value) {
                if ($slots['trinket1']['item'] === null) {
                    $slots['trinket1']['item'] = $item; $assignedIndices[$idx] = true;
                } elseif ($slots['trinket2']['item'] === null) {
                    $slots['trinket2']['item'] = $item; $assignedIndices[$idx] = true;
                }
            } elseif ($type === ItemTypes::WEAPON_2H->value) {
                if ($slots['mainhand']['item'] === null) {
                    $slots['mainhand']['item'] = $item; $assignedIndices[$idx] = true;
                } elseif ($canDualWieldTwoHandedWeapons && $slots['offhand']['item'] === null) {
                    // Warriors with Titan's Grip can equip a second 2H weapon.
                    $slots['offhand']['item'] = $item; $assignedIndices[$idx] = true;
                }
            } elseif ($type === ItemTypes::WEAPON_MAINHAND->value) {
                if ($slots['mainhand']['item'] === null) {
                    $slots['mainhand']['item'] = $item; $assignedIndices[$idx] = true;
                }
            } elseif ($type === ItemTypes::WEAPON_1H->value) {
                if ($slots['mainhand']['item'] === null) {
                    $slots['mainhand']['item'] = $item; $assignedIndices[$idx] = true;
                } elseif ($slots['offhand']['item'] === null) {
                    $slots['offhand']['item'] = $item; $assignedIndices[$idx] = true;
                }
            } elseif ($type === ItemTypes::WEAPON_OFFHAND->value || $type === ItemTypes::SHIELD->value || $type === ItemTypes::OFF_HAND->value) {
                if ($slots['offhand']['item'] === null) {
                    $slots['offhand']['item'] = $item; $assignedIndices[$idx] = true;
                }
            } elseif ($type === ItemTypes::BOW->value || $type === ItemTypes::THROWN->value || $type === ItemTypes::RANGED->value || $type === ItemTypes::RELIC->value) {
                if ($slots['ranged']['item'] === null) {
                    $slots['ranged']['item'] = $item; $assignedIndices[$idx] = true;
                }
            }
        }

        // Second Pass: Strict type-checked assignment for remaining items
        foreach ($enrichedItems as $idx => $item) {
            if (isset($assignedIndices[$idx])) {
                continue;
            }

            $type = $item['type'] !== null ? (int)$item['type'] : null;

            foreach ($slots as $slotKey => &$slotData) {
                if ($slotData['item'] !== null) {
                    continue;
                }

                if ($type !== null) {
                    if (in_array($type, $slotData['types'], true)) {
                        $slotData['item'] = $item;
                        $assignedIndices[$idx] = true;
                        break;
                    }
                } else {
                    $slotData['item'] = $item;
                    $assignedIndices[$idx] = true;
                    break;
                }
            }
        }

        // Generate icon URLs and propagate should_have_enchant to item data
        foreach ($enrichedItems as &$enrichedItem) {
            $enrichedItem['icon_url'] = $this->externalIconUrl($enrichedItem['icon'] ?? null);
            $enrichedItem['icon_fallback_url'] = $this->localIconUrl($enrichedItem['icon'] ?? null);
        }
        unset($enrichedItem);

        foreach ($slots as $slotKey => &$slotData) {
            if ($slotData['item'] !== null) {
                $slotData['item']['icon_url'] = $this->externalIconUrl($slotData['item']['icon'] ?? null);
                $slotData['item']['icon_fallback_url'] = $this->localIconUrl($slotData['item']['icon'] ?? null);
                $itemType = $slotData['item']['type'] !== null ? (int) $slotData['item']['type'] : null;
                $shouldHaveEnchant = $slotData['should_have_enchant'];
                if ($slotKey === 'offhand' && $itemType === ItemTypes::OFF_HAND->value) {
                    $shouldHaveEnchant = false;
                }
                $slotData['should_have_enchant'] = $shouldHaveEnchant;
                $slotData['item']['should_have_enchant'] = $shouldHaveEnchant;
            }
        }
        unset($slotData);

        $transmogItems = [];
        foreach ($enrichedItems as $enrichedItem) {
            $transmogItem = $enrichedItem['transmog_item'] ?? null;
            if (is_array($transmogItem) && (int) ($transmogItem['id'] ?? 0) > 0) {
                $transmogItems[(int) $transmogItem['id']] = $transmogItem;
            }
        }

        return [
            'slots' => $slots,
            'enrichedItems' => $enrichedItems,
            'tooltips' => $itemTooltips,
            'tooltipPayload' => $includeTooltips ? $this->compactTooltipPayload($itemTooltips) : [],
            'transmogItems' => array_values($transmogItems),
        ];
    }

    /**
     * Set data can list a different version of a piece than the one worn, such as
     * another arena season or tier 9 ilvl, so members are also matched by slot and
     * the tooltip can name the piece the character actually wears.
     *
     * @param array<int, array<string, mixed>> $enrichedItems
     * @param array<int, array<string, mixed>> $dbItems
     * @return array<int, array<int, array{item_id: int, name: string}>> worn pieces by set ID and slot
     */
    private function addSetMemberTypes(array &$enrichedItems, array $dbItems): array
    {
        $equippedSetSlots = [];
        $missingIds = [];
        foreach ($enrichedItems as $enrichedItem) {
            $setId = (int) ($enrichedItem['tooltip']['item_set_id'] ?? 0);
            if ($setId <= 0) {
                continue;
            }
            $equippedSetSlots[$setId][ItemTooltipService::setSlotKey((int) ($enrichedItem['type'] ?? 0))] ??= [
                'item_id' => (int) ($enrichedItem['id'] ?? 0),
                'name' => (string) ($enrichedItem['name'] ?? ''),
            ];
            foreach ($enrichedItem['item_set_details']['members'] ?? [] as $member) {
                $memberId = (int) ($member['item_id'] ?? 0);
                if ($memberId > 0 && !isset($dbItems[$memberId])) {
                    $missingIds[$memberId] = $memberId;
                }
            }
        }

        if ($equippedSetSlots === []) {
            return [];
        }
        if ($missingIds !== []) {
            $dbItems += $this->itemDatabaseService->getItemsBulk(array_values($missingIds));
        }

        foreach ($enrichedItems as &$enrichedItem) {
            foreach ($enrichedItem['item_set_details']['members'] ?? [] as $index => $member) {
                $enrichedItem['item_set_details']['members'][$index]['type']
                    = $dbItems[(int) ($member['item_id'] ?? 0)]['type'] ?? null;
            }
        }
        unset($enrichedItem);

        return $equippedSetSlots;
    }

    /** @param array<string, array<string, mixed>> $tooltips */
    public function compactTooltipPayload(array $tooltips): array
    {
        if ($tooltips === []) {
            return [];
        }

        $unique = [];
        $hashToIndex = [];
        $references = [];
        foreach ($tooltips as $key => $tooltip) {
            $hash = hash('sha256', json_encode($tooltip, JSON_THROW_ON_ERROR));
            if (!array_key_exists($hash, $hashToIndex)) {
                $hashToIndex[$hash] = count($unique);
                $unique[] = $tooltip;
            }
            $references[$key] = $hashToIndex[$hash];
        }

        $compact = ['entries' => $unique, 'references' => $references];
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $flatSize = strlen(json_encode($tooltips, $flags | JSON_THROW_ON_ERROR));
        $compactSize = strlen(json_encode($compact, $flags | JSON_THROW_ON_ERROR));

        return $compactSize < $flatSize ? $compact : $tooltips;
    }

    public function resolveGemFromEnchantId(int $gemEnchantId): array
    {
        if (isset(GemDatabase::GEMS[$gemEnchantId])) {
            $gemData = GemDatabase::GEMS[$gemEnchantId];
            $cleanIcon = strtolower(pathinfo($gemData['icon'], PATHINFO_FILENAME));
            return [
                'id' => $gemData['item_id'],
                'enchant_id' => $gemEnchantId,
                'name' => $gemData['name'],
                'quality' => $gemData['quality'] ?? 4,
                'effect' => EnchantDatabase::ENCHANTS[$gemEnchantId] ?? $gemData['name'],
                'color_mask' => $this->inferGemColorMask($gemData['name'], $gemData['icon']),
                'icon_url' => $this->externalIconUrl($cleanIcon),
                'icon_fallback_url' => $this->localIconUrl($cleanIcon),
            ];
        }

        $name = EnchantDatabase::ENCHANTS[$gemEnchantId] ?? "Gem #{$gemEnchantId}";
        $textLower = strtolower($name);

        $iconName = 'inv_jewelcrafting_gem_37';
        $color = 'default';

        if (preg_match('/(increased critical|spell reflect|reduced threat|restore mana|snare\/root|silence duration|stun duration|heal on your crits|shield block value|run speed)/i', $textLower)) {
            $color = 'meta';
            $iconName = 'inv_jewelcrafting_shadowspirit_02';
        } elseif (str_contains($textLower, 'all stats') || str_contains($textLower, 'all resist')) {
            $color = 'prismatic';
            $iconName = 'inv_misc_gem_pearl_12';
        } else {
            $hasRed = preg_match('/\b(strength|agility|ap|sp|attack power|spell power|armor pen|expertise|parry|dodge)\b/i', $textLower);
            $hasBlue = preg_match('/\b(stamina|mp5|spirit|spell pen)\b/i', $textLower);
            $hasYellow = preg_match('/\b(crit|hit|haste|intellect|defense|resilience)\b/i', $textLower);

            if ($hasRed && $hasBlue && !$hasYellow) {
                $color = 'purple';
                $iconName = 'inv_jewelcrafting_gem_40';
            } elseif ($hasRed && $hasYellow && !$hasBlue) {
                $color = 'orange';
                $iconName = 'inv_jewelcrafting_gem_39';
            } elseif ($hasYellow && $hasBlue && !$hasRed) {
                $color = 'green';
                $iconName = 'inv_jewelcrafting_gem_41';
            } elseif ($hasRed) {
                $color = 'red';
                $iconName = 'inv_jewelcrafting_gem_37';
            } elseif ($hasBlue) {
                $color = 'blue';
                $iconName = 'inv_jewelcrafting_gem_42';
            } elseif ($hasYellow) {
                $color = 'yellow';
                $iconName = 'inv_jewelcrafting_gem_38';
            }
        }

        return [
            'id' => 0,
            'enchant_id' => $gemEnchantId,
            'name' => $name,
            'color' => $color,
            'quality' => 4,
            'effect' => $name,
            'color_mask' => $this->inferGemColorMask($name, $iconName),
            'icon_url' => $this->externalIconUrl($iconName),
            'icon_fallback_url' => $this->localIconUrl($iconName),
        ];
    }

    private function externalIconUrl(?string $iconName): ?string
    {
        $cleanIcon = $this->cleanIconName($iconName);

        return $cleanIcon === null
            ? null
            : 'https://wow.zamimg.com/images/wow/icons/large/'.rawurlencode($cleanIcon).'.jpg';
    }

    private function localIconUrl(?string $iconName): ?string
    {
        $cleanIcon = $this->cleanIconName($iconName);

        return $cleanIcon === null
            ? null
            : '/wow-icons/large/'.rawurlencode($cleanIcon).'.jpg';
    }

    private function cleanIconName(?string $iconName): ?string
    {
        if ($iconName === null || trim($iconName) === '') {
            return null;
        }

        $cleanIcon = strtolower(pathinfo($iconName, PATHINFO_FILENAME));
        if (!preg_match('/\A[a-z0-9][a-z0-9_-]{0,127}\z/', $cleanIcon)) {
            return null;
        }

        return $cleanIcon;
    }

    private function inferGemColorMask(string $name, string $icon): int
    {
        $value = strtolower($name . ' ' . $icon);

        if (str_contains($value, 'diamond')) {
            return 1;
        }
        if (str_contains($value, 'nightmare tear') || str_contains($value, 'prismatic')) {
            return 2 | 4 | 8;
        }
        if (str_contains($value, 'dreadstone') || str_contains($value, 'twilight opal')) {
            return 2 | 8;
        }
        if (str_contains($value, 'ametrine') || str_contains($value, 'monarch topaz')) {
            return 2 | 4;
        }
        if (str_contains($value, 'eye of zul') || str_contains($value, 'forest emerald')) {
            return 4 | 8;
        }
        if (str_contains($value, 'gem_40') || str_contains($value, 'shadow crystal')) {
            return 2 | 8;
        }
        if (str_contains($value, 'gem_39') || str_contains($value, 'huge citrine')) {
            return 2 | 4;
        }
        if (str_contains($value, 'gem_41') || str_contains($value, 'dark jade')) {
            return 4 | 8;
        }
        if (str_contains($value, 'dragonseye03') || str_contains($value, "king's amber") || str_contains($value, 'autumn')) {
            return 4;
        }
        if (str_contains($value, 'gem_38') || str_contains($value, 'sun crystal')) {
            return 4;
        }
        if (str_contains($value, 'dragonseye04') || str_contains($value, 'zircon') || str_contains($value, 'sapphire')) {
            return 8;
        }
        if (str_contains($value, 'gem_42') || str_contains($value, 'chalcedony')) {
            return 8;
        }
        if (str_contains($value, 'dragonseye05') || str_contains($value, 'ruby')) {
            return 2;
        }
        if (str_contains($value, 'gem_37') || str_contains($value, 'gem_28') || str_contains($value, 'gem_22') || str_contains($value, 'bloodstone')) {
            return 2;
        }

        return 0;
    }
}
