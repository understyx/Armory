<?php

namespace App\Tests\Service;

use App\Enum\ItemTypes;
use App\Service\CharacterClaimSlotPicker;
use App\Service\ItemDatabaseService;
use PHPUnit\Framework\TestCase;

final class CharacterClaimSlotPickerTest extends TestCase
{
    public function testPicksTwoRandomNonShirtSlotsWhenAvailable(): void
    {
        $picker = $this->picker([
            100 => ['type' => ItemTypes::HEAD->value, 'icon' => 'helm'],
            200 => ['type' => ItemTypes::NECK->value, 'icon' => 'neck'],
            300 => ['type' => ItemTypes::SHIRT->value, 'icon' => 'shirt'],
        ]);
        $slots = $picker->pick([
            ['id' => 100, 'name' => 'Helm'],
            ['id' => 200, 'name' => 'Necklace'],
            ['id' => 300, 'name' => 'Shirt'],
        ], static fn (int $max): int => 0);

        self::assertCount(2, $slots);
        self::assertSame([
            ['slot' => 'head', 'id' => 100, 'name' => 'Head: Helm', 'icon' => 'helm'],
            ['slot' => 'neck', 'id' => 200, 'name' => 'Neck: Necklace', 'icon' => 'neck'],
        ], $slots);
    }

    public function testUsesShirtOrTabardOnlyToFillMissingSlots(): void
    {
        $slots = $this->picker([
            100 => ['type' => ItemTypes::HEAD->value],
            300 => ['type' => ItemTypes::SHIRT->value],
            400 => ['type' => ItemTypes::TABARD->value],
        ])->pick([
            ['id' => 100, 'name' => 'Helm'],
            ['id' => 300, 'name' => 'Shirt'],
            ['id' => 400, 'name' => 'Tabard'],
        ], static fn (int $max): int => 0);

        self::assertCount(2, $slots);
        self::assertSame(['head', 'shirt'], array_column($slots, 'slot'));
    }

    public function testUsesTheNumberOfItemsAvailableAndHandlesNoItems(): void
    {
        $picker = $this->picker([100 => ['type' => ItemTypes::HEAD->value]]);

        self::assertSame([], $picker->pick([]));
        self::assertCount(1, $picker->pick([['id' => 100, 'name' => 'Helm']]));
    }

    /** @param array<int, array<string, mixed>> $items */
    private function picker(array $items): CharacterClaimSlotPicker
    {
        $itemDatabase = $this->createMock(ItemDatabaseService::class);
        $itemDatabase->method('getItemsBulk')->willReturnCallback(
            static fn (array $ids): array => array_intersect_key($items, array_flip($ids)),
        );

        return new CharacterClaimSlotPicker($itemDatabase);
    }
}
