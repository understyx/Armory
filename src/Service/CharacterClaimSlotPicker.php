<?php

namespace App\Service;

use App\Enum\ItemTypes;

final class CharacterClaimSlotPicker
{
    private const SLOT_NAMES = [
        'head' => 'Head',
        'neck' => 'Neck',
        'shoulder' => 'Shoulders',
        'back' => 'Back',
        'chest' => 'Chest',
        'shirt' => 'Shirt',
        'tabard' => 'Tabard',
        'wrist' => 'Wrist',
        'hands' => 'Hands',
        'waist' => 'Waist',
        'legs' => 'Legs',
        'feet' => 'Feet',
        'ring' => 'Ring',
        'trinket' => 'Trinket',
        'mainhand' => 'Main Hand',
        'offhand' => 'Off Hand',
        'ranged' => 'Ranged / Relic',
        'weapon' => 'Weapon',
        'unknown' => 'Equipped item',
    ];

    public function __construct(private readonly ItemDatabaseService $itemDatabaseService)
    {
    }

    /**
     * @param list<array<string, mixed>> $equippedItems
     * @param callable(int): int|null $chooseIndex Optional bounded index picker for deterministic tests.
     * @return list<array{slot: string, id: int, name: string, icon: ?string}>
     */
    public function pick(array $equippedItems, ?callable $chooseIndex = null): array
    {
        $candidates = $this->map($equippedItems);

        if ($candidates === []) {
            return [];
        }

        $preferred = array_values(array_filter(
            $candidates,
            static fn (array $item): bool => !in_array($item['slot'], ['shirt', 'tabard'], true),
        ));
        $picked = [];
        $this->draw($preferred, min(2, count($preferred)), $picked, $chooseIndex);

        if (count($picked) < 2) {
            $fallback = array_values(array_filter(
                $candidates,
                static fn (array $item): bool => in_array($item['slot'], ['shirt', 'tabard'], true),
            ));
            $this->draw($fallback, 2, $picked, $chooseIndex);
        }

        return $picked;
    }

    /** @param list<array{slot: string, id: int, name: string, icon: ?string}> $pool
     * @param list<array{slot: string, id: int, name: string, icon: ?string}> $picked
     */
    private function draw(array $pool, int $targetTotal, array &$picked, ?callable $chooseIndex): void
    {
        while ($pool !== [] && count($picked) < $targetTotal) {
            $last = count($pool) - 1;
            $index = $chooseIndex !== null ? $chooseIndex($last) : random_int(0, $last);
            $index = max(0, min($last, $index));
            $picked[] = $pool[$index];
            array_splice($pool, $index, 1);
        }
    }

    /**
     * @param list<array<string, mixed>> $equippedItems
     * @return list<array{slot: string, id: int, name: string, icon: ?string}>
     */
    public function map(array $equippedItems): array
    {
        $itemIds = array_values(array_unique(array_filter(
            array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $equippedItems),
            static fn (int $id): bool => $id > 0,
        )));
        $itemDetails = $itemIds !== [] ? $this->itemDatabaseService->getItemsBulk($itemIds) : [];
        $mapped = [];
        foreach ($equippedItems as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id < 1) {
                continue;
            }

            $details = $itemDetails[$id] ?? [];
            $slot = $this->slotFor((int) ($details['type'] ?? 0));
            $slotName = self::SLOT_NAMES[$slot] ?? ucfirst($slot);
            $name = (string) (($item['name'] ?? '') ?: ($details['name'] ?? 'Item #'.$id));
            $mapped[] = [
                'slot' => $slot,
                'id' => $id,
                'name' => $slotName.': '.$name,
                'icon' => isset($details['icon']) ? (string) $details['icon'] : null,
            ];
        }

        return $mapped;
    }

    /** @param array<string, mixed> $item */
    private function slotFor(int $type): string
    {
        return match ($type) {
            ItemTypes::HEAD->value => 'head',
            ItemTypes::NECK->value => 'neck',
            ItemTypes::SHOULDER->value => 'shoulder',
            ItemTypes::BACK->value => 'back',
            ItemTypes::CHEST->value, ItemTypes::ROBE->value => 'chest',
            ItemTypes::SHIRT->value => 'shirt',
            ItemTypes::TABARD->value => 'tabard',
            ItemTypes::WRIST->value => 'wrist',
            ItemTypes::GLOVES->value => 'hands',
            ItemTypes::WAIST->value => 'waist',
            ItemTypes::LEGS->value => 'legs',
            ItemTypes::FEET->value => 'feet',
            ItemTypes::RING->value => 'ring',
            ItemTypes::TRINKET->value => 'trinket',
            ItemTypes::WEAPON_1H->value => 'weapon',
            ItemTypes::WEAPON_MAINHAND->value => 'mainhand',
            ItemTypes::WEAPON_2H->value => 'weapon',
            ItemTypes::WEAPON_OFFHAND->value, ItemTypes::SHIELD->value, ItemTypes::OFF_HAND->value => 'offhand',
            ItemTypes::BOW->value, ItemTypes::THROWN->value, ItemTypes::RANGED->value, ItemTypes::RELIC->value => 'ranged',
            default => 'unknown',
        };
    }
}
