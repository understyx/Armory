<?php

namespace App\Service;

use App\Enum\ItemTypes;

class ItemTooltipService
{
    private const HEROIC_TOOLTIP_FLAG = 0x08;
    private const PRISMATIC_SOCKET_MASK = 2 | 4 | 8;

    /** @var array<int, string> */
    private const PRIMARY_STATS = [
        3 => 'Agility', 4 => 'Strength', 5 => 'Intellect', 6 => 'Spirit', 7 => 'Stamina',
    ];

    /** @var array<int, string> */
    private const RATING_STATS = [
        12 => 'defense', 13 => 'dodge', 14 => 'parry', 15 => 'shield block', 31 => 'hit',
        32 => 'critical strike', 35 => 'resilience', 36 => 'haste', 37 => 'expertise', 44 => 'armor penetration',
    ];

    /**
     * @param array<string, mixed> $item
     * @param array<int, int> $setCounts
     * @param int[] $equippedItemIds
     * @param string[] $equippedItemNames
     * @return array<string, mixed>|null
     */
    public function build(
        array $item,
        array $setCounts = [],
        array $equippedItemIds = [],
        array $equippedItemNames = [],
    ): ?array
    {
        $raw = $item['tooltip'] ?? null;
        if (!is_array($raw)) {
            return null;
        }

        $primaryStats = [];
        $equipEffects = [];
        foreach ($raw['stats'] ?? [] as $stat) {
            $type = (int) ($stat['type'] ?? 0);
            $value = (int) ($stat['value'] ?? 0);
            if ($value === 0) {
                continue;
            }

            if (isset(self::PRIMARY_STATS[$type])) {
                $primaryStats[] = sprintf('%+d %s', $value, self::PRIMARY_STATS[$type]);
            } elseif (isset(self::RATING_STATS[$type])) {
                $equipEffects[] = sprintf('Increases your %s rating by %d.', self::RATING_STATS[$type], $value);
            } elseif ($type === 38) {
                $equipEffects[] = sprintf('Increases attack power by %d.', $value);
            } elseif ($type === 39) {
                $equipEffects[] = sprintf('Increases ranged attack power by %d.', $value);
            } elseif ($type === 43) {
                $equipEffects[] = sprintf('Restores %d mana per 5 sec.', $value);
            } elseif ($type === 45) {
                $equipEffects[] = sprintf('Increases spell power by %d.', $value);
            } elseif ($type === 47) {
                $equipEffects[] = sprintf('Increases spell penetration by %d.', $value);
            } elseif ($type === 48) {
                $equipEffects[] = sprintf('Increases the block value of your shield by %d.', $value);
            }
        }

        $effects = array_map(
            static fn (string $effect): array => ['type' => 'equip', 'text' => $effect, 'source' => 'trinity'],
            $equipEffects
        );
        foreach ($item['external_effects'] ?? [] as $effect) {
            $text = trim((string) ($effect['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $effects[] = [
                'type' => in_array($effect['type'] ?? null, ['equip', 'use', 'chance_on_hit'], true)
                    ? $effect['type']
                    : 'equip',
                'text' => $text,
                'spell_id' => isset($effect['spell_id']) ? (int) $effect['spell_id'] : null,
                'source' => $effect['source'] ?? null,
            ];
        }

        $sockets = $this->buildSockets($raw['sockets'] ?? [], $item['gem_details'] ?? []);
        $socketBonusId = (int) ($raw['socket_bonus_id'] ?? 0);
        $socketBonus = null;
        if ($socketBonusId > 0) {
            $socketBonus = [
                'text' => $this->expandLegacyStatNames(
                    EnchantDatabase::ENCHANTS[$socketBonusId] ?? sprintf('Enchantment #%d', $socketBonusId)
                ),
                'active' => $sockets !== [] && array_reduce(
                    $sockets,
                    static fn (bool $active, array $socket): bool => $active
                        && (!(bool) ($socket['counts_for_bonus'] ?? true) || ($socket['matches'] ?? false)),
                    true
                ),
            ];
        }

        $damage = null;
        foreach ($raw['damage'] ?? [] as $damageRange) {
            $min = (float) ($damageRange['min'] ?? 0);
            $max = (float) ($damageRange['max'] ?? 0);
            if ($max <= 0) {
                continue;
            }

            $delay = (int) ($raw['delay'] ?? 0);
            $damage = [
                'min' => $this->formatNumber($min),
                'max' => $this->formatNumber($max),
                'speed' => $delay > 0 ? number_format($delay / 1000, 2) : null,
                'dps' => $delay > 0 ? number_format((($min + $max) / 2) / ($delay / 1000), 1) : null,
            ];
            break;
        }

        $setId = (int) ($raw['item_set_id'] ?? 0);
        $itemSet = $setId > 0 ? [
            'id' => $setId,
            'equipped_count' => $setCounts[$setId] ?? 0,
        ] : null;
        $setDetails = $item['item_set_details'] ?? null;
        if ($itemSet !== null && is_array($setDetails)) {
            $itemSet['name'] = (string) ($setDetails['name'] ?? sprintf('Item Set #%d', $setId));
            $equippedNameKeys = array_fill_keys(array_map(
                self::normalizeSetMemberName(...),
                $equippedItemNames
            ), true);
            $currentItemId = (int) ($item['id'] ?? 0);
            $currentItemName = self::normalizeSetMemberName((string) ($item['name'] ?? ''));
            $itemSet['members'] = array_map(
                static function (array $member) use (
                    $equippedItemIds,
                    $equippedNameKeys,
                    $currentItemId,
                    $currentItemName
                ): array {
                    $memberId = (int) ($member['item_id'] ?? 0);
                    $memberName = (string) ($member['name'] ?? 'Unknown Item');
                    $memberNameKey = self::normalizeSetMemberName($memberName);

                    return [
                        'item_id' => $memberId,
                        'name' => $memberName,
                        'equipped' => in_array($memberId, $equippedItemIds, true)
                            || ($currentItemId > 0 && $memberId === $currentItemId)
                            || ($memberNameKey !== '' && isset($equippedNameKeys[$memberNameKey]))
                            || ($currentItemName !== '' && $memberNameKey === $currentItemName),
                    ];
                },
                $setDetails['members'] ?? []
            );
            $equippedCount = (int) $itemSet['equipped_count'];
            $itemSet['bonuses'] = array_map(
                static fn (array $bonus): array => [
                    'required_count' => (int) ($bonus['required_count'] ?? 0),
                    'description' => (string) ($bonus['description'] ?? ''),
                    'active' => $equippedCount >= (int) ($bonus['required_count'] ?? 0),
                ],
                $setDetails['bonuses'] ?? []
            );
        }

        return [
            'id' => (int) ($item['id'] ?? 0),
            'name' => (string) ($item['name'] ?? 'Unknown Item'),
            'quality' => (int) ($item['quality'] ?? 1),
            'icon_url' => $item['icon_url'] ?? null,
            'icon_fallback_url' => $item['icon_fallback_url'] ?? null,
            'heroic' => (((int) ($raw['flags'] ?? 0)) & self::HEROIC_TOOLTIP_FLAG) !== 0,
            'binding' => $this->bindingName((int) ($raw['bonding'] ?? 0)),
            'unique' => (int) ($raw['max_count'] ?? 0) === 1,
            'slot' => $this->inventoryTypeName((int) ($item['type'] ?? 0)),
            'subclass' => $this->subclassName((int) ($item['class'] ?? 0), (int) ($item['subclass'] ?? 0)),
            'armor' => (int) ($raw['armor'] ?? 0),
            'block' => (int) ($raw['block'] ?? 0),
            'primary_stats' => $primaryStats,
            'equip_effects' => $equipEffects,
            'effects' => $effects,
            'enchant' => $this->expandLegacyStatNames($item['enchant_name'] ?? null),
            'sockets' => $sockets,
            'socket_bonus' => $socketBonus,
            'required_level' => (int) ($item['requires'] ?? 0),
            'item_level' => (int) ($item['ilvl'] ?? 0),
            'damage' => $damage,
            'description' => (string) ($raw['description'] ?? ''),
            'sell_price' => $this->moneyParts((int) ($raw['sell_price'] ?? 0)),
            'item_set' => $itemSet,
            'transmog_item' => $item['transmog_item'] ?? null,
        ];
    }

    private static function normalizeSetMemberName(string $name): string
    {
        $name = strtolower(trim(str_replace(['‘', '’'], "'", $name)));

        // Tier 10 upgrades keep the same set membership while adding this prefix.
        // Set data can list the base item names even when the equipped pieces are upgraded.
        return (string) preg_replace('/^sanctified\s+/i', '', $name);
    }

    /**
     * @param array<int, array<string, mixed>> $rawSockets
     * @param array<int, array<string, mixed>> $gems
     * @return array<int, array<string, mixed>>
     */
    private function buildSockets(array $rawSockets, array $gems): array
    {
        $sockets = [];
        foreach ($rawSockets as $index => $rawSocket) {
            $socketColor = (int) ($rawSocket['color'] ?? 0);
            $gem = $gems[$index] ?? null;
            if (is_array($gem)) {
                $gem['effect'] = $this->expandLegacyStatNames($gem['effect'] ?? null);
            }
            $gemMask = is_array($gem) ? (int) ($gem['color_mask'] ?? 0) : 0;
            $sockets[] = [
                'color' => $this->socketColorName($socketColor),
                'color_mask' => $socketColor,
                'gem' => $gem,
                'matches' => is_array($gem) && ($gemMask & $socketColor) !== 0,
                'counts_for_bonus' => true,
            ];
        }

        // The armory includes gems added by an Eternal Belt Buckle and
        // Blacksmithing sockets, while item_template only contains native slots.
        // Preserve every equipped gem and treat any excess as a prismatic slot.
        foreach (array_slice($gems, count($rawSockets)) as $gem) {
            if (!is_array($gem)) {
                continue;
            }

            $gem['effect'] = $this->expandLegacyStatNames($gem['effect'] ?? null);
            $gemMask = (int) ($gem['color_mask'] ?? 0);
            $sockets[] = [
                'color' => 'Prismatic',
                'color_mask' => self::PRISMATIC_SOCKET_MASK,
                'gem' => $gem,
                'matches' => $gemMask !== 1,
                'counts_for_bonus' => false,
                'inferred' => true,
            ];
        }

        return $sockets;
    }

    private function expandLegacyStatNames(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $expanded = preg_replace_callback(
            '/(\+?\d+(?:\.\d+)?\s+)(Armor Pen(?:etration)?|Crit|Haste|Hit|Expertise|SP)(?:\s+Rating)?\b/i',
            static function (array $matches): string {
                $stat = match (strtolower($matches[2])) {
                    'sp' => 'Spell Power',
                    'crit' => 'Critical Strike Rating',
                    'haste' => 'Haste Rating',
                    'hit' => 'Hit Rating',
                    'expertise' => 'Expertise Rating',
                    default => 'Armor Penetration Rating',
                };

                return $matches[1] . $stat;
            },
            $text
        );

        return $expanded ?? $text;
    }

    private function bindingName(int $bonding): ?string
    {
        return match ($bonding) {
            1 => 'Binds when picked up', 2 => 'Binds when equipped', 3 => 'Binds when used',
            4, 5 => 'Quest Item', default => null,
        };
    }

    private function inventoryTypeName(int $type): ?string
    {
        return ItemTypes::tryFrom($type)?->getName() ?? match ($type) {
            18 => 'Bag', 24 => 'Ammo', 27 => 'Quiver', default => null,
        };
    }

    private function subclassName(int $class, int $subclass): ?string
    {
        if ($class === 4) {
            return [0 => 'Miscellaneous', 1 => 'Cloth', 2 => 'Leather', 3 => 'Mail', 4 => 'Plate', 6 => 'Shield'][$subclass] ?? null;
        }
        if ($class === 2) {
            return [
                0 => 'Axe', 1 => 'Axe', 2 => 'Bow', 3 => 'Gun', 4 => 'Mace', 5 => 'Mace', 6 => 'Polearm',
                7 => 'Sword', 8 => 'Sword', 10 => 'Staff', 13 => 'Fist Weapon', 15 => 'Dagger',
                16 => 'Thrown', 18 => 'Crossbow', 19 => 'Wand', 20 => 'Fishing Pole',
            ][$subclass] ?? null;
        }

        return null;
    }

    private function socketColorName(int $mask): string
    {
        return match ($mask) { 1 => 'Meta', 2 => 'Red', 4 => 'Yellow', 8 => 'Blue', default => 'Prismatic' };
    }

    /** @return array{gold: int, silver: int, copper: int}|null */
    private function moneyParts(int $copper): ?array
    {
        return $copper <= 0 ? null : [
            'gold' => intdiv($copper, 10000),
            'silver' => intdiv($copper % 10000, 100),
            'copper' => $copper % 100,
        ];
    }

    private function formatNumber(float $number): int|float
    {
        return fmod($number, 1.0) === 0.0 ? (int) $number : round($number, 1);
    }
}
