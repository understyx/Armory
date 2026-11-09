<?php

namespace App\Tests\Service;

use App\Entity\CharacterSnapshot;
use App\Service\WowSimLinkBuilder;
use PHPUnit\Framework\TestCase;

final class WowSimLinkBuilderTest extends TestCase
{
    public function testShadowPriestPayloadRoundTripsWithEquipmentGlyphsAndOverrides(): void
    {
        $snapshot = (new CharacterSnapshot())
            ->setName('Examplepriest')->setRealm('Icecrown')->setLevel(80)->setRace('Undead')->setClass('Priest')
            ->setProfessions(['Tailoring (450)'])
            ->setTalentStrings(['0' => '05032031'.str_repeat('0', 20).str_repeat('0', 27).'325023051223010323151301351'])
            ->setGlyphs(['0' => ['Major Glyphs' => ['Glyph of Shadow', 'Glyph of Mind Flay'], 'Minor Glyphs' => ['Glyph of Shadowfiend']]]);
        $slots = $this->slots();
        $slots['head']['item'] = ['id' => 51471, 'enchant' => 3820, 'gem_details' => [['id' => 41285], ['id' => 41398]]];
        $slots['finger1']['item'] = ['id' => 50398, 'enchant' => 3791, 'gem_details' => [['id' => 40113]]];
        $slots['finger2']['item'] = ['id' => 50397, 'enchant' => 3791, 'gem_details' => []];
        $slots['mainhand']['item'] = ['id' => 50734, 'enchant' => 3854, 'gem_details' => []];
        $builder = new WowSimLinkBuilder();
        $payload = $builder->buildPayload($snapshot, '0', 'Shadow', $slots);

        self::assertNotNull($payload);
        self::assertSame('shadow_priest', $payload['path']);
        $binary = gzuncompress(base64_decode(substr($builder->buildLinks($snapshot, [['specId' => '0', 'specName' => 'Shadow']], $slots)['0'], strpos($builder->buildLinks($snapshot, [['specId' => '0', 'specName' => 'Shadow']], $slots)['0'], '#') + 1), true));
        self::assertIsString($binary);
        $decoded = $this->decodeMessage($binary);
        $player = $this->asMap($this->nestedField($decoded, 3));

        self::assertSame(5, $this->varintField($player, 2));
        self::assertSame(10, $this->varintField($player, 1));
        self::assertSame('05032031--325023051223010323151301351', $this->stringField($player, 17));
        self::assertSame(11, $this->varintField($player, 29));
        $glyphs = $this->asMap($this->nestedField($player, 28));
        self::assertSame(42407, $this->varintField($glyphs, 1));
        self::assertSame(42415, $this->varintField($glyphs, 2));
        self::assertSame(43374, $this->varintField($glyphs, 4));

        $equipment = $this->asMap($this->nestedField($player, 3));
        $items = [];
        foreach ($equipment as $field) {
            if ($field['number'] === 1) {
                $items[] = $this->asMap((string) $field['value']);
            }
        }
        self::assertCount(17, $items);
        self::assertSame(51471, $this->varintField($items[0], 2));
        self::assertSame(3820, $this->varintField($items[0], 3));
        self::assertSame([41285, 41398], $this->packedVarintsField($items[0], 4));
        self::assertSame(50398, $this->varintField($items[10], 2));
        self::assertSame(0, $this->varintField($items[10], 3, 0));
        self::assertSame(50734, $this->varintField($items[14], 2));

        $shadowPriest = $this->asMap($this->nestedField($player, 10));
        $options = $this->asMap($this->nestedField($shadowPriest, 3));
        self::assertSame(1, $this->varintField($options, 2));
        self::assertStringStartsWith('https://poli93.github.io/wotlk/shadow_priest/?i=gtmc#', $builder->buildLinks($snapshot, [['specId' => '0', 'specName' => 'Shadow']], $slots)['0']);
    }

    public function testUnsupportedRestorationDruidHasNoSimLink(): void
    {
        $snapshot = (new CharacterSnapshot())->setName('Druid')->setRace('Night Elf')->setClass('Druid');

        self::assertNull((new WowSimLinkBuilder())->buildPayload($snapshot, '0', 'Restoration', $this->slots()));
    }

    public function testUnsupportedSmitePriestHasNoSimLink(): void
    {
        $snapshot = (new CharacterSnapshot())->setName('Priest')->setRace('Human')->setClass('Priest');

        self::assertNull((new WowSimLinkBuilder())->buildPayload($snapshot, '0', 'Smite', $this->slots()));
    }

    /** @return array<string, array{item: ?array}> */
    private function slots(): array
    {
        $slots = [];
        foreach (['head', 'neck', 'shoulder', 'back', 'chest', 'wrist', 'hands', 'waist', 'legs', 'feet', 'finger1', 'finger2', 'trinket1', 'trinket2', 'mainhand', 'offhand', 'ranged'] as $slot) {
            $slots[$slot] = ['item' => null];
        }

        return $slots;
    }

    /** @return list<array{number: int, wire: int, value: int|string}> */
    private function decodeMessage(string $bytes): array
    {
        $fields = [];
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $tag = $this->readVarint($bytes, $offset);
            $number = $tag >> 3;
            $wire = $tag & 7;
            $value = match ($wire) {
                0 => $this->readVarint($bytes, $offset),
                1 => $this->readBytes($bytes, $offset, 8),
                2 => $this->readLengthDelimited($bytes, $offset),
                5 => $this->readBytes($bytes, $offset, 4),
                default => throw new \RuntimeException('Unsupported protobuf wire type.'),
            };
            $fields[] = ['number' => $number, 'wire' => $wire, 'value' => $value];
        }

        return $fields;
    }

    private function readVarint(string $bytes, int &$offset): int
    {
        $value = 0;
        $shift = 0;
        do {
            $byte = ord($bytes[$offset++]);
            $value |= ($byte & 0x7f) << $shift;
            $shift += 7;
        } while (($byte & 0x80) !== 0);

        return $value;
    }

    private function readLengthDelimited(string $bytes, int &$offset): string
    {
        $length = $this->readVarint($bytes, $offset);

        return $this->readBytes($bytes, $offset, $length);
    }

    private function readBytes(string $bytes, int &$offset, int $length): string
    {
        $value = substr($bytes, $offset, $length);
        $offset += $length;

        return $value;
    }

    private function nestedField(array $fields, int $number): string
    {
        foreach ($fields as $field) {
            if ($field['number'] === $number && $field['wire'] === 2) {
                return (string) $field['value'];
            }
        }
        self::fail(sprintf('Nested field %d is missing.', $number));
    }

    private function asMap(string $bytes): array
    {
        return $this->decodeMessage($bytes);
    }

    private function varintField(array $fields, int $number, ?int $default = null): ?int
    {
        foreach ($fields as $field) {
            if ($field['number'] === $number && $field['wire'] === 0) {
                return (int) $field['value'];
            }
        }

        return $default;
    }

    private function stringField(array $fields, int $number): ?string
    {
        foreach ($fields as $field) {
            if ($field['number'] === $number && $field['wire'] === 2) {
                return (string) $field['value'];
            }
        }

        return null;
    }

    /** @return list<int> */
    private function packedVarintsField(array $fields, int $number): array
    {
        foreach ($fields as $field) {
            if ($field['number'] !== $number || $field['wire'] !== 2) {
                continue;
            }
            $bytes = (string) $field['value'];
            $offset = 0;
            $values = [];
            while ($offset < strlen($bytes)) {
                $values[] = $this->readVarint($bytes, $offset);
            }

            return $values;
        }

        return [];
    }
}
