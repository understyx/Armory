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
    public function buildPaperdollSlots(array $equippedItems, ?string $characterClass = null): array
    {
        $canDualWieldTwoHandedWeapons = strcasecmp($characterClass ?? '', 'Warrior') === 0;
        $itemIds = array_values(array_filter(array_column($equippedItems, 'id')));

        // Fetch equipment item details from DB (gem IDs are spell/enchantment IDs, not item IDs)
        $dbItems = !empty($itemIds) ? $this->itemDatabaseService->getItemsBulk($itemIds) : [];

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
            $gemDetails = [];
            foreach ($rawGems as $gId) {
                $gemEnchantId = (int)$gId;
                if ($gemEnchantId > 0) {
                    $gemDetails[] = $this->resolveGemFromEnchantId($gemEnchantId);
                }
            }

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
                'transmog' => $item['transmog'] ?? null,
                'tooltip' => $item['tooltip'] ?? ($dbData['tooltip'] ?? null),
                'orig_index' => $index,
            ];
        }

        foreach ($enrichedItems as &$enrichedItem) {
            $iconName = $enrichedItem['icon'] ?? null;
            if (!empty($iconName)) {
                $cleanIcon = strtolower(pathinfo($iconName, PATHINFO_FILENAME));
                $enrichedItem['icon_url'] = "https://wow.zamimg.com/images/wow/icons/large/{$cleanIcon}.jpg";
            } else {
                $enrichedItem['icon_url'] = null;
            }
        }
        unset($enrichedItem);

        $setCounts = [];
        foreach ($enrichedItems as $enrichedItem) {
            $setId = (int) ($enrichedItem['tooltip']['item_set_id'] ?? 0);
            if ($setId > 0) {
                $setCounts[$setId] = ($setCounts[$setId] ?? 0) + 1;
            }
        }

        $itemTooltips = [];
        if ($this->itemTooltipService !== null) {
            foreach ($enrichedItems as &$enrichedItem) {
                $tooltip = $this->itemTooltipService->build($enrichedItem, $setCounts);
                if ($tooltip !== null && $enrichedItem['tooltip_key'] !== null) {
                    $enrichedItem['display_tooltip'] = $tooltip;
                    $itemTooltips[$enrichedItem['tooltip_key']] = $tooltip;
                }
            }
            unset($enrichedItem);
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
            $iconName = $enrichedItem['icon'] ?? null;
            if (!empty($iconName)) {
                $cleanIcon = strtolower(pathinfo($iconName, PATHINFO_FILENAME));
                $enrichedItem['icon_url'] = "https://wow.zamimg.com/images/wow/icons/large/{$cleanIcon}.jpg";
            } else {
                $enrichedItem['icon_url'] = null;
            }
        }
        unset($enrichedItem);

        foreach ($slots as $slotKey => &$slotData) {
            if ($slotData['item'] !== null) {
                $iconName = $slotData['item']['icon'] ?? null;
                if (!empty($iconName)) {
                    $cleanIcon = strtolower(pathinfo($iconName, PATHINFO_FILENAME));
                    $slotData['item']['icon_url'] = "https://wow.zamimg.com/images/wow/icons/large/{$cleanIcon}.jpg";
                } else {
                    $slotData['item']['icon_url'] = null;
                }
                $slotData['item']['should_have_enchant'] = $slotData['should_have_enchant'];
            }
        }
        unset($slotData);

        return [
            'slots' => $slots,
            'enrichedItems' => $enrichedItems,
            'tooltips' => $itemTooltips,
        ];
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
                'icon_url' => "https://wow.zamimg.com/images/wow/icons/large/{$cleanIcon}.jpg",
            ];
        }

        $name = EnchantDatabase::ENCHANTS[$gemEnchantId] ?? "Gem #{$gemEnchantId}";
        $textLower = strtolower($name);

        $iconUrl = 'https://wow.zamimg.com/images/wow/icons/large/inv_jewelcrafting_gem_37.jpg';
        $color = 'default';

        if (preg_match('/(increased critical|spell reflect|reduced threat|restore mana|snare\/root|silence duration|stun duration|heal on your crits|shield block value|run speed)/i', $textLower)) {
            $color = 'meta';
            $iconUrl = 'https://wow.zamimg.com/images/wow/icons/large/inv_jewelcrafting_shadowspirit_02.jpg';
        } elseif (str_contains($textLower, 'all stats') || str_contains($textLower, 'all resist')) {
            $color = 'prismatic';
            $iconUrl = 'https://wow.zamimg.com/images/wow/icons/large/inv_jewelcrafting_nightmaretear_01.jpg';
        } else {
            $hasRed = preg_match('/\b(strength|agility|ap|sp|attack power|spell power|armor pen|expertise|parry|dodge)\b/i', $textLower);
            $hasBlue = preg_match('/\b(stamina|mp5|spirit|spell pen)\b/i', $textLower);
            $hasYellow = preg_match('/\b(crit|hit|haste|intellect|defense|resilience)\b/i', $textLower);

            if ($hasRed && $hasBlue && !$hasYellow) {
                $color = 'purple';
                $iconUrl = 'https://wow.zamimg.com/images/wow/icons/large/inv_jewelcrafting_dreadstone_02.jpg';
            } elseif ($hasRed && $hasYellow && !$hasBlue) {
                $color = 'orange';
                $iconUrl = 'https://wow.zamimg.com/images/wow/icons/large/inv_jewelcrafting_ametrine_02.jpg';
            } elseif ($hasYellow && $hasBlue && !$hasRed) {
                $color = 'green';
                $iconUrl = 'https://wow.zamimg.com/images/wow/icons/large/inv_jewelcrafting_eyeofzul_02.jpg';
            } elseif ($hasRed) {
                $color = 'red';
                $iconUrl = 'https://wow.zamimg.com/images/wow/icons/large/inv_jewelcrafting_crimsonruby_02.jpg';
            } elseif ($hasBlue) {
                $color = 'blue';
                $iconUrl = 'https://wow.zamimg.com/images/wow/icons/large/inv_jewelcrafting_skytanzanite_02.jpg';
            } elseif ($hasYellow) {
                $color = 'yellow';
                $iconUrl = 'https://wow.zamimg.com/images/wow/icons/large/inv_jewelcrafting_kingssamber_02.jpg';
            }
        }

        return [
            'id' => 0,
            'enchant_id' => $gemEnchantId,
            'name' => $name,
            'color' => $color,
            'quality' => 4,
            'effect' => $name,
            'color_mask' => $this->inferGemColorMask($name, $iconUrl),
            'icon_url' => $iconUrl,
        ];
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
        if (str_contains($value, 'dragonseye03') || str_contains($value, "king's amber") || str_contains($value, 'autumn')) {
            return 4;
        }
        if (str_contains($value, 'dragonseye04') || str_contains($value, 'zircon') || str_contains($value, 'sapphire')) {
            return 8;
        }
        if (str_contains($value, 'dragonseye05') || str_contains($value, 'ruby')) {
            return 2;
        }

        return 0;
    }
}
